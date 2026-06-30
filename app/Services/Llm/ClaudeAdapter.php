<?php

namespace App\Services\Llm;

use App\Contracts\LlmProviderContract;
use App\Models\LlmProvider;
use Illuminate\Support\Facades\Http;

class ClaudeAdapter implements LlmProviderContract
{
    protected LlmProvider $config;
    protected string $apiBase = 'https://api.anthropic.com';
    protected string $apiKey = '';
    protected string $apiVersion = '2023-06-01';
    protected array $defaultOptions = [
        'temperature' => 0.7,
        'max_tokens' => 4096,
        'top_p' => 0.95,
    ];

    protected array $pricing = [
        'claude-opus-4' => ['input' => 0.015, 'output' => 0.075],
        'claude-sonnet-4' => ['input' => 0.003, 'output' => 0.015],
        'claude-haiku-3' => ['input' => 0.00025, 'output' => 0.00125],
        'claude-sonnet-3.5' => ['input' => 0.003, 'output' => 0.015],
    ];

    public function driver(): string
    {
        return 'claude';
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
            if (isset($config->config['api_version'])) {
                $this->apiVersion = $config->config['api_version'];
            }
        }
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'claude-haiku-3';
        $systemMsg = $this->extractSystemMessage($messages);
        $body = $this->buildRequestBody($messages, $model, $options, $systemMsg);

        $startTime = microtime(true);
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post("{$this->apiBase}/v1/messages", $body);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Claude API 错误: ' . ($response->json('error.message') ?? $response->body()),
                $response->status()
            );
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'model' => $data['model'] ?? $model,
            'finish_reason' => $data['stop_reason'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
            'duration_ms' => $durationMs,
        ];
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'claude-haiku-3';
        $systemMsg = $this->extractSystemMessage($messages);
        $body = $this->buildRequestBody($messages, $model, $options, $systemMsg);
        $body['stream'] = true;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ])->timeout(300)->withOptions(['stream' => true])
            ->post("{$this->apiBase}/v1/messages", $body);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Claude 流式 API 错误: ' . ($response->json('error.message') ?? $response->body()),
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

                // Claude SSE format: event: ... \n data: {...}
                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    $data = json_decode($json, true);
                    if (!$data) continue;

                    if ($data['type'] === 'content_block_delta' && isset($data['delta']['text'])) {
                        yield [
                            'content' => $data['delta']['text'],
                            'finish_reason' => null,
                            'model' => $model,
                        ];
                    }

                    if ($data['type'] === 'message_stop') {
                        yield [
                            'content' => '',
                            'finish_reason' => 'stop',
                            'model' => $model,
                        ];
                        break 2;
                    }
                }
            }
        }
    }

    public function listModels(): array
    {
        return [
            ['id' => 'claude-opus-4', 'name' => 'Claude Opus 4', 'description' => '最强推理，复杂任务'],
            ['id' => 'claude-sonnet-4', 'name' => 'Claude Sonnet 4', 'description' => '平衡性能与速度'],
            ['id' => 'claude-haiku-3', 'name' => 'Claude Haiku 3', 'description' => '轻量快速，日常对话'],
            ['id' => 'claude-sonnet-3.5', 'name' => 'Claude Sonnet 3.5', 'description' => '上一代平衡模型'],
        ];
    }

    public function testConnection(): array
    {
        $startTime = microtime(true);
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
            ])->timeout(15)->get("{$this->apiBase}/v1/models");

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'latency_ms' => $latencyMs,
                    'models_available' => count($response->json('data') ?? []),
                    'message' => 'Claude API 连接成功',
                ];
            }

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => 'Claude API 响应异常: ' . $response->status(),
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
        return $this->pricing[$model] ?? ['input' => 0.003, 'output' => 0.015];
    }

    protected function extractSystemMessage(array &$messages): ?string
    {
        foreach ($messages as $i => $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $systemContent = $msg['content'] ?? '';
                unset($messages[$i]);
                $messages = array_values($messages);
                return $systemContent;
            }
        }
        return null;
    }

    protected function buildRequestBody(array $messages, string $model, array $options, ?string $systemMsg): array
    {
        $opts = array_merge($this->defaultOptions, $options);
        unset($opts['provider'], $opts['no_fallback'], $opts['model']);

        // 转换消息格式（Claude 使用 user/assistant）
        $claudeMessages = [];
        foreach ($messages as $msg) {
            if (in_array($msg['role'] ?? '', ['user', 'assistant'])) {
                $claudeMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        if (empty($claudeMessages)) {
            $claudeMessages[] = ['role' => 'user', 'content' => 'Hello'];
        }

        $body = [
            'model' => $model,
            'messages' => $claudeMessages,
            'max_tokens' => (int) ($opts['max_tokens'] ?? 4096),
            'temperature' => $opts['temperature'] ?? 0.7,
        ];

        if ($systemMsg) {
            $body['system'] = $systemMsg;
        }

        return $body;
    }
}
