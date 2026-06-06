<?php

namespace Tests\Feature\Api;

use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $product;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $this->product = Product::factory()->create([
            'name' => '测试产品',
            'slug' => 'test-product',
            'is_active' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 查询 ───

    public function test_index_returns_paginated_products(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/products?per_page=3', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_filters_by_active(): void
    {
        Product::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/products?filter[is_active]=1', $this->authHeaders());

        $response->assertStatus(200);
        foreach ($response->json('data') as $p) {
            $this->assertTrue($p['is_active']);
        }
    }

    public function test_show_returns_product_detail(): void
    {
        $response = $this->getJson("/api/products/{$this->product->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.id', $this->product->id);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/products/99999', $this->authHeaders());
        $response->assertStatus(404);
    }

    // ─── 创建 ───

    public function test_store_creates_product(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => '新产品',
            'slug' => 'new-product',
            'description' => '产品描述',
            'version' => '1.0.0',
            'is_active' => true,
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', '新产品');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/products', [], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── 更新 ───

    public function test_update_product(): void
    {
        $response = $this->putJson("/api/products/{$this->product->id}", [
            'name' => '已更新产品',
            'version' => '2.0.0',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'name' => '已更新产品',
            'version' => '2.0.0',
        ]);
    }

    // ─── 统计 ───

    public function test_stats(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products/stats', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    // ─── License 关联 ───

    public function test_licenses_returns_product_licenses(): void
    {
        License::factory()->count(2)->create([
            'product_id' => $this->product->id,
        ]);

        $response = $this->getJson(
            "/api/products/{$this->product->id}/licenses",
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ─── 功能 ───

    public function test_show_features(): void
    {
        $features = FeatureFlag::factory()->count(3)->create();

        // Attach features to the product via pivot table
        $this->product->featureFlags()->sync($features->pluck('id'));

        $response = $this->getJson(
            "/api/products/{$this->product->id}/features",
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ─── 权限 ───

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/products');
        $response->assertStatus(401);
    }
}
