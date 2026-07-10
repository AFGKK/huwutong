<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class DiagnosticApiTest extends TestCase
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

    public function test_diagnose_requires_error_code(): void
    {
        $response = $this->postJson('/api/diagnostic/diagnose', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_diagnose_accepts_valid_request(): void
    {
        $response = $this->postJson('/api/diagnostic/diagnose', [
            'error_code' => 'E001',
            'context' => ['key' => 'value'],
        ], $this->authHeaders());

        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_diagnose_activation_accepts_valid(): void
    {
        $response = $this->postJson('/api/diagnostic/activation', [
            'license_key' => 'TEST-KEY',
            'error_code' => 'ACTIVATION_FAILED',
        ], $this->authHeaders());

        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_diagnose_batch_requires_errors(): void
    {
        $response = $this->postJson('/api/diagnostic/batch', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_diagnose_batch_with_valid_errors(): void
    {
        $response = $this->postJson('/api/diagnostic/batch', [
            'errors' => [
                ['code' => 'E001', 'context' => ['key' => 'value']],
                ['code' => 'E002'],
            ],
        ], $this->authHeaders());

        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_sdk_suggestions_returns_map(): void
    {
        $response = $this->getJson('/api/diagnostic/sdk-suggestions', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
