<?php

namespace Tests\Unit\Services;

use App\Models\SsoConnection;
use App\Models\SsoProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SSOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SSOServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private SSOService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SSOService::class);
        $this->tenant = Tenant::factory()->create();
    }

    // ─── 配置测试 ───

    public function test_configure_saml_provider(): void
    {
        $provider = $this->service->configureProvider(
            $this->tenant->id,
            'Okta',
            'saml2',
            [
                'idp_entity_id' => 'http://www.okta.com/exk123',
                'idp_login_url' => 'https://okta.com/saml/sso',
                'idp_x509_certificate' => 'MIID...',
            ],
            'sp-entity-id',
            'https://app.huwutong.com/sso/callback',
        );

        $this->assertDatabaseHas('sso_providers', [
            'id' => $provider->id,
            'tenant_id' => $this->tenant->id,
            'provider_type' => 'saml2',
            'is_active' => true,
        ]);

        $this->assertEquals('Okta', $provider->name);
        $this->assertEquals('http://www.okta.com/exk123', $provider->idp_entity_id);
    }

    public function test_configure_oidc_provider(): void
    {
        $provider = $this->service->configureProvider(
            $this->tenant->id,
            'Azure AD',
            'oidc',
            [
                'client_id' => 'azure-client-123',
                'client_secret' => 'secret-456',
                'authorization_url' => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/token',
                'userinfo_url' => 'https://graph.microsoft.com/oidc/userinfo',
                'scopes' => 'openid email profile',
            ],
        );

        $this->assertEquals('oidc', $provider->provider_type);
        $this->assertEquals('azure-client-123', $provider->client_id);
    }

    public function test_configure_invalid_type_throws_exception(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->configureProvider($this->tenant->id, 'Test', 'invalid_type', []);
    }

    public function test_configure_update_existing_provider(): void
    {
        $this->service->configureProvider($this->tenant->id, 'Old', 'oidc', [
            'client_id' => 'old-client',
            'authorization_url' => 'https://old.com/auth',
            'token_url' => 'https://old.com/token',
        ]);

        $this->service->configureProvider($this->tenant->id, 'Updated', 'oidc', [
            'client_id' => 'new-client',
            'authorization_url' => 'https://new.com/auth',
            'token_url' => 'https://new.com/token',
        ]);

        $this->assertDatabaseHas('sso_providers', [
            'tenant_id' => $this->tenant->id,
            'provider_type' => 'oidc',
            'name' => 'Updated',
        ]);

        $this->assertDatabaseMissing('sso_providers', ['name' => 'Old']);
    }

    // ─── 回调处理测试 ───

    public function test_handle_callback_for_new_user(): void
    {
        $provider = $this->createSamlProvider();

        $user = $this->service->handleCallback($provider, 'ext-123', [
            'email' => 'sso-user@example.com',
            'name' => 'SSO User',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'sso-user@example.com',
            'name' => 'SSO User',
        ]);

        $this->assertDatabaseHas('sso_connections', [
            'user_id' => $user->id,
            'sso_provider_id' => $provider->id,
            'external_id' => 'ext-123',
        ]);
    }

    public function test_handle_callback_for_existing_connection(): void
    {
        $provider = $this->createSamlProvider();
        $user = $this->service->handleCallback($provider, 'ext-456', [
            'email' => 'existing@example.com',
            'name' => 'Existing',
        ]);

        $firstLogin = $user->last_login_at;

        // 同一用户再次登录
        $sameUser = $this->service->handleCallback($provider, 'ext-456', [
            'email' => 'existing@example.com',
            'name' => 'Existing',
        ]);

        $this->assertEquals($user->id, $sameUser->id);

        // 不应该有重复的 SSO 连接记录
        $this->assertDatabaseCount('sso_connections', 1);
    }

    public function test_handle_callback_links_existing_user_by_email(): void
    {
        $provider = $this->createSamlProvider();

        // 先创建一个本地用户
        $localUser = User::factory()->create([
            'email' => 'local@example.com',
            'tenant_id' => $this->tenant->id,
        ]);

        // SSO 登录（邮箱匹配）
        $ssoUser = $this->service->handleCallback($provider, 'ext-789', [
            'email' => 'local@example.com',
            'name' => 'Local User',
        ]);

        $this->assertEquals($localUser->id, $ssoUser->id);

        $this->assertDatabaseHas('sso_connections', [
            'user_id' => $localUser->id,
            'external_id' => 'ext-789',
        ]);
    }

    public function test_get_active_providers(): void
    {
        $this->createSamlProvider();
        $this->service->configureProvider($this->tenant->id, 'Inactive', 'oidc', [
            'client_id' => 'test',
            'authorization_url' => 'https://test.com/auth',
            'token_url' => 'https://test.com/token',
        ]);
        // 手动停用
        $inactiveProvider = SsoProvider::where('tenant_id', $this->tenant->id)
            ->where('provider_type', 'oidc')->first();
        $inactiveProvider->update(['is_active' => false]);

        $active = $this->service->getActiveProviders($this->tenant->id);

        $this->assertCount(1, $active);
        $this->assertEquals('saml2', $active[0]['provider_type']);
    }

    // ─── 属性映射测试 ───

    public function test_map_attributes(): void
    {
        $mapping = [
            'mail' => 'email',
            'givenName' => 'name',
            'mobile' => 'phone',
        ];

        $idpAttributes = [
            'mail' => 'user@company.com',
            'givenName' => '张三',
            'mobile' => '13800138000',
            'department' => 'IT',
        ];

        $result = $this->service->mapAttributes($idpAttributes, $mapping);

        $this->assertEquals('user@company.com', $result['email']);
        $this->assertEquals('张三', $result['name']);
        $this->assertEquals('13800138000', $result['phone']);
        $this->assertArrayNotHasKey('department', $result);
    }

    public function test_map_attributes_handles_array_values(): void
    {
        $mapping = ['urn:oid:0.9.2342.19200300.100.1.3' => 'email'];

        $idpAttributes = [
            'urn:oid:0.9.2342.19200300.100.1.3' => ['user@example.com'],
        ];

        $result = $this->service->mapAttributes($idpAttributes, $mapping);

        $this->assertEquals('user@example.com', $result['email']);
    }

    public function test_toggle_provider(): void
    {
        $provider = $this->createSamlProvider();

        $this->service->toggleProvider($provider, false);
        $this->assertFalse($provider->fresh()->is_active);

        $this->service->toggleProvider($provider, true);
        $this->assertTrue($provider->fresh()->is_active);
    }

    // ─── Helper ───

    protected function createSamlProvider(): SsoProvider
    {
        return $this->service->configureProvider(
            $this->tenant->id,
            'Test SAML',
            'saml2',
            [
                'idp_entity_id' => 'https://test-saml.com/idp',
                'idp_login_url' => 'https://test-saml.com/sso',
            ],
        );
    }
}
