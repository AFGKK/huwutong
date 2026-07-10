<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'enterprise',
            'level' => 'pro',
            'status' => 'active',
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 查询 ───

    public function test_index_returns_paginated_customers(): void
    {
        Customer::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/customers?per_page=3', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_filters_by_type(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'individual',
        ]);

        $response = $this->getJson('/api/customers?filter[type]=individual', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_returns_customer_detail(): void
    {
        $response = $this->getJson("/api/customers/{$this->customer->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer.id', $this->customer->id);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/customers/99999', $this->authHeaders());
        $response->assertStatus(404);
    }

    // ─── 创建 ───

    public function test_store_creates_customer(): void
    {
        $response = $this->postJson('/api/customers', [
            'type' => 'individual',
            'level' => 'free',
            'status' => 'active',
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/customers', [], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── 更新 ───

    public function test_update_customer(): void
    {
        $response = $this->putJson("/api/customers/{$this->customer->id}", [
            'type' => 'individual',
            'level' => 'enterprise',
            'status' => 'suspended',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'level' => 'enterprise',
            'status' => 'suspended',
        ]);
    }

    // ─── 统计 ───

    public function test_stats(): void
    {
        Customer::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/customers/stats', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total', 'enterprise', 'individual', 'by_level']]);
    }

    // ─── License 列表 ───

    public function test_licenses_returns_customer_licenses(): void
    {
        $product = Product::factory()->create();
        License::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'product_id' => $product->id,
        ]);

        $response = $this->getJson(
            "/api/customers/{$this->customer->id}/licenses",
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
    }

    // ─── 权限 ───

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/customers');
        $response->assertStatus(401);
    }
}
