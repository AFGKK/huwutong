<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Device;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Device $device;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $product = Product::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
        ]);

        $this->device = Device::factory()->create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $license->id,
            'platform' => 'windows',
            'trust_score' => 85,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 查询 ───

    public function test_index_returns_paginated_devices(): void
    {
        Device::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/devices?per_page=3', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_filters_by_platform(): void
    {
        Device::factory()->create([
            'tenant_id' => $this->tenant->id,
            'platform' => 'macos',
        ]);

        $response = $this->getJson(
            '/api/devices?filter[platform]=macos',
            $this->authHeaders(),
        );

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_returns_device_detail(): void
    {
        $response = $this->getJson("/api/devices/{$this->device->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->device->id);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/devices/99999', $this->authHeaders());
        $response->assertStatus(404);
    }

    // ─── 操作 ───

    public function test_deactivate_device(): void
    {
        $response = $this->postJson(
            "/api/devices/{$this->device->id}/deactivate",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('devices', [
            'id' => $this->device->id,
            'license_id' => null,
            'trust_score' => 0,
        ]);
    }

    public function test_deactivate_with_blacklist(): void
    {
        $response = $this->postJson(
            "/api/devices/{$this->device->id}/deactivate",
            ['blacklist' => true],
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('devices', [
            'id' => $this->device->id,
            'is_blacklisted' => true,
        ]);
    }

    // ─── 统计 ───

    public function test_stats(): void
    {
        Device::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/devices/stats', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total', 'active', 'blacklisted', 'virtual']]);
    }

    // ─── 权限 ───

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/devices');
        $response->assertStatus(401);
    }
}
