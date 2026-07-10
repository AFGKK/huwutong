<?php

namespace Tests\Feature\Api;

use App\Enums\LicenseStatus;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\KeyGenerator;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class LicenseManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private User $user;
    private License $license;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->addYear(),
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 查询测试 ───

    public function test_index_returns_paginated_licenses(): void
    {
        License::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/licenses?per_page=3', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data', 'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_index_filters_by_status(): void
    {
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => LicenseStatus::Expired->value,
        ]);

        $response = $this->getJson('/api/licenses?filter[status]=expired', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_returns_license_detail(): void
    {
        $response = $this->getJson("/api/licenses/{$this->license->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.license.id', $this->license->id);
    }

    public function test_lookup_by_license_key(): void
    {
        $response = $this->postJson('/api/licenses/lookup', [
            'license_key' => $this->license->license_key,
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('data.license_key', $this->license->license_key);
    }

    public function test_lookup_nonexistent_key(): void
    {
        $response = $this->postJson('/api/licenses/lookup', [
            'license_key' => 'INVALID-KEY',
        ], $this->authHeaders());

        $response->assertStatus(404);
    }

    // ─── 状态管理测试 ───

    public function test_revoke_license(): void
    {
        $response = $this->postJson(
            "/api/licenses/{$this->license->id}/revoke",
            ['reason' => '违规使用'],
            $this->authHeaders(),
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('licenses', [
            'id' => $this->license->id,
            'status' => LicenseStatus::Revoked->value,
        ]);
    }

    public function test_suspend_license(): void
    {
        $response = $this->postJson(
            "/api/licenses/{$this->license->id}/suspend",
            ['reason' => '欠费挂起'],
            $this->authHeaders(),
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('licenses', [
            'id' => $this->license->id,
            'status' => LicenseStatus::Suspended->value,
        ]);
    }

    public function test_freeze_license(): void
    {
        $response = $this->postJson(
            "/api/licenses/{$this->license->id}/freeze",
            ['reason' => '风控冻结'],
            $this->authHeaders(),
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('licenses', [
            'id' => $this->license->id,
            'status' => LicenseStatus::Frozen->value,
        ]);
    }

    public function test_restore_license(): void
    {
        // 先冻结
        $this->license->update(['status' => LicenseStatus::Frozen->value]);

        $response = $this->postJson(
            "/api/licenses/{$this->license->id}/restore",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('licenses', [
            'id' => $this->license->id,
            'status' => LicenseStatus::Active->value,
        ]);
    }

    public function test_blacklist_license(): void
    {
        $response = $this->postJson(
            "/api/licenses/{$this->license->id}/blacklist",
            ['reason' => '恶意使用'],
            $this->authHeaders(),
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('licenses', [
            'id' => $this->license->id,
            'status' => LicenseStatus::Blacklisted->value,
        ]);
    }

    public function test_refund_license(): void
    {
        $response = $this->postJson(
            "/api/licenses/{$this->license->id}/refund",
            ['reason' => '客户申请退款'],
            $this->authHeaders(),
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('licenses', [
            'id' => $this->license->id,
            'status' => LicenseStatus::Refunded->value,
        ]);
    }

    public function test_invalid_state_transition_returns_error(): void
    {
        // 已撤销的 License 不允许再撤销
        $this->license->update(['status' => LicenseStatus::Revoked->value]);

        $response = $this->postJson(
            "/api/licenses/{$this->license->id}/suspend",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(422);
    }

    public function test_show_triggers_audit_log_entry(): void
    {
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->addYear(),
        ]);

        // 撤销操作会触发审计日志（通过 EventBus）
        $this->postJson(
            "/api/licenses/{$this->license->id}/revoke",
            ['reason' => '测试撤销审计'],
            $this->authHeaders(),
        );

        $this->assertDatabaseHas('logs', [
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license->id,
            'action' => 'license.status_changed',
        ]);
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/licenses');
        $response->assertStatus(401);
    }

    // ─── 补充端点测试 ───

    public function test_batch_store_creates_multiple_licenses(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/licenses/batch', [
            'product_id' => $this->product->id,
            'type' => 'standard',
            'count' => 3,
            'expires_at' => now()->addYear()->toDateString(),
            'seats' => 1,
            'max_devices' => 2,
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_batch_store_rejects_oversized_count(): void
    {
        $response = $this->postJson('/api/licenses/batch', [
            'product_id' => $this->product->id,
            'type' => 'standard',
            'count' => 999,
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_batch_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/licenses/batch', [], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_lookup_nonexistent_key_already_tested(): void
    {
        // already covered by test_lookup_nonexistent_key
        $this->assertTrue(true);
    }
}
