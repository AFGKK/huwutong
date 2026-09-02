<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AiIntegrationWizardApiTest extends TestCase
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

    public function test_languages_returns_list(): void
    {
        $response = $this->getJson('/api/wizard/languages', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $json = $response->json('data');
        $this->assertIsArray($json);
        $php = collect($json)->firstWhere('id', 'php');
        $this->assertNotNull($php);
        $this->assertStringContainsString('huwutong/huwutong-sdk-php', $php['steps'][0] ?? '');
        $this->assertStringNotContainsString('license-sdk', json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    public function test_products_returns_list(): void
    {
        $response = $this->getJson('/api/wizard/products', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_generate_config_requires_params(): void
    {
        $response = $this->postJson('/api/wizard/generate-config', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_test_connectivity_requires_params(): void
    {
        $response = $this->postJson('/api/wizard/test-connectivity', [], $this->authHeaders());

        $response->assertStatus(422);
    }
}
