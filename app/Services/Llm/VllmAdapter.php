<?php

namespace App\Services\Llm;

use App\Contracts\LlmProviderContract;
use App\Models\LlmProvider;
use Illuminate\Support\Facades\Http;

/**
 * vLLM 本地大模型适配器
 *
 * 基于 vLLM (OpenAI-compatible API) 的高性能本地推理适配
 * - 完全兼容 OpenAI API 格式
 * - 支持 PagedAttention 高效显存管理
 * - 支持连续批处理 (continuous batching)
 * - 支持多种量化格式 (AWQ/GPTQ/FP8)
 *
 * @m3-49 LocalLLMDeploy
 */
class VllmAdapter implements LlmProviderContract
{
    protected LlmProvider $config;
    protected string $apiBase = 'http://localhost:8000';
    protected string $apiKey = '';
    protected array $defaultOptions = [
        'temperature' => 0.7,
        'max_tokens' => 4096,
        'top_p' => 0.95,
    ];

    public function driver(): string
    {
        return 'vllm';
    }

    public function initialize(LlmProvider $config): void
    {
        $this->config = $config;
        $this->apiKey = $config->getApiKey() ?? '';
        $this->apiBase = rtrim($config->api_base ?: $this->apiBase, '/');
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'Qwen/Qwen2-7B-Instruct';
        $body = $this->buildRequestBody($messages, $model, $options, false);

        $startTime = microtime(true);
        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout(300)
            ->post("{$this->apiBase}/v1/chat/completions", $body);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'vLLM API 错误: ' . ($response->json('error')['message'] ?? $response->body()),
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
            'provider' => 'vllm',
        ];
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'Qwen/Qwen2-7B-Instruct';
        $body = $this->buildRequestBody($messages, $model, $options, true);

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout(600)
            ->withOptions(['stream' => true])
            ->post("{$this->apiBase}/v1/chat/completions", $body);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'vLLM 流式错误: ' . ($response->json('error')['message'] ?? $response->body()),
                $response->status()
            );
        }

        $bodyStream = $response->getBody();
        $buffer = '';

        while (!$bodyStream->eof()) {
            $chunk = $bodyStream->read(4096);
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);
                if (empty($line)) continue;
                if ($line === 'data: [DONE]') break 2;
                if (!str_starts_with($line, 'data: ')) continue;

                $json = substr($line, 6);
                $data = json_decode($json, true);
                if (!$data) continue;

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
        try {
            $headers = [];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            }

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get("{$this->apiBase}/v1/models");

            if (!$response->successful()) return [];

            $models = $response->json('data') ?? [];
            return array_map(fn($m) => [
                'id' => $m['id'],
                'name' => $m['id'],
                'description' => $m['owned_by'] ?? 'vLLM',
            ], $models);
        } catch (\Throwable) {
            return [
                ['id' => 'Qwen/Qwen2-7B-Instruct', 'name' => 'Qwen2 7B (vLLM)', 'description' => '阿里通义千问'],
                ['id' => 'Qwen/Qwen2-72B-Instruct', 'name' => 'Qwen2 72B (vLLM)', 'description' => '阿里通义千问大模型'],
                ['id' => 'deepseek-ai/DeepSeek-R1-Distill-Qwen-7B', 'name' => 'DeepSeek R1 7B', 'description' => 'DeepSeek R1 蒸馏版'],
                ['id' => 'mistralai/Mistral-7B-Instruct-v0.3', 'name' => 'Mistral 7B', 'description' => 'Mistral AI'],
            ];
        }
    }

    public function testConnection(): array
    {
        $startTime = microtime(true);
        try {
            $headers = [];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            }

            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->get("{$this->apiBase}/v1/models");

            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $models = $response->json('data') ?? [];
                return [
                    'success' => true,
                    'latency_ms' => $latencyMs,
                    'models_available' => count($models),
                    'message' => 'vLLM 连接成功，发现 ' . count($models) . ' 个模型',
                ];
            }

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => 'vLLM 响应异常: ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'message' => 'vLLM 连接失败: ' . $e->getMessage(),
            ];
        }
    }

    public function getPricing(string $model): array
    {
        return ['input' => 0, 'output' => 0];
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
            'stream' => $stream,
            'frequency_penalty' => $opts['frequency_penalty'] ?? 0,
            'presence_penalty' => $opts['presence_penalty'] ?? 0,
        ], fn($v) => $v !== null);
    }
}
