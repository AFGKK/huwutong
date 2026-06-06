<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            // 优先使用用户记住的租户（多租户切换），其次是绑定的 tenant_id
            $tenantId = $user->remember_tenant_id ?? $user->tenant_id;

            // 如果有租户头信息，允许临时切换租户上下文（超级管理员）
            if ($user->hasRole('super-admin') && $request->header('X-Tenant-Id')) {
                $tenantId = $request->header('X-Tenant-Id');
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
