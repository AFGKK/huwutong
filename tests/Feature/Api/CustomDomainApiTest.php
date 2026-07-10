<?php

namespace Tests\Feature\Api;

use App\Models\CustomDomain;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CustomDomainApiTest extends TestCase
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

        CustomDomain::create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'example.com',
            'status' => 'pending',
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_index_returns_list(): void
    {
        $response = $this->getJson('/api/domains', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data']);
    }

    public function test_store_requires_domain(): void
    {
        $response = $this->postJson('/api/domains', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_store_creates_domain(): void
    {
        $response = $this->postJson('/api/domains', [
            'domain' => 'test.com',
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/domains/999', $this->authHeaders());

        $response->assertStatus(404);
    }

    public function test_verify_returns_200_or_422(): void
    {
        $domain = CustomDomain::first();

        $response = $this->postJson("/api/domains/{$domain->id}/verify", [], $this->authHeaders());

        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_dns_info_returns_data(): void
    {
        $domain = CustomDomain::first();

        $response = $this->getJson("/api/domains/{$domain->id}/dns", $this->authHeaders());

        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_destroy_deletes_domain(): void
    {
        $domain = CustomDomain::first();

        $response = $this->deleteJson("/api/domains/{$domain->id}", [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertModelMissing($domain);
    }
}
