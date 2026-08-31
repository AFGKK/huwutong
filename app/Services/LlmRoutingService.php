<?php

namespace App\Services;

use App\Models\LlmProvider;

/**
 * LLM 默认路由：站点设置 → 配置 → 环境变量
 *
 * D-38：业务默认走 Ollama，不可用时由 LlmFallbackService 降级。
 */
class LlmRoutingService
{
    /**
     * 站点/配置中的默认 Provider slug
     */
    public function defaultProviderSlug(): string
    {
        $fromSetting = site_setting('llm_default_provider', null);
        if (is_string($fromSetting) && $fromSetting !== '') {
            return $fromSetting;
        }

        return (string) config('local-llm.default_provider', 'deepseek');
    }

    /**
     * 解析当前应使用的 Provider 记录
     */
    public function resolveProvider(?string $slug = null): ?LlmProvider
    {
        $slug = $slug ?? $this->defaultProviderSlug();

        $provider = $this->findActiveProvider($slug);
        if ($provider) {
            return $provider;
        }

        if ($slug === 'ollama' && config('local-llm.enabled')) {
            return $this->ensureOllamaProvider();
        }

        return LlmProvider::where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * 为 LLM 调用注入默认 provider / model
     */
    public function applyDefaults(array $options = []): array
    {
        if (empty($options['provider'])) {
            $options['provider'] = $this->defaultProviderSlug();
        }

        $provider = $this->findActiveProvider($options['provider']);
        if (! $provider && $options['provider'] === 'ollama' && config('local-llm.enabled')) {
            $provider = $this->ensureOllamaProvider();
        }

        if (empty($options['model'])) {
            if ($options['provider'] === 'ollama') {
                $options['model'] = config('local-llm.ollama.default_model', 'qwen2.5:7b');
            } elseif ($provider?->default_model) {
                $options['model'] = $provider->default_model;
            }
        }

        return $options;
    }

    /**
     * 确保 DB 中存在 Ollama Provider（从 config 同步）
     */
    public function ensureOllamaProvider(): LlmProvider
    {
        $defaultModel = config('local-llm.ollama.default_model', 'qwen2.5:7b');

        return LlmProvider::updateOrCreate(
            ['slug' => 'ollama'],
            [
                'name' => 'Ollama (Local)',
                'driver' => 'ollama',
                'api_base' => config('local-llm.ollama.api_base', 'http://127.0.0.1:11434'),
                'api_key' => '',
                'models' => [
                    ['id' => $defaultModel, 'name' => $defaultModel, 'description' => '本地 Ollama 默认模型'],
                ],
                'default_model' => $defaultModel,
                'config' => ['temperature' => 0.7, 'num_predict' => 4096],
                'sort_order' => 0,
                'is_active' => (bool) config('local-llm.enabled', false),
                'is_fallback' => false,
            ]
        );
    }

    protected function findActiveProvider(string $slug): ?LlmProvider
    {
        return LlmProvider::where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }
}
