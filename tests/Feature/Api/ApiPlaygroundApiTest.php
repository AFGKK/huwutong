<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ApiPlaygroundApiTest extends TestCase
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

    // ─── 公开路由 ───

    public function test_endpoints_returns_list(): void
    {
        $response = $this->getJson('/api/playground/endpoints');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ─── 受保护路由 ───

    public function test_execute_requires_params(): void
    {
        $response = $this->postJson('/api/playground/execute', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_generate_code_requires_params(): void
    {
        $response = $this->postJson('/api/playground/generate-code', [], $this->authHeaders());

        $response->assertStatus(422);
    }
}
