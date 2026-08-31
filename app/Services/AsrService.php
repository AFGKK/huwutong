<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsrService
{
    private array $dbSettings = [];

    public function __construct()
    {
        // 从数据库加载配置（优先于 .env）
        try {
            $settings = SiteSetting::where('group', 'asr')->pluck('value', 'key');
            $this->dbSettings = $settings->toArray();
        } catch (\Exception $e) {
            $this->dbSettings = [];
        }
    }

    private function getConfig(string $key, string $default = ''): string
    {
        // 数据库配置优先
        if (!empty($this->dbSettings[$key])) {
            return $this->dbSettings[$key];
        }
        // 回退到 .env 配置
        return $default;
    }

    /**
     * 将语音消息转为文字
     */
    public function transcribe(ConversationMessage $message): string
    {
        $meta = $message->metadata ?? [];
        if (!empty($meta['transcript'])) {
            return $meta['transcript'];
        }

        $audioUrl = $message->content;
        if (empty($audioUrl)) {
            throw new \RuntimeException(__("app.asr.voice_content_empty"));
        }

        $provider = $this->getConfig('asr_provider', 'mock');

        $transcript = match ($provider) {
            'openai' => $this->transcribeWithWhisper($audioUrl),
            'aliyun' => $this->transcribeWithAliyun($audioUrl),
            'tencent' => $this->transcribeWithTencent($audioUrl),
            default => $this->fallbackTranscript(),
        };

        // 缓存转写结果
        $meta['transcript'] = $transcript;
        $meta['transcribed_at'] = now()->toIso8601String();
        $meta['asr_provider'] = $provider;
        $message->update(['metadata' => $meta]);

        return $transcript;
    }

    /**
     * OpenAI Whisper API
     */
    private function transcribeWithWhisper(string $audioUrl): ?string
    {
        $apiKey = $this->getConfig('asr_openai_key', config('services.openai.api_key', ''));
        if (empty($apiKey)) return null;

        try {
            // 下载音频文件
            $audioContent = @file_get_contents($audioUrl);
            if ($audioContent === false) return null;

            // 写入临时文件
            $tmpPath = tempnam(sys_get_temp_dir(), 'asr_') . '.webm';
            file_put_contents($tmpPath, $audioContent);

            $response = Http::withToken($apiKey)
                ->attach('file', file_get_contents($tmpPath), 'audio.webm')
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                    'language' => 'zh',
                    'response_format' => 'json',
                ]);

            @unlink($tmpPath);

            if ($response->successful()) {
                return $response->json('text', '');
            }

            Log::warning('Whisper API failed: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::warning('Whisper API exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 阿里云语音识别（需配置）
     */
    private function transcribeWithAliyun(string $audioUrl): ?string
    {
        $appKey = $this->getConfig('asr_aliyun_app_key', config('services.aliyun.asr_app_key', ''));
        $accessKeyId = $this->getConfig('asr_aliyun_access_key', config('services.aliyun.access_key_id', ''));
        $accessKeySecret = $this->getConfig('asr_aliyun_access_secret', config('services.aliyun.access_key_secret', ''));
        if (empty($appKey) || empty($accessKeyId)) return null;

        // 阿里云 RESTful API 语音识别
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAliyunToken($accessKeyId, $accessKeySecret),
            ])->post('https://nls-meta.cn-shanghai.aliyuncs.com/api/v1/recognizer/asr', [
                'app_key' => $appKey,
                'audio_url' => $audioUrl,
                'format' => 'webm',
                'sample_rate' => 16000,
                'enable_intermediate_result' => false,
                'enable_punctuation_prediction' => true,
            ]);

            if ($response->successful()) {
                return $response->json('result', '');
            }
        } catch (\Exception $e) {
            Log::warning('Aliyun ASR failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * 腾讯云语音识别（需配置）
     */
    private function transcribeWithTencent(string $audioUrl): ?string
    {
        $secretId = $this->getConfig('asr_tencent_secret_id', config('services.tencent.asr_secret_id', ''));
        $secretKey = $this->getConfig('asr_tencent_secret_key', config('services.tencent.asr_secret_key', ''));
        if (empty($secretId)) return null;

        // 腾讯云语音识别 API
        try {
            $response = Http::withBasicAuth($secretId, $secretKey)
                ->post('https://asr.tencentcloudapi.com/', [
                    'Action' => 'CreateRecTask',
                    'Version' => '2019-06-14',
                    'EngineModelType' => '16k_0',
                    'ChannelNum' => 1,
                    'ResTextFormat' => 0,
                    'SourceType' => 0,
                    'Url' => $audioUrl,
                ]);

            if ($response->successful()) {
                $taskId = $response->json('Response.Data.TaskId');
                if ($taskId) {
                    // 查询结果
                    sleep(2);
                    $result = Http::withBasicAuth($secretId, $secretKey)
                        ->post('https://asr.tencentcloudapi.com/', [
                            'Action' => 'DescribeTaskStatus',
                            'Version' => '2019-06-14',
                            'TaskId' => $taskId,
                        ]);
                    if ($result->successful()) {
                        return $result->json('Response.Data.ResultDetail.0.RecordText', '');
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Tencent ASR failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * 模拟模式或降级处理
     */
    private function fallbackTranscript(): string
    {
        // 模拟模式
        if ($this->getConfig('asr_provider', 'mock') === 'mock') {
            $mockTexts = [
                '好的，我明白了，谢谢你的解答。',
                '这个产品什么时候可以发货？',
                '我想咨询一下关于退款的问题。',
                '请帮我查一下我的订单状态。',
                '你们的工作时间是几点到几点？',
                '你好，我想了解一下这个产品的功能。',
                '收到，我马上处理。',
                '不好意思，能再说一遍吗？',
                '价格方面还能再优惠一些吗？',
                '好的，我明天再联系你。',
            ];
            return $mockTexts[array_rand($mockTexts)];
        }

        return '[语音转文字服务未配置，请在后台 IM → ⚙️ 配置 → 语音识别中设置]';
    }

    private function getAliyunToken(string $accessKeyId, string $accessKeySecret): string
    {
        // 简化实现：实际应使用阿里云 SDK 获取 token
        return base64_encode($accessKeyId . ':' . $accessKeySecret);
    }
}
