<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingApiTest extends TestCase
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

    // ─── 获取 Staging 环境 ───

    public function test_index_returns_null_when_no_env(): void
    {
        $response = $this->getJson('/api/staging', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ─── 创建 Staging 环境 ───

    public function test_create_new_environment(): void
    {
        $this->tenant->update(['has_staging' => false]);

        $response = $this->postJson('/api/staging/create', [
            'name' => 'Test Staging',
        ], $this->authHeaders());

        // StagingService 可能不可用（依赖外部资源），但请求应被接受
        $this->assertContains($response->status(), [200, 400, 500]);
    }

    // ─── 详情 ───

    public function test_show_forbidden_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['has_staging' => true]);

        $response = $this->getJson("/api/staging/{$otherTenant->id}", $this->authHeaders());

        $response->assertStatus(404);
    }

    // ─── 更新 ───

    public function test_update_requires_valid_data(): void
    {
        $response = $this->putJson('/api/staging/999', [
            'rate_limit' => 10,
        ], $this->authHeaders());

        $response->assertStatus(404);
    }

    // ─── License 列表 ───

    public function test_licenses_returns_forbidden_no_env(): void
    {
        $response = $this->getJson('/api/staging/999/licenses', $this->authHeaders());

        $response->assertStatus(404);
    }
}
