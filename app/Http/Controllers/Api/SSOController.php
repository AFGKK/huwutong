<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SsoProvider;
use App\Models\User;
use App\Services\SSOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SSOController extends Controller
{
    public function __construct(
        protected SSOService $ssoService,
    ) {}

    /**
     * 获取租户 SSO 配置列表
     *
     * GET /api/sso/providers
     */
    public function providers(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $providers = SsoProvider::where('tenant_id', $tenantId)->get();

        return ApiResponse::success($providers->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'provider_type' => $p->provider_type,
            'is_active' => $p->is_active,
        ]));
    }

    /**
     * 配置/更新 SSO 提供者
     *
     * POST /api/sso/providers
     */
    public function configure(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'provider_type' => 'required|string|in:saml2,oidc,oauth2',
            'config' => 'required|array',
            'config.idp_entity_id' => 'required_if:provider_type,saml2|nullable|string',
            'config.idp_login_url' => 'required_if:provider_type,saml2|nullable|string',
            'config.idp_x509_certificate' => 'nullable|string',
            'config.client_id' => 'required_if:provider_type,oidc,oauth2|nullable|string',
            'config.client_secret' => 'nullable|string',
            'config.authorization_url' => 'required_if:provider_type,oidc,oauth2|nullable|string',
            'config.token_url' => 'required_if:provider_type,oidc,oauth2|nullable|string',
            'config.userinfo_url' => 'nullable|string',
            'config.jwks_url' => 'nullable|string',
            'config.scopes' => 'nullable|string',
            'attribute_mapping' => 'nullable|array',
            'sp_entity_id' => 'nullable|string',
            'sp_acs_url' => 'nullable|url',
        ]);

        $provider = $this->ssoService->configureProvider(
            $request->user()->tenant_id,
            $data['name'],
            $data['provider_type'],
            $data['config'],
            $data['sp_entity_id'] ?? null,
            $data['sp_acs_url'] ?? null,
            $data['attribute_mapping'] ?? null,
        );

        return ApiResponse::success($provider, 'SSO 配置已保存');
    }

    /**
     * 开启/关闭 SSO 提供者
     *
     * POST /api/sso/providers/{provider}/toggle
     */
    public function toggle(int $providerId, Request $request): JsonResponse
    {
        $provider = SsoProvider::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($providerId);

        $this->ssoService->toggleProvider($provider, $request->input('is_active', true));

        return ApiResponse::success(
            ['is_active' => $provider->fresh()->is_active],
            $provider->is_active ? 'SSO 已启用' : 'SSO 已停用',
        );
    }

    /**
     * SSO 回调处理（统一入口）
     *
     * POST /api/sso/callback
     * Body: { provider_id, external_id, attributes: {...} }
     */
    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => 'required|integer|exists:sso_providers,id',
            'external_id' => 'required|string',
            'attributes' => 'nullable|array',
        ]);

        $provider = SsoProvider::findOrFail($data['provider_id']);

        if (! $provider->is_active) {
            return ApiResponse::error('SSO_PROVIDER_INACTIVE', 'SSO 提供者未启用', 422);
        }

        $attributes = $data['attributes'] ?? [];

        $user = $this->ssoService->handleCallback($provider, $data['external_id'], $attributes);

        // 生成 API Token
        $token = $user->createToken('sso-token', ['*'])->plainTextToken;

        return ApiResponse::success([
            'user' => $this->formatUserWithTenants($user),
            'token' => $token,
            'requires_tenant_selection' => $user->tenants()->count() > 1,
        ], 'SSO 登录成功');
    }

    /**
     * 获取用户绑定的 SSO 连接
     *
     * GET /api/sso/connections
     */
    public function connections(Request $request): JsonResponse
    {
        $connections = $request->user()->ssoConnections()
            ->with('provider')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'provider_name' => $c->provider->name,
                'provider_type' => $c->provider->provider_type,
                'external_email' => $c->external_email,
                'last_login_at' => $c->last_login_at,
                'created_at' => $c->created_at,
            ]);

        return ApiResponse::success($connections);
    }

    /**
     * 解除 SSO 绑定
     *
     * DELETE /api/sso/connections/{connection}
     */
    public function disconnect(int $connectionId, Request $request): JsonResponse
    {
        $connection = $request->user()->ssoConnections()
            ->findOrFail($connectionId);

        $connection->delete();

        return ApiResponse::success(null, 'SSO 绑定已解除');
    }

    /**
     * 获取 SSO 登录 URL
     *
     * GET /api/sso/{provider}/login-url
     */
    public function loginUrl(int $providerId): JsonResponse
    {
        $provider = SsoProvider::findOrFail($providerId);

        if (! $provider->is_active) {
            return ApiResponse::error('SSO_PROVIDER_INACTIVE', 'SSO 提供者未启用', 422);
        }

        $url = $this->ssoService->buildLoginUrl($provider);

        return ApiResponse::success([
            'login_url' => $url,
            'provider_name' => $provider->name,
            'provider_type' => $provider->provider_type,
        ]);
    }

    /**
     * 格式化用户数据（含多租户信息）
     */
    protected function formatUserWithTenants(User $user): array
    {
        $data = $user->toArray();
        $tenants = $user->tenants()->get(['tenants.id', 'tenants.name', 'tenants.slug', 'tenants.logo']);
        $data['tenants'] = $tenants;
        $data['is_multi_tenant'] = $tenants->count() > 1;
        $data['active_tenant_id'] = $user->remember_tenant_id ?? $user->tenant_id;
        return $data;
    }
}
