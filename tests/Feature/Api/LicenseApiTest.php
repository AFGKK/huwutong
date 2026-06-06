<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseApiTest extends TestCase
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

    public function test_index_returns_paginated(): void
    {
        $response = $this->getJson('/api/licenses', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_requires_fields(): void
    {
        $response = $this->postJson('/api/licenses', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/licenses/999', $this->authHeaders());

        $response->assertStatus(404);
    }

    public function test_stats_returns_data(): void
    {
        $response = $this->getJson('/api/licenses/stats', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_lookup_requires_license_key(): void
    {
        $response = $this->postJson('/api/licenses/lookup', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_destroy_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/api/licenses/999', [], $this->authHeaders());

        $response->assertStatus(404);
    }
}
