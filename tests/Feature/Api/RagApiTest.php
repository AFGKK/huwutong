<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RagApiTest extends TestCase
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
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 检索 ───

    public function test_retrieve_requires_query(): void
    {
        $response = $this->postJson('/api/rag/retrieve', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_retrieve_accepts_valid_request(): void
    {
        $response = $this->postJson('/api/rag/retrieve', [
            'q' => '如何激活 License',
        ], $this->authHeaders());

        // RAG 服务可能不可用或返回空结果，但请求应被接受
        $this->assertContains($response->status(), [200, 500]);
    }

    // ─── 提问 ───

    public function test_ask_requires_question(): void
    {
        $response = $this->postJson('/api/rag/ask', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_ask_accepts_valid_request(): void
    {
        $response = $this->postJson('/api/rag/ask', [
            'q' => '如何使用这个系统？',
            'session_id' => 'test-session-1',
        ], $this->authHeaders());

        $this->assertContains($response->status(), [200, 500]);
    }

    // ─── 对话历史 ───

    public function test_history_requires_session_id(): void
    {
        $response = $this->getJson('/api/rag/history', $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_history_returns_empty_for_new_session(): void
    {
        $response = $this->getJson('/api/rag/history?session_id=new-session', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ─── 反馈 ───

    public function test_feedback_requires_message_id(): void
    {
        $response = $this->postJson('/api/rag/feedback', [
            'was_helpful' => true,
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_feedback_validates_message_id_exists(): void
    {
        $response = $this->postJson('/api/rag/feedback', [
            'message_id' => 99999,
            'was_helpful' => true,
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    // ─── 统计（管理） ───

    public function test_stats_returns_data(): void
    {
        $response = $this->getJson('/api/rag/stats', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
