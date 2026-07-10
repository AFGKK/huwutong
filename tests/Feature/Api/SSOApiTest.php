<?php

namespace Tests\Feature\Api;

use App\Models\SsoProvider;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SSOApiTest extends TestCase
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

    // ─── SSO 回调（公开） ───

    public function test_callback_creates_user_and_returns_token(): void
    {
        $provider = SsoProvider::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Okta',
            'provider_type' => 'saml2',
            'is_active' => true,
            'idp_entity_id' => 'http://okta.com/idp',
            'idp_login_url' => 'https://okta.com/sso',
        ]);

        $response = $this->postJson('/api/sso/callback', [
            'provider_id' => $provider->id,
            'external_id' => 'user-001',
            'attributes' => [
                'email' => 'sso@example.com',
                'name' => 'SSO User',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'sso@example.com']);
        $this->assertDatabaseHas('sso_connections', ['external_id' => 'user-001']);
    }

    public function test_callback_returns_error_for_inactive_provider(): void
    {
        $provider = SsoProvider::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Disabled',
            'provider_type' => 'oidc',
            'is_active' => false,
            'client_id' => 'test',
            'authorization_url' => 'https://auth.com',
            'token_url' => 'https://token.com',
        ]);

        $response = $this->postJson('/api/sso/callback', [
            'provider_id' => $provider->id,
            'external_id' => 'user-002',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SSO_PROVIDER_INACTIVE');
    }

    // ─── SSO 管理（auth） ───

    public function test_list_providers(): void
    {
        SsoProvider::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Okta',
            'provider_type' => 'saml2',
            'is_active' => true,
            'idp_entity_id' => 'test',
            'idp_login_url' => 'https://okta.com/sso',
        ]);

        $response = $this->getJson('/api/sso/providers', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_configure_saml_provider(): void
    {
        $response = $this->postJson('/api/sso/providers', [
            'name' => 'Azure AD',
            'provider_type' => 'saml2',
            'config' => [
                'idp_entity_id' => 'https://sts.windows.net/tenant-id/',
                'idp_login_url' => 'https://login.microsoftonline.com/tenant-id/saml2',
                'idp_x509_certificate' => 'MIID...',
            ],
            'sp_entity_id' => 'https://app.huwutong.com',
            'sp_acs_url' => 'https://app.huwutong.com/sso/callback',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_configure_oidc_provider(): void
    {
        $response = $this->postJson('/api/sso/providers', [
            'name' => 'Google Workspace',
            'provider_type' => 'oidc',
            'config' => [
                'client_id' => 'google-client-id',
                'client_secret' => 'google-secret',
                'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
                'scopes' => 'openid email profile',
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
    }

    public function test_toggle_provider(): void
    {
        $provider = SsoProvider::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test',
            'provider_type' => 'saml2',
            'is_active' => true,
            'idp_entity_id' => 'test',
            'idp_login_url' => 'https://test.com/sso',
        ]);

        $response = $this->postJson("/api/sso/providers/{$provider->id}/toggle", [
            'is_active' => false,
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_list_connections(): void
    {
        $provider = SsoProvider::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Okta',
            'provider_type' => 'saml2',
            'is_active' => true,
            'idp_entity_id' => 'test',
            'idp_login_url' => 'https://okta.com/sso',
        ]);

        $this->user->ssoConnections()->create([
            'sso_provider_id' => $provider->id,
            'external_id' => 'ext-001',
            'last_login_at' => now(),
        ]);

        $response = $this->getJson('/api/sso/connections', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_disconnect(): void
    {
        $provider = SsoProvider::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Okta',
            'provider_type' => 'saml2',
            'is_active' => true,
            'idp_entity_id' => 'test',
            'idp_login_url' => 'https://okta.com/sso',
        ]);

        $connection = $this->user->ssoConnections()->create([
            'sso_provider_id' => $provider->id,
            'external_id' => 'ext-002',
        ]);

        $response = $this->deleteJson("/api/sso/connections/{$connection->id}", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('sso_connections', ['id' => $connection->id]);
    }

    // ─── login-url 端点 ───

    public function test_login_url_requires_auth(): void
    {
        $provider = SsoProvider::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Okta SAML',
            'provider_type' => 'saml2',
            'is_active' => true,
            'idp_entity_id' => 'test',
            'idp_login_url' => 'https://okta.com/sso',
        ]);

        $response = $this->getJson("/api/sso/providers/{$provider->id}/login-url", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['login_url', 'provider_name', 'provider_type']])
            ->assertJsonPath('data.provider_name', 'Okta SAML')
            ->assertJsonPath('data.provider_type', 'saml2');
    }

    public function test_login_url_rejects_unauthenticated(): void
    {
        $provider = SsoProvider::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Okta SAML',
            'provider_type' => 'saml2',
            'is_active' => true,
            'idp_entity_id' => 'test',
            'idp_login_url' => 'https://okta.com/sso',
        ]);

        $response = $this->getJson("/api/sso/providers/{$provider->id}/login-url");

        $response->assertStatus(401);
    }
}
