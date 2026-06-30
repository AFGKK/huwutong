<?php

namespace App\Services\Llm;

use App\Contracts\LlmProviderContract;
use App\Models\LlmProvider;
use Illuminate\Support\Facades\Http;

/**
 * Ollama 本地大模型适配器
 *
 * 基于 Ollama API 的本地部署大模型适配
 * - 支持所有 Ollama 模型 (llama3/qwen2/mistral 等)
 * - 数据不出内网
 * - 支持 GPU 加速
 *
 * @m3-49 LocalLLMDeploy
 */
class OllamaAdapter implements LlmProviderContract
{
    protected LlmProvider $config;
    protected string $apiBase = 'http://localhost:11434';
    protected string $apiKey = '';
    protected array $defaultOptions = [
        'temperature' => 0.7,
        'num_predict' => 4096,
        'top_p' => 0.95,
        'seed' => 42,
    ];

    public function driver(): string
    {
        return 'ollama';
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
            if (isset($config->config['num_predict'])) {
                $this->defaultOptions['num_predict'] = (int) $config->config['num_predict'];
            }
        }
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'llama3';
        $prompt = $this->messagesToPrompt($messages);

        $body = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => $options['temperature'] ?? $this->defaultOptions['temperature'],
                'num_predict' => $options['max_tokens'] ?? $options['num_predict'] ?? $this->defaultOptions['num_predict'],
                'top_p' => $options['top_p'] ?? $this->defaultOptions['top_p'],
                'seed' => $this->defaultOptions['seed'],
            ],
        ];

        $startTime = microtime(true);
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(300)->post("{$this->apiBase}/api/generate", $body);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Ollama API 错误: ' . ($response->json('error') ?? $response->body()),
                $response->status()
            );
        }

        $data = $response->json();

        return [
            'content' => $data['response'] ?? '',
            'model' => $data['model'] ?? $model,
            'finish_reason' => 'stop',
            'usage' => [
                'prompt_tokens' => $data['prompt_eval_count'] ?? 0,
                'completion_tokens' => $data['eval_count'] ?? 0,
                'total_tokens' => ($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0),
            ],
            'duration_ms' => $durationMs,
            'eval_duration_ms' => isset($data['eval_duration']) ? (int)($data['eval_duration'] / 1000000) : null,
            'provider' => 'ollama',
        ];
    }

    public function chatStream(array $messages, array $options = []): \Generator
    {
        $model = $options['model'] ?? $this->config->default_model ?? 'llama3';
        $prompt = $this->messagesToPrompt($messages);

        $body = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => true,
            'options' => [
                'temperature' => $options['temperature'] ?? $this->defaultOptions['temperature'],
                'num_predict' => $options['max_tokens'] ?? $options['num_predict'] ?? $this->defaultOptions['num_predict'],
                'top_p' => $options['top_p'] ?? $this->defaultOptions['top_p'],
            ],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(600)->withOptions(['stream' => true])
            ->post("{$this->apiBase}/api/generate", $body);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Ollama 流式错误: ' . ($response->json('error') ?? $response->body()),
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

                $data = json_decode($line, true);
                if (!$data) continue;

                yield [
                    'content' => $data['response'] ?? '',
                    'finish_reason' => $data['done'] ? 'stop' : null,
                    'model' => $data['model'] ?? $model,
                ];

                if ($data['done'] ?? false) break 2;
            }
        }
    }

    public function listModels(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiBase}/api/tags");
            if (!$response->successful()) return [];

            $models = $response->json('models') ?? [];
            return array_map(fn($m) => [
                'id' => $m['name'],
                'name' => $m['name'],
                'description' => "Size: " . ($this->formatBytes($m['size'] ?? 0)) . ", Modified: " . ($m['modified_at'] ?? ''),
                'size' => $m['size'] ?? 0,
                'details' => $m['details'] ?? [],
            ], $models);
        } catch (\Throwable) {
            return [
                ['id' => 'llama3', 'name' => 'Llama 3 (8B)', 'description' => 'Meta 开源模型'],
                ['id' => 'llama3:70b', 'name' => 'Llama 3 (70B)', 'description' => 'Meta 开源大模型'],
                ['id' => 'qwen2:7b', 'name' => 'Qwen 2 (7B)', 'description' => '阿里通义千问'],
                ['id' => 'qwen2:72b', 'name' => 'Qwen 2 (72B)', 'description' => '阿里通义千问大模型'],
                ['id' => 'mistral', 'name' => 'Mistral (7B)', 'description' => 'Mistral AI 开源模型'],
                ['id' => 'deepseek-r1:7b', 'name' => 'DeepSeek R1 (7B)', 'description' => 'DeepSeek R1 蒸馏版'],
                ['id' => 'deepseek-r1:14b', 'name' => 'DeepSeek R1 (14B)', 'description' => 'DeepSeek R1 中等规模'],
                ['id' => 'nomic-embed-text', 'name' => 'Nomic Embed Text', 'description' => '文本嵌入模型'],
            ];
        }
    }

    public function testConnection(): array
    {
        $startTime = microtime(true);
        try {
            $response = Http::timeout(15)->get("{$this->apiBase}/api/tags");
            $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $models = $response->json('models') ?? [];
                return [
                    'success' => true,
                    'latency_ms' => $latencyMs,
                    'models_available' => count($models),
                    'message' => 'Ollama 连接成功，发现 ' . count($models) . ' 个模型',
                ];
            }

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => 'Ollama 响应异常: ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'message' => 'Ollama 连接失败: ' . $e->getMessage(),
            ];
        }
    }

    public function getPricing(string $model): array
    {
        return ['input' => 0, 'output' => 0]; // 本地部署免费
    }

    /**
     * 将消息列表转换为 Ollama prompt
     */
    protected function messagesToPrompt(array $messages): string
    {
        $prompt = '';
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            switch ($role) {
                case 'system':
                    $prompt .= "<|system|>\n{$content}\n";
                    break;
                case 'user':
                    $prompt .= "<|user|>\n{$content}\n";
                    break;
                case 'assistant':
                    $prompt .= "<|assistant|>\n{$content}\n";
                    break;
                default:
                    $prompt .= "{$content}\n";
            }
        }
        $prompt .= "<|assistant|>\n";
        return $prompt;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
