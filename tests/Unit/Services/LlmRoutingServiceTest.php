<?php

namespace Tests\Unit\Services;

use App\Models\LlmProvider;
use App\Models\SiteSetting;
use App\Services\LlmRoutingService;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class LlmRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('site_settings_all');
    }

    public function test_default_provider_from_site_setting(): void
    {
        SiteSetting::create([
            'group' => 'ai',
            'key' => 'llm_default_provider',
            'value' => 'ollama',
            'type' => 'select',
            'is_public' => false,
        ]);
        Cache::forget('site_settings_all');

        $this->assertSame('ollama', app(LlmRoutingService::class)->defaultProviderSlug());
    }

    public function test_default_provider_falls_back_to_config(): void
    {
        config(['local-llm.default_provider' => 'ollama']);

        $this->assertSame('ollama', app(LlmRoutingService::class)->defaultProviderSlug());
    }

    public function test_apply_defaults_injects_ollama_model(): void
    {
        config([
            'local-llm.enabled' => true,
            'local-llm.default_provider' => 'ollama',
            'local-llm.ollama.default_model' => 'qwen2.5:7b',
        ]);

        LlmProvider::create([
            'name' => 'Ollama',
            'slug' => 'ollama',
            'driver' => 'ollama',
            'api_base' => 'http://127.0.0.1:11434',
            'default_model' => 'qwen2.5:7b',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $options = app(LlmRoutingService::class)->applyDefaults([]);

        $this->assertSame('ollama', $options['provider']);
        $this->assertSame('qwen2.5:7b', $options['model']);
    }

    public function test_ensure_ollama_provider_creates_record(): void
    {
        config([
            'local-llm.enabled' => true,
            'local-llm.ollama.api_base' => 'http://127.0.0.1:11434',
            'local-llm.ollama.default_model' => 'qwen2.5:7b',
        ]);

        $provider = app(LlmRoutingService::class)->ensureOllamaProvider();

        $this->assertSame('ollama', $provider->slug);
        $this->assertSame('qwen2.5:7b', $provider->default_model);
        $this->assertDatabaseHas('llm_providers', ['slug' => 'ollama']);
    }
}
