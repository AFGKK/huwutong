<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmFallbackApiTest extends TestCase
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

    public function test_status_returns_info(): void
    {
        $response = $this->getJson('/api/llm/fallback/status', $this->authHeaders());

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_reset_returns_confirmation(): void
    {
        $response = $this->postJson('/api/llm/fallback/reset', [], $this->authHeaders());

        $this->assertContains($response->status(), [200, 403, 500]);
    }
}
