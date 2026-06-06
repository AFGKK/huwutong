<?php

namespace App\Services\Llm;

use App\Contracts\LlmProviderContract;
use App\Models\LlmProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekAdapter implements LlmProviderContract
{
    protected LlmProvider $config;

    protected string $apiBase = 'https://api.deepseek.com';

    protected string $apiKey = '';

    protected array $defaultOptions = [
        'temperature' => 0.7,
        'max_tokens' => 4096,
        'top_p' => 0.95,
        'frequency_penalty' => 0,
        'presence_penalty' => 0,
    ];

    // 模型定价（每 1K tokens, USD）
    protected array $pricing = [
        'deepseek-chat' => ['input' => 0.00027, 'output' => 0.00110],   // DeepSeek-V3
        'deepseek-reasoner' => ['input' => 0.00055, 'output' => 0.00219], // DeepSeek-R1
    ];

    public function driver(): string
    {
        return 'deepseek';
    }

    public function initialize(LlmProvider $config): void
    {
        $this->config = $config;
        $this->apiKey = $config->getApiKey() ?? '';
        $this->apiBase = rtrim($config->api_base ?: $this->apiBase, '/');

        if ($config->config && isset($config->config['temperature'])) {
            $this->defaultOptions['temperature'] = (float) $config->config['temperature'];
        }
        if ($config->config && isset($config->config['max_tokens'])) {
            $this->defaultOptions['max_tokens'] = (int) $config->config['max_tokens'];
        }
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'deepseek-chat';
        $body = $this->buildRequestBody($messages, $model, $options, false);

        $startTime = microtime(true);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post("{$this->apiBase}/v1/chat/completions", $body);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'DeepSeek API 错误: ' . ($response->json('error.message') ?? $response->body()),
                $response->status()
            );
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'model' => $data['model'] ?? $model,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ],
            'duration_ms' => $durationMs,
        ];
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'deepseek-chat';
        $body = $this->buildRequestBody($messages, $model, $options, true);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(300)->withOptions(['stream' => true])
            ->post("{$this->apiBase}/v1/chat/completions", $body);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'DeepSeek 流式 API 错误: ' . ($response->json('error.message') ?? $response->body()),
                $response->status()
            );
        }

        $bodyStream = $response->getBody();
        $buffer = '';

        while (! $bodyStream->eof()) {
            $chunk = $bodyStream->read(4096);
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);

                if (empty($line)) continue;
                if ($line === 'data: [DONE]') break 2;
                if (! str_starts_with($line, 'data: ')) continue;

                $json = substr($line, 6);
                $data = json_decode($json, true);
                if (! $data) continue;

                $delta = $data['choices'][0]['delta'] ?? [];
                $finishReason = $data['choices'][0]['finish_reason'] ?? null;

                yield [
                    'content' => $delta['content'] ?? '',
                    'finish_reason' => $finishReason,
                    'model' => $data['model'] ?? $model,
                ];

                if ($finishReason) break 2;
            }
        }
    }

    public function listModels(): array
    {
        return [
            ['id' => 'deepseek-chat', 'name' => 'DeepSeek-V3', 'description' => '最新版 V3 对话模型'],
            ['id' => 'deepseek-reasoner', 'name' => 'DeepSeek-R1', 'description' => '深度推理模型，适合复杂问题'],
        ];
    }

    public function testConnection(): array
    {
        $startTime = microtime(true);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->timeout(15)->get("{$this->apiBase}/v1/models");

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $models = $response->json('data') ?? [];
                return [
                    'success' => true,
                    'latency_ms' => $latencyMs,
                    'models_available' => count($models),
                    'message' => 'DeepSeek API 连接成功',
                ];
            }

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => 'DeepSeek API 响应异常: ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'message' => '连接失败: ' . $e->getMessage(),
            ];
        }
    }

    public function getPricing(string $model): array
    {
        return $this->pricing[$model] ?? ['input' => 0.00027, 'output' => 0.00110];
    }

    protected function buildRequestBody(array $messages, string $model, array $options, bool $stream): array
    {
        $opts = array_merge($this->defaultOptions, $options);

        return [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $opts['temperature'] ?? 0.7,
            'max_tokens' => (int) ($opts['max_tokens'] ?? 4096),
            'top_p' => $opts['top_p'] ?? 0.95,
            'frequency_penalty' => $opts['frequency_penalty'] ?? 0,
            'presence_penalty' => $opts['presence_penalty'] ?? 0,
            'stream' => $stream,
        ];
    }
}
