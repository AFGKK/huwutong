<?php

namespace App\Services;

use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AccessibilityService
{
    protected LlmService $llm;

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    /**
     * 为图片生成 ALT 描述文本
     */
    public function generateImageAlt(string $imageUrl): string
    {
        $cacheKey = 'img_alt_' . md5($imageUrl);

        return Cache::remember($cacheKey, 86400 * 30, function () use ($imageUrl) {
            try {
                $result = $this->llm->chat([
                    ['role' => 'system', 'content' => '你是一个图片描述助手，为视障用户生成简洁的图片描述。
要求：
- 用一句话描述图片核心内容（15-30字）
- 描述主体、动作、场景
- 保持客观，不添加推测
- 用中文
- 如果图片包含文字，提取关键文字'],
                    ['role' => 'user', 'content' => "请描述这张图片：{$imageUrl}"],
                ], ['temperature' => 0.2, 'max_tokens' => 150], 'a11y_image_alt');

                return $result['content'] ?? '图片';
            } catch (\Throwable $e) {
                Log::warning('Image ALT generation failed', ['error' => $e->getMessage()]);
                return '图片';
            }
        });
    }

    /**
     * 为聊天消息生成摘要（供屏幕阅读器快速浏览）
     */
    public function summarizeMessage(ConversationMessage $msg): string
    {
        $sender = $msg->sender?->name ?? '用户';
        $type = $msg->message_type ?? 'text';
        $time = $msg->created_at?->format('H:i') ?? '';

        return match ($type) {
            'text' => "{$sender} 在 {$time} 说：{$msg->content}",
            'image' => "{$sender} 在 {$time} 发送了一张图片：" . ($msg->metadata['alt_text'] ?? ''),
            'voice' => "{$sender} 在 {$time} 发送了一条语音消息",
            'file' => "{$sender} 在 {$time} 发送了文件：" . ($msg->metadata['file_name'] ?? '文件'),
            'forward' => "{$sender} 在 {$time} 转发了消息",
            'sticker' => "{$sender} 在 {$time} 发送了一个贴纸",
            default => "{$sender} 在 {$time} 发送了一条 {$type} 类型消息",
        };
    }

    /**
     * 为整段会话生成无障碍摘要
     */
    public function summarizeConversation(int $convId, int $limit = 20): array
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')
            ->latest()
            ->take($limit)
            ->get()
            ->reverse();

        $summaries = [];
        foreach ($messages as $msg) {
            $summaries[] = $this->summarizeMessage($msg);
        }

        return [
            'total' => count($summaries),
            'items' => $summaries,
            'full_text' => implode("\n", $summaries),
        ];
    }

    /**
     * 生成图片的详细无障碍描述（长文，用于独立查看）
     */
    public function describeImageDetail(string $imageUrl): array
    {
        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => '你是一个专业的无障碍描述助手。为视障用户生成详细的图片描述，包含：
1. 图片类型（照片/插画/图表/截图等）
2. 主体描述
3. 颜色和构图
4. 文字内容（如有）
5. 整体氛围
使用中文，描述要准确、客观。'],
                ['role' => 'user', 'content' => "请详细描述这张图片：{$imageUrl}"],
            ], ['temperature' => 0.3, 'max_tokens' => 500], 'a11y_image_detail');

            $description = $result['content'] ?? '无法生成描述';

            return [
                'description' => $description,
                'short_alt' => $this->generateImageAlt($imageUrl),
            ];
        } catch (\Throwable $e) {
            return [
                'description' => '图片描述暂时不可用',
                'short_alt' => '图片',
            ];
        }
    }

    /**
     * 文字转语音（调用现有 TTS API）
     */
    public function textToSpeech(string $text): ?string
    {
        try {
            $asr = app(AsrService::class);
            // 使用 TTS 方法（如果存在）
            if (method_exists($asr, 'synthesize')) {
                return $asr->synthesize($text);
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('TTS failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
