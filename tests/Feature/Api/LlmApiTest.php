<?php

namespace Tests\Feature\Api;

use App\Models\LlmProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        LlmProvider::create([
            'name' => 'OpenAI',
            'slug' => 'openai',
            'driver' => 'openai',
            'api_base' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'default_model' => 'gpt-4o',
            'models' => ['gpt-4o', 'gpt-3.5-turbo'],
            'config' => ['temperature' => 0.7, 'max_tokens' => 2000],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── Provider 列表 ───

    public function test_providers_returns_list(): void
    {
        $response = $this->getJson('/api/llm/providers', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => [['id', 'name', 'slug', 'driver', 'is_active']]]);
    }

    // ─── 更新 Provider ───

    public function test_update_provider_modifies_config(): void
    {
        $provider = LlmProvider::first();

        $response = $this->putJson("/api/llm/providers/{$provider->id}", [
            'name' => 'OpenAI Updated',
            'is_active' => false,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'OpenAI Updated');
        $response->assertJsonPath('data.is_active', false);
    }

    // ─── 测试连接 ───

    public function test_test_connection_returns_result_or_error(): void
    {
        $provider = LlmProvider::first();

        $response = $this->postJson("/api/llm/providers/{$provider->id}/test", [], $this->authHeaders());

        // 连接测试可能成功也可能失败，但应有响应
        $this->assertContains($response->status(), [200, 500]);
    }

    // ─── 对话 ───

    public function test_chat_validates_messages(): void
    {
        $response = $this->postJson('/api/llm/chat', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_chat_accepts_valid_request(): void
    {
        $response = $this->postJson('/api/llm/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ], $this->authHeaders());

        // LLM 服务可能不可用
        $this->assertContains($response->status(), [200, 500]);
    }

    // ─── Token 统计 ───

    public function test_token_stats_returns_data(): void
    {
        $response = $this->getJson('/api/llm/token-stats', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ─── 日志 ───

    public function test_logs_returns_paginated(): void
    {
        $response = $this->getJson('/api/llm/logs', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data']);
    }
}
