<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class TenantRouterApiTest extends TestCase
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

    // ─── 租户列表 ───

    public function test_index_returns_tenant_list(): void
    {
        // 将用户关联到当前租户
        $this->user->tenants()->attach($this->tenant->id);

        $response = $this->getJson('/api/tenants', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['tenants', 'current_tenant_id', 'is_multi_tenant']]);
    }

    // ─── 切换租户 ───

    public function test_switch_changes_active_tenant(): void
    {
        $this->user->tenants()->attach($this->tenant->id);

        $response = $this->postJson('/api/tenants/switch', [
            'tenant_id' => $this->tenant->id,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.active_tenant_id', $this->tenant->id);
    }

    public function test_switch_validates_tenant_exists(): void
    {
        $response = $this->postJson('/api/tenants/switch', [
            'tenant_id' => 99999,
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_switch_returns_forbidden_for_unrelated_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        $response = $this->postJson('/api/tenants/switch', [
            'tenant_id' => $otherTenant->id,
        ], $this->authHeaders());

        $response->assertStatus(403);
    }

    // ─── SSO 信息 ───

    public function test_sso_info_returns_no_selection_for_single_tenant(): void
    {
        $this->user->tenants()->attach($this->tenant->id);

        $response = $this->getJson('/api/tenants/sso-info', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.requires_selection', false);
        $response->assertJsonStructure(['data' => ['requires_selection', 'tenant']]);
    }
}
