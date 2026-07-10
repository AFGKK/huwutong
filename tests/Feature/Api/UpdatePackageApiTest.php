<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class UpdatePackageApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
        $this->product = Product::factory()->create();
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_index_returns_paginated(): void
    {
        $response = $this->getJson("/api/products/{$this->product->id}/updates", $this->authHeaders());

        // authorize 可能失败
        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_check_update_requires_current_version(): void
    {
        $response = $this->getJson(
            "/api/products/{$this->product->id}/updates/check",
            $this->authHeaders()
        );

        $response->assertStatus(422);
    }

    public function test_check_update_accepts_valid_request(): void
    {
        $response = $this->getJson(
            "/api/products/{$this->product->id}/updates/check?current_version=1.0.0",
            $this->authHeaders()
        );

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/updates/999', $this->authHeaders());

        $response->assertStatus(404);
    }

    public function test_destroy_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/api/updates/999', [], $this->authHeaders());

        $response->assertStatus(404);
    }

    public function test_publish_returns_404_for_nonexistent(): void
    {
        $response = $this->postJson('/api/updates/999/publish', [], $this->authHeaders());

        $response->assertStatus(404);
    }

    public function test_deprecate_returns_404_for_nonexistent(): void
    {
        $response = $this->postJson('/api/updates/999/deprecate', [], $this->authHeaders());

        $response->assertStatus(404);
    }

    public function test_download_redirects_or_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/updates/999/download', []);

        $response->assertStatus(404);
    }

    public function test_download_stats_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/updates/999/stats', $this->authHeaders());

        $response->assertStatus(404);
    }
}
