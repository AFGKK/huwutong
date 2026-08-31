<?php

namespace Tests\Feature\Api;

use App\Models\LlmProvider;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LlmFallbackService;
use App\Services\LlmService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * T-23：Ollama 本地 LLM 业务路由
 */
class OllamaLlmApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'local-llm.enabled' => true,
            'local-llm.default_provider' => 'ollama',
            'local-llm.ollama.api_base' => 'http://127.0.0.1:11434',
            'local-llm.ollama.default_model' => 'qwen2.5:7b',
        ]);

        Cache::forget('site_settings_all');

        SiteSetting::create([
            'group' => 'ai',
            'key' => 'llm_default_provider',
            'value' => 'ollama',
            'type' => 'select',
            'is_public' => false,
        ]);

        LlmProvider::create([
            'name' => 'Ollama (Local)',
            'slug' => 'ollama',
            'driver' => 'ollama',
            'api_base' => 'http://127.0.0.1:11434',
            'api_key' => '',
            'default_model' => 'qwen2.5:7b',
            'models' => [['id' => 'qwen2.5:7b', 'name' => 'qwen2.5:7b']],
            'config' => ['temperature' => 0.7, 'num_predict' => 4096],
            'is_active' => true,
            'sort_order' => 0,
            'is_fallback' => false,
        ]);

        LlmProvider::create([
            'name' => 'DeepSeek',
            'slug' => 'deepseek',
            'driver' => 'deepseek',
            'api_base' => 'https://api.deepseek.com',
            'api_key' => 'sk-test',
            'default_model' => 'deepseek-chat',
            'models' => [['id' => 'deepseek-chat', 'name' => 'DeepSeek-V3']],
            'config' => ['temperature' => 0.7, 'max_tokens' => 4096],
            'is_active' => true,
            'sort_order' => 1,
            'is_fallback' => true,
        ]);

        app(LlmFallbackService::class)->resetAllCircuits();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_fallback_service_prefers_ollama_when_site_default(): void
    {
        $provider = app(LlmFallbackService::class)->getAvailableProvider();

        $this->assertNotNull($provider);
        $this->assertSame('ollama', $provider->slug);
    }

    public function test_chat_completion_uses_ollama_when_available(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/generate' => Http::response([
                'model' => 'qwen2.5:7b',
                'response' => '你好，我是本地 Ollama 助手。',
                'done' => true,
                'prompt_eval_count' => 10,
                'eval_count' => 8,
            ], 200),
        ]);

        $result = app(LlmService::class)->chat([
            ['role' => 'user', 'content' => '你好'],
        ], [], 'test_ollama');

        $this->assertStringContainsString('本地 Ollama', $result['content']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/generate')
                && ($request['model'] ?? '') === 'qwen2.5:7b';
        });
    }

    public function test_chat_falls_back_when_ollama_model_missing(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/generate' => Http::response(['error' => 'model not found'], 404),
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'DeepSeek 降级回复']]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 2, 'total_tokens' => 3],
            ], 200),
        ]);

        $result = app(LlmService::class)->chat([
            ['role' => 'user', 'content' => '测试降级'],
        ], [], 'test_fallback');

        $this->assertStringContainsString('DeepSeek 降级回复', $result['content']);
    }

    public function test_ollama_health_via_provider_test(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen2.5:7b', 'size' => 1000, 'modified_at' => '2026-01-01'],
                ],
            ], 200),
        ]);

        $provider = LlmProvider::where('slug', 'ollama')->first();
        $result = app(LlmService::class)->testProvider($provider->id);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['models_available']);
    }

    public function test_api_chat_routes_to_ollama_by_default(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/generate' => Http::response([
                'model' => 'qwen2.5:7b',
                'response' => 'API 路由 Ollama 回复',
                'done' => true,
            ], 200),
        ]);

        $response = $this->postJson('/api/llm/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'hello'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.content', 'API 路由 Ollama 回复');
    }
}
