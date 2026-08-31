<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SSO 租户路由与多租户选择 API
 *
 * 按 M1.4-56，支持：
 * - SSO 登录后多租户用户展示租户列表选择页
 * - 记住上次选择的租户
 * - 管理后台内租户切换
 */
class TenantRouterController extends Controller
{
    /**
     * 获取当前用户的租户列表
     *
     * GET /api/tenants
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tenants = $user->tenants()->get(['tenants.id', 'tenants.name', 'tenants.slug', 'tenants.logo']);

        $currentTenantId = $user->remember_tenant_id ?? $user->tenant_id;

        return ApiResponse::success([
            'tenants' => $tenants,
            'current_tenant_id' => $currentTenantId,
            'is_multi_tenant' => $tenants->count() > 1,
        ]);
    }

    /**
     * 切换活跃租户
     *
     * POST /api/tenants/switch
     * Body: { tenant_id: int }
     */
    public function switch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $user = $request->user();
        $tenantId = $data['tenant_id'];

        // 验证用户是否有权访问该租户
        $hasAccess = $user->tenants()->where('tenants.id', $tenantId)->exists();

        if (! $hasAccess && ! $user->hasRole('super-admin')) {
            return ApiResponse::forbidden(__("app.tenant_router.msg_d8d1234a"));
        }

        // 记住选择的租户
        $user->update(['remember_tenant_id' => $tenantId]);

        // 获取切换后的租户信息
        $tenant = Tenant::find($tenantId);

        return ApiResponse::success([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo' => $tenant->logo,
            ],
            'active_tenant_id' => $tenantId,
        ], __('app.common.tenant_switched', ['name' => $tenant->name]));
    }

    /**
     * 获取 SSO 登录后的租户选择信息
     * 用于 SSO 回调后判断是否需要展示租户选择页
     *
     * GET /api/tenants/sso-info
     */
    public function ssoInfo(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenants = $user->tenants()->get(['tenants.id', 'tenants.name', 'tenants.slug', 'tenants.logo']);

        // 单租户用户自动跳转
        if ($tenants->count() === 1) {
            $tenant = $tenants->first();
            // 确保 tenant_id 正确设置
            $user->update(['remember_tenant_id' => $tenant->id]);

            return ApiResponse::success([
                'requires_selection' => false,
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
            ]);
        }

        // 多租户用户需要选择
        if ($tenants->count() > 1) {
            // 如果有记住的租户，直接使用
            if ($user->remember_tenant_id) {
                $remembered = $tenants->firstWhere('id', $user->remember_tenant_id);
                if ($remembered) {
                    return ApiResponse::success([
                        'requires_selection' => false,
                        'tenant' => [
                            'id' => $remembered->id,
                            'name' => $remembered->name,
                            'slug' => $remembered->slug,
                        ],
                    ]);
                }
            }

            return ApiResponse::success([
                'requires_selection' => true,
                'tenants' => $tenants,
            ]);
        }

        // 无租户（极少情况）
        return ApiResponse::success([
            'requires_selection' => false,
            'tenant' => null,
        ]);
    }
}
