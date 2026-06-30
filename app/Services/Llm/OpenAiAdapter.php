<?php

namespace App\Services\Llm;

use App\Contracts\LlmProviderContract;
use App\Models\LlmProvider;
use Illuminate\Support\Facades\Http;

class OpenAiAdapter implements LlmProviderContract
{
    protected LlmProvider $config;
    protected string $apiBase = 'https://api.openai.com';
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
        'gpt-4o' => ['input' => 0.0025, 'output' => 0.0100],
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.00060],
        'gpt-4-turbo' => ['input' => 0.0100, 'output' => 0.0300],
        'gpt-4' => ['input' => 0.0300, 'output' => 0.0600],
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
    ];

    public function driver(): string
    {
        return 'openai';
    }

    public function initialize(LlmProvider $config): void
    {
        $this->config = $config;
        $this->apiKey = $config->getApiKey() ?? '';
        $this->apiBase = rtrim($config->api_base ?: $this->apiBase, '/');

        if ($config->config) {
            if (isset($config->config['temperature'])) {
                $this->defaultOptions['temperature'] = (float) $config->config['temperature'];
            }
            if (isset($config->config['max_tokens'])) {
                $this->defaultOptions['max_tokens'] = (int) $config->config['max_tokens'];
            }
        }
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'gpt-4o-mini';
        $body = $this->buildRequestBody($messages, $model, $options, false);

        $startTime = microtime(true);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post("{$this->apiBase}/v1/chat/completions", $body);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OpenAI API 错误: ' . ($response->json('error.message') ?? $response->body()),
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
        $model = $options['model'] ?? $this->config->default_model ?? 'gpt-4o-mini';
        $body = $this->buildRequestBody($messages, $model, $options, true);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(300)->withOptions(['stream' => true])
            ->post("{$this->apiBase}/v1/chat/completions", $body);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OpenAI 流式 API 错误: ' . ($response->json('error.message') ?? $response->body()),
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
            ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'description' => '最新多模态旗舰模型'],
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'description' => '轻量快速，性价比最佳'],
            ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'description' => '高性能推理'],
            ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo', 'description' => '经济型模型'],
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
                    'message' => 'OpenAI API 连接成功',
                ];
            }

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => 'OpenAI API 响应异常: ' . $response->status(),
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
        return $this->pricing[$model] ?? ['input' => 0.00015, 'output' => 0.00060];
    }

    protected function buildRequestBody(array $messages, string $model, array $options, bool $stream): array
    {
        $opts = array_merge($this->defaultOptions, $options);

        return array_filter([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $opts['temperature'] ?? 0.7,
            'max_tokens' => (int) ($opts['max_tokens'] ?? 4096),
            'top_p' => $opts['top_p'] ?? 0.95,
            'frequency_penalty' => $opts['frequency_penalty'] ?? 0,
            'presence_penalty' => $opts['presence_penalty'] ?? 0,
            'stream' => $stream,
        ], fn($v) => $v !== null);
    }
}
