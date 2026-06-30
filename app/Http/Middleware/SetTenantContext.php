<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            // 优先使用用户记住的租户（多租户切换），其次是绑定的 tenant_id
            $tenantId = $user->remember_tenant_id ?? $user->tenant_id;

            // 先设置 Spatie team ID，否则后面 hasRole 查询会因 team 作用域查不到角色
            if ($tenantId) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
            }

            // 超级管理员可通过 X-Tenant-Id 头临时切换租户上下文
            if ($tenantId && $request->header('X-Tenant-Id')) {
                if ($user->hasRole('super-admin')) {
                    $tenantId = $request->header('X-Tenant-Id');
                    app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
                }
            }

            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    app()->instance(Tenant::class, $tenant);
                    $request->merge(['_tenant' => $tenant]);
                }
            }
        }

        return $next($request);
    }
}
