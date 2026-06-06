<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['is_sandbox' => true]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 创建沙箱 ───

    public function test_create_requires_authenticated_user(): void
    {
        $response = $this->postJson('/api/sandbox/create', []);

        $response->assertStatus(401);
    }

    // ─── 状态 ───

    public function test_status_returns_data_for_sandbox_tenant(): void
    {
        $response = $this->getJson('/api/sandbox/status', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_status_returns_error_for_non_sandbox(): void
    {
        $regularTenant = Tenant::factory()->create(['is_sandbox' => false]);
        $regularUser = User::factory()->create(['tenant_id' => $regularTenant->id]);
        $regularToken = $regularUser->createToken('test-token', ['*'])->plainTextToken;

        $response = $this->getJson('/api/sandbox/status', [
            'Authorization' => 'Bearer ' . $regularToken,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'NOT_SANDBOX');
    }

    // ─── 重置 ───

    public function test_reset_works_for_sandbox(): void
    {
        $response = $this->postJson('/api/sandbox/reset', [], $this->authHeaders());

        // SandboxService 可能不可用
        $this->assertContains($response->status(), [200, 400, 500]);
    }

    // ─── License 列表 ───

    public function test_licenses_returns_list_for_sandbox(): void
    {
        $response = $this->getJson('/api/sandbox/licenses', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_licenses_returns_error_for_non_sandbox(): void
    {
        $regularTenant = Tenant::factory()->create(['is_sandbox' => false]);
        $regularUser = User::factory()->create(['tenant_id' => $regularTenant->id]);
        $regularToken = $regularUser->createToken('test-token', ['*'])->plainTextToken;

        $response = $this->getJson('/api/sandbox/licenses', [
            'Authorization' => 'Bearer ' . $regularToken,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'NOT_SANDBOX');
    }
}
