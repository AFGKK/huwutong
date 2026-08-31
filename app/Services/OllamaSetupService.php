<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * D-37: Ollama 运行时启动与健康检查、模型拉取
 */
class OllamaSetupService
{
    public function apiBase(): string
    {
        return rtrim(config('local-llm.ollama.api_base', 'http://127.0.0.1:11434'), '/');
    }

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(5)->get($this->apiBase() . '/api/tags')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{status: string, models: array<int, array>, count: int, api_base: string}
     */
    public function health(): array
    {
        if (! $this->isAvailable()) {
            return [
                'status' => 'unavailable',
                'models' => [],
                'count' => 0,
                'api_base' => $this->apiBase(),
            ];
        }

        try {
            $response = Http::timeout(10)->get($this->apiBase() . '/api/tags');
            $models = $response->json('models') ?? [];

            return [
                'status' => 'available',
                'models' => array_map(fn ($m) => [
                    'name' => $m['name'] ?? '',
                    'size' => $m['size'] ?? 0,
                    'modified_at' => $m['modified_at'] ?? null,
                ], $models),
                'count' => count($models),
                'api_base' => $this->apiBase(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'models' => [],
                'count' => 0,
                'api_base' => $this->apiBase(),
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return list<string>
     */
    public function recommendedModels(): array
    {
        return config('local-llm.recommended_models', [
            'qwen2.5:7b',
            'qwen2.5:1.5b',
            'nomic-embed-text',
        ]);
    }

    public function pullModel(string $modelName): array
    {
        if (! $this->isAvailable()) {
            return [
                'success' => false,
                'model' => $modelName,
                'message' => 'Ollama 不可用，请先启动服务',
            ];
        }

        try {
            $response = Http::timeout((int) config('local-llm.ollama.timeout', 3600))
                ->post($this->apiBase() . '/api/pull', [
                    'name' => $modelName,
                    'stream' => false,
                ]);

            if ($response->successful()) {
                Log::info('Ollama: model pulled', ['model' => $modelName]);

                return [
                    'success' => true,
                    'model' => $modelName,
                    'message' => "模型 {$modelName} 下载完成",
                ];
            }

            return [
                'success' => false,
                'model' => $modelName,
                'message' => '下载失败: ' . ($response->json('error') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'model' => $modelName,
                'message' => '下载异常: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, pulled: array<int, array>, failed: array<int, array>}
     */
    public function pullRecommendedModels(?array $models = null): array
    {
        $models = $models ?? $this->recommendedModels();
        $pulled = [];
        $failed = [];

        foreach ($models as $model) {
            $result = $this->pullModel($model);
            if ($result['success']) {
                $pulled[] = $result;
            } else {
                $failed[] = $result;
            }
        }

        return [
            'success' => $failed === [],
            'pulled' => $pulled,
            'failed' => $failed,
        ];
    }
}
