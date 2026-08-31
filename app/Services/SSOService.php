<?php

namespace App\Services;

use App\Models\SsoConnection;
use App\Models\SsoProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SSO 单点登录核心服务
 *
 * 支持协议：SAML 2.0, OIDC, OAuth 2.0
 * 支持的 IdP：Okta, Azure AD, 飞书, Google Workspace, CAS, 通用 SAML/OIDC
 *
 * 特性：
 * - IdP 属性 → tenant_id 映射（按 SAML Attribute / OIDC Claim 自动关联租户）
 * - 多租户用户首次 SSO 登录后进入租户选择页
 * - 自动创建本地用户（首次登录）
 * - 已有 SSO 绑定的自动登录
 */
class SSOService
{
    /**
     * 支持的提供者类型
     */
    const SUPPORTED_TYPES = ['saml2', 'oidc', 'oauth2'];

    /**
     * 默认属性映射（IdP 属性名 → 系统字段名）
     */
    const DEFAULT_ATTRIBUTE_MAPPING = [
        'email' => 'email',
        'name' => 'name',
        'phone' => 'phone',
    ];

    /**
     * 获取租户下激活的 SSO 提供者列表
     */
    public function getActiveProviders(int $tenantId): array
    {
        return SsoProvider::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->toArray();
    }

    /**
     * 配置 SSO 提供者
     */
    public function configureProvider(
        int     $tenantId,
        string  $name,
        string  $providerType,
        array   $config,
        ?string $spEntityId = null,
        ?string $spAcsUrl = null,
        ?array  $attributeMapping = null,
    ): SsoProvider {
        if (! in_array($providerType, self::SUPPORTED_TYPES)) {
            throw ValidationException::withMessages([
                'provider_type' => '不支持的 SSO 类型，支持: ' . implode(', ', self::SUPPORTED_TYPES),
            ]);
        }

        $data = [
            'tenant_id' => $tenantId,
            'name' => $name,
            'provider_type' => $providerType,
            'is_active' => true,
            'attribute_mapping' => $attributeMapping ?? self::DEFAULT_ATTRIBUTE_MAPPING,
        ];

        // 根据类型填充配置
        match ($providerType) {
            'saml2' => $data += [
                'idp_entity_id' => $config['idp_entity_id'] ?? null,
                'idp_login_url' => $config['idp_login_url'] ?? null,
                'idp_logout_url' => $config['idp_logout_url'] ?? null,
                'idp_x509_certificate' => $config['idp_x509_certificate'] ?? null,
                'sp_entity_id' => $spEntityId,
                'sp_acs_url' => $spAcsUrl,
            ],
            'oidc', 'oauth2' => $data += [
                'client_id' => $config['client_id'] ?? null,
                'client_secret' => $config['client_secret'] ?? null,
                'authorization_url' => $config['authorization_url'] ?? null,
                'token_url' => $config['token_url'] ?? null,
                'userinfo_url' => $config['userinfo_url'] ?? null,
                'jwks_url' => $config['jwks_url'] ?? null,
                'scopes' => $config['scopes'] ?? 'openid email profile',
            ],
        };

        return SsoProvider::updateOrCreate(
            ['tenant_id' => $tenantId, 'provider_type' => $providerType],
            $data,
        );
    }

    /**
     * 处理 SSO 登录回调
     *
     * @param SsoProvider $provider  SSO 提供者
     * @param string      $externalId  IdP 中的用户唯一标识
     * @param array       $attributes  IdP 返回的属性
     * @return User 已登录/已创建的用户
     */
    public function handleCallback(SsoProvider $provider, string $externalId, array $attributes): User
    {
        return DB::transaction(function () use ($provider, $externalId, $attributes) {
            // 1. 查找是否已有 SSO 连接记录
            $connection = SsoConnection::where('sso_provider_id', $provider->id)
                ->where('external_id', $externalId)
                ->first();

            if ($connection) {
                // 已有连接 → 更新信息并返回关联用户
                $connection->update([
                    'external_email' => $attributes['email'] ?? $connection->external_email,
                    'external_name' => $attributes['name'] ?? $connection->external_name,
                    'raw_attributes' => $attributes,
                    'last_login_at' => now(),
                ]);

                $connection->user->update([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ]);

                return $connection->user;
            }

            // 2. 新 SSO 用户 → 按属性映射解析系统字段
            $mapping = $provider->attribute_mapping ?? self::DEFAULT_ATTRIBUTE_MAPPING;
            $userData = $this->mapAttributes($attributes, $mapping);

            // 3. 按邮箱查找是否已有本地用户
            $email = $userData['email'] ?? null;
            $user = null;

            if ($email) {
                $user = User::where('email', $email)->first();
            }

            if (! $user) {
                // 4. 创建新用户 — 放入 IdP 属性映射的租户
                $user = User::create([
                    'tenant_id' => $provider->tenant_id,
                    'name' => $userData['name'] ?? $externalId,
                    'email' => $email,
                    'phone' => $userData['phone'] ?? null,
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'active',
                    'remember_tenant_id' => $provider->tenant_id,
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ]);

                // 自动加入租户成员
                if (! $user->tenants()->where('tenant_id', $provider->tenant_id)->exists()) {
                    \App\Models\TenantMember::create([
                        'tenant_id' => $provider->tenant_id,
                        'user_id' => $user->id,
                        'role' => 'member',
                        'invited_by' => null,
                        'status' => 'active',
                    ]);
                }
            } else {
                // 更新现有用户
                $user->update([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ]);

                // 确保用户也加入了该租户（SSO 属性映射的租户）
                if ($provider->tenant_id && ! $user->tenants()->where('tenant_id', $provider->tenant_id)->exists()) {
                    \App\Models\TenantMember::create([
                        'tenant_id' => $provider->tenant_id,
                        'user_id' => $user->id,
                        'role' => 'member',
                        'invited_by' => null,
                        'status' => 'active',
                    ]);
                }
            }

            // 5. 记录 SSO 连接
            SsoConnection::create([
                'user_id' => $user->id,
                'sso_provider_id' => $provider->id,
                'external_id' => $externalId,
                'external_email' => $attributes['email'] ?? null,
                'external_name' => $attributes['name'] ?? null,
                'raw_attributes' => $attributes,
                'last_login_at' => now(),
            ]);

            Log::info('SSO 新用户登录', [
                'user_id' => $user->id,
                'provider_id' => $provider->id,
                'provider_type' => $provider->provider_type,
                'external_id' => $externalId,
            ]);

            return $user;
        });
    }

    /**
     * 根据属性映射解析 IdP 属性为系统字段
     */
    public function mapAttributes(array $attributes, array $mapping): array
    {
        $result = [];

        foreach ($mapping as $idpField => $systemField) {
            // 支持层级属性（如 attributes.name[0]）
            $value = $attributes[$idpField] ?? null;

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if ($value !== null && $value !== '') {
                $result[$systemField] = $value;
            }
        }

        return $result;
    }

    /**
     * 生成 SP 元数据 URL（用于 SAML 配置）
     */
    public function getSpMetadataUrl(SsoProvider $provider): string
    {
        return route('sso.metadata', ['provider' => $provider->id]);
    }

    /**
     * 生成 SAML 登录请求 URL
     */
    public function buildLoginUrl(SsoProvider $provider, ?string $redirectTo = null): string
    {
        // 根据类型构建登录 URL
        return match ($provider->provider_type) {
            'saml2' => $this->buildSamlLoginUrl($provider, $redirectTo),
            'oidc', 'oauth2' => $this->buildOidcLoginUrl($provider, $redirectTo),
            default => throw new \RuntimeException(__("app.sso.msg_6394ba26")),
        };
    }

    /**
     * 构建 SAML 登录 URL（模拟 Step-up 重定向）
     */
    protected function buildSamlLoginUrl(SsoProvider $provider, ?string $redirectTo = null): string
    {
        // 实际 SAML 登录需要外部库如 lightSAML/SP 生成 AuthnRequest
        // 这里生成一个重定向到服务提供者 ACS 端点的 URL
        $params = http_build_query([
            'provider' => $provider->id,
            'redirect_to' => $redirectTo,
        ]);

        return route('sso.login') . '?' . $params;
    }

    /**
     * 构建 OIDC/OAuth 登录 URL
     */
    protected function buildOidcLoginUrl(SsoProvider $provider, ?string $redirectTo = null): string
    {
        $state = [
            'provider_id' => $provider->id,
            'redirect_to' => $redirectTo,
            'nonce' => Str::random(32),
        ];

        // 存入 session 用于回调时校验
        session(['sso_state_' . $provider->id => $state]);

        $params = http_build_query([
            'client_id' => $provider->client_id,
            'redirect_uri' => route('sso.callback', ['provider' => $provider->id]),
            'response_type' => 'code',
            'scope' => $provider->scopes ?? 'openid email profile',
            'state' => json_encode($state),
        ]);

        $baseUrl = $provider->authorization_url;
        if (! $baseUrl) {
            throw new \RuntimeException(__("app.sso.msg_4b1e9980"));
        }

        return $baseUrl . '?' . $params;
    }

    /**
     * 停用/启用 SSO 提供者
     */
    public function toggleProvider(SsoProvider $provider, bool $isActive): SsoProvider
    {
        $provider->update(['is_active' => $isActive]);
        return $provider->fresh();
    }
}
