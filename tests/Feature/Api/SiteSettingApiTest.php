<?php

namespace Tests\Feature\Api;

use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SiteSettingApiTest extends TestCase
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

        SiteSetting::create([
            'group' => 'general',
            'key' => 'site_name',
            'value' => 'My App',
            'type' => 'text',
            'is_public' => true,
        ]);

        SiteSetting::create([
            'group' => 'seo',
            'key' => 'meta_description',
            'value' => 'A great app',
            'type' => 'textarea',
            'is_public' => false,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_index_returns_all_settings(): void
    {
        $response = $this->getJson('/api/settings/all', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data']);
    }

    public function test_grouped_returns_grouped_settings(): void
    {
        $response = $this->getJson('/api/settings', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => [['group', 'label', 'settings']]]);
    }

    public function test_update_requires_settings_array(): void
    {
        $response = $this->postJson('/api/settings', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_update_modifies_settings(): void
    {
        $response = $this->postJson('/api/settings', [
            'settings' => [
                ['key' => 'site_name', 'value' => 'New App Name'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('site_settings', [
            'key' => 'site_name',
            'value' => 'New App Name',
        ]);
    }

    public function test_store_creates_new_setting(): void
    {
        $response = $this->postJson('/api/settings/create', [
            'group' => 'brand',
            'key' => 'logo_url',
            'value' => '/logo.png',
            'type' => 'image',
            'is_public' => true,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }

    public function test_store_requires_unique_key(): void
    {
        $response = $this->postJson('/api/settings/create', [
            'group' => 'general',
            'key' => 'site_name',
            'value' => 'Duplicate',
            'type' => 'text',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_public_settings_returns_public_only(): void
    {
        $response = $this->getJson('/api/settings/public');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertArrayHasKey('site_name', $data);
        $this->assertArrayNotHasKey('meta_description', $data);
    }
}
