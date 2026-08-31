<?php

namespace App\Services;

use App\Models\LlmLog;
use App\Models\LlmProvider;
use App\Models\Tenant;
use App\Services\LlmFallbackService;
use Illuminate\Support\Facades\Log;

class LlmService
{
    protected array $adapters = [];

    protected ?LlmProvider $currentProvider = null;

    protected ?LlmFallbackService $fallbackService = null;

    public function __construct()
    {
        $this->fallbackService = app(LlmFallbackService::class);
    }

    /**
     * 注册适配器
     */
    public function registerAdapter(string $driver, string $adapterClass): void
    {
        $this->adapters[$driver] = $adapterClass;
    }

    /**
     * 获取指定或当前活跃 Provider 的适配器实例
     */
    public function driver(?string $slug = null): \App\Contracts\LlmProviderContract
    {
        $provider = $slug
            ? LlmProvider::where('slug', $slug)->first()
            : LlmProvider::getActive();

        if (! $provider) {
            throw new \RuntimeException(__('app.api.service_llm.no_provider'));
        }

        $this->currentProvider = $provider;

        $adapterClass = $this->adapters[$provider->driver] ?? null;
        if (! $adapterClass) {
            throw new \RuntimeException(__('app.api.service_llm.unknown_driver', ['driver' => $provider->driver]));
        }

        $adapter = app($adapterClass);
        $adapter->initialize($provider);

        return $adapter;
    }

    /**
     * 文本对话并记录日志
     */
    public function chat(array $messages, array $options = [], ?string $function = null): array
    {
        $adapter = $this->getAdapter($options);
        $model = $options['model'] ?? $this->currentProvider?->default_model ?? 'deepseek-chat';
        $startTime = microtime(true);

        try {
            $result = $adapter->chat($messages, $options);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $cost = $this->calculateCost(
                $model,
                $result['usage']['prompt_tokens'] ?? 0,
                $result['usage']['completion_tokens'] ?? 0,
            );

            $this->log([
                'llm_provider_id' => $this->currentProvider?->id,
                'model' => $model,
                'function' => $function ?? 'chat',
                'prompt' => $this->truncate(json_encode($messages), 5000),
                'response' => $this->truncate($result['content'], 10000),
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $result['usage']['total_tokens'] ?? 0,
                'cost_usd' => $cost,
                'duration_ms' => $durationMs,
                'success' => true,
            ]);

            // 记录成功到降级服务
            if ($this->currentProvider) {
                $this->fallbackService->recordSuccess($this->currentProvider);
            }

            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->log([
                'llm_provider_id' => $this->currentProvider?->id,
                'model' => $model,
                'function' => $function ?? 'chat',
                'prompt' => $this->truncate(json_encode($messages), 5000),
                'success' => false,
                'error_message' => $this->truncate($e->getMessage(), 500),
                'duration_ms' => $durationMs,
            ]);

            // 记录失败到降级服务
            if ($this->currentProvider) {
                $this->fallbackService->recordFailure($this->currentProvider, $e->getMessage());
            }

            // 降级链：尝试备用 Provider → 如果仍失败则尝试本地兜底
            if (empty($options['no_fallback'])) {
                try {
                    return $this->fallback($messages, $options, $function);
                } catch (\Throwable $fallbackError) {
                    // 最后兜底
                    $localResult = $this->localFallback($messages);
                    if ($localResult !== null) {
                        return $localResult;
                    }
                    throw $fallbackError;
                }
            }

            throw $e;
        }
    }

    /**
     * 流式对话
     */
    public function chatStream(array $messages, array $options = []): \Generator
    {
        $adapter = $this->getAdapter($options);
        $model = $options['model'] ?? $this->currentProvider?->default_model ?? 'deepseek-chat';
        $fullContent = '';
        $startTime = microtime(true);

        try {
            $stream = $adapter->chatStream($messages, $options);

            foreach ($stream as $chunk) {
                $fullContent .= $chunk['content'];
                yield $chunk;
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // 非流式无法获取精确 Token，先不记录
            $this->log([
                'llm_provider_id' => $this->currentProvider?->id,
                'model' => $model,
                'function' => 'stream',
                'prompt' => $this->truncate(json_encode($messages), 5000),
                'response' => $this->truncate($fullContent, 10000),
                'duration_ms' => $durationMs,
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->log([
                'llm_provider_id' => $this->currentProvider?->id,
                'model' => $model,
                'function' => 'stream',
                'prompt' => $this->truncate(json_encode($messages), 5000),
                'success' => false,
                'error_message' => $this->truncate($e->getMessage(), 500),
                'duration_ms' => $durationMs,
            ]);

            throw $e;
        }
    }

    /**
     * 获取 Provider 列表
     */
    public function listProviders(): array
    {
        return LlmProvider::orderBy('sort_order')->get()->toArray();
    }

    /**
     * 更新 Provider 配置
     */
    public function updateProvider(int $id, array $data): LlmProvider
    {
        $provider = LlmProvider::findOrFail($id);

        if (isset($data['api_key']) && $data['api_key']) {
            $data['api_key'] = 'encrypted:' . encrypt($data['api_key']);
        }

        $provider->update($data);
        return $provider->fresh();
    }

    /**
     * 测试 Provider 连接
     */
    public function testProvider(int $id): array
    {
        $provider = LlmProvider::findOrFail($id);
        $adapter = $this->getAdapterByProvider($provider);
        return $adapter->testConnection();
    }

    /**
     * Token 用量统计
     */
    public function tokenStats(?int $days = 30): array
    {
        $since = now()->subDays($days);

        $total = LlmLog::where('created_at', '>=', $since)
            ->where('success', true)
            ->selectRaw('
                SUM(prompt_tokens) as total_prompt,
                SUM(completion_tokens) as total_completion,
                SUM(total_tokens) as total_tokens,
                SUM(cost_usd) as total_cost,
                COUNT(*) as total_requests
            ')->first();

        $byModel = LlmLog::where('created_at', '>=', $since)
            ->where('success', true)
            ->selectRaw('model, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as requests')
            ->groupBy('model')
            ->orderByDesc('tokens')
            ->get();

        $byDay = LlmLog::where('created_at', '>=', $since)
            ->where('success', true)
            ->selectRaw('DATE(created_at) as date, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as requests')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_prompt_tokens' => (int) ($total->total_prompt ?? 0),
            'total_completion_tokens' => (int) ($total->total_completion ?? 0),
            'total_tokens' => (int) ($total->total_tokens ?? 0),
            'total_cost_usd' => round((float) ($total->total_cost ?? 0), 6),
            'total_requests' => (int) ($total->total_requests ?? 0),
            'by_model' => $byModel,
            'by_day' => $byDay,
        ];
    }

    private function getAdapter(array &$options): \App\Contracts\LlmProviderContract
    {
        $routing = app(LlmRoutingService::class);

        if (empty($options['provider'])) {
            $provider = $this->fallbackService->getAvailableProvider()
                ?? $routing->resolveProvider();
            if (! $provider) {
                throw new \RuntimeException(__('app.api.service_llm.no_provider'));
            }
            $options['provider'] = $provider->slug;
        }

        $options = $routing->applyDefaults($options);

        $slug = $options['provider'];
        unset($options['provider']);

        $provider = LlmProvider::where('slug', $slug)->first()
            ?? ($slug === 'ollama' ? $routing->ensureOllamaProvider() : null);

        if (! $provider) {
            throw new \RuntimeException(__('app.api.service_llm.unknown_provider_slug', ['slug' => $slug]));
        }

        $this->currentProvider = $provider;

        return $this->getAdapterByProvider($provider);
    }

    private function getAdapterByProvider(LlmProvider $provider): \App\Contracts\LlmProviderContract
    {
        $adapterClass = $this->adapters[$provider->driver] ?? null;
        if (! $adapterClass) {
            throw new \RuntimeException(__('app.api.service_llm.unknown_driver', ['driver' => $provider->driver]));
        }

        $adapter = app($adapterClass);
        $adapter->initialize($provider);

        return $adapter;
    }

    private function fallback(array $messages, array $options, ?string $function): array
    {
        // 使用降级服务获取可用的备用 Provider（排除刚失败的）
        $fallbackProvider = $this->fallbackService->getAvailableProvider($this->currentProvider);
        if (! $fallbackProvider) {
            throw new \RuntimeException(__('app.api.service_llm.no_fallback'));
        }

        // 确保是不同 Provider
        if ($this->currentProvider && $fallbackProvider->id === $this->currentProvider->id) {
            throw new \RuntimeException(__('app.api.service_llm.no_fallback'));
        }

        Log::warning('LLM fallback triggered', [
            'primary' => $this->currentProvider?->slug,
            'fallback' => $fallbackProvider->slug,
        ]);

        $options['no_fallback'] = true;
        $options['provider'] = $fallbackProvider->slug;

        return $this->chat($messages, $options, $function);
    }

    /**
     * 本地兜底：当所有远程 Provider 都不可用时返回简单响应
     */
    private function localFallback(array $messages): ?array
    {
        $lastMessage = end($messages);
        $lastContent = is_string($lastMessage['content'] ?? null) ? $lastMessage['content'] : '';

        Log::warning('LLM local fallback triggered', [
            'query_preview' => mb_substr($lastContent, 0, 100),
        ]);

        // D-38：Ollama 可用时尝试本地模型，避免直接返回硬编码文案
        if (config('local-llm.enabled')) {
            try {
                $routing = app(LlmRoutingService::class);
                $options = $routing->applyDefaults(['provider' => 'ollama', 'no_fallback' => true]);

                return $this->chat($messages, $options, 'local_ollama_fallback');
            } catch (\Throwable $e) {
                Log::debug('LLM local ollama fallback failed', ['error' => $e->getMessage()]);
            }
        }

        // 简单的关键词匹配兜底
        $localResponses = [
            'help' => __('app.api.service_llm.help_fallback'),
            'error' => __('app.api.service_llm.error_fallback'),
            'license' => __('app.api.service_llm.license_fallback'),
        ];

        // 匹配关键词
        foreach ($localResponses as $keyword => $response) {
            if (mb_stripos($lastContent, $keyword) !== false) {
                return [
                    'content' => $response,
                    'usage' => [
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0,
                    ],
                    'local_fallback' => true,
                ];
            }
        }

        // 默认兜底响应
        return [
            'content' => __('app.api.service_llm.chat_fallback'),
            'usage' => [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ],
            'local_fallback' => true,
        ];
    }

    private function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $adapter = $this->getAdapterByProvider($this->currentProvider);
        $pricing = $adapter->getPricing($model);

        $inputCost = ($promptTokens / 1000) * ($pricing['input'] ?? 0);
        $outputCost = ($completionTokens / 1000) * ($pricing['output'] ?? 0);

        return round($inputCost + $outputCost, 8);
    }

    private function log(array $data): void
    {
        try {
            LlmLog::create($data);
        } catch (\Throwable $e) {
            Log::warning('LLM 日志记录失败', ['error' => $e->getMessage()]);
        }
    }

    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) return $text;
        return mb_substr($text, 0, $limit) . '...';
    }
}
