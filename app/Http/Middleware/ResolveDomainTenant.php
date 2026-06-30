<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 域名 → 租户解析中间件
 *
 * 根据请求的 Host 头自动识别对应的自定义域名，
 * 如果匹配则将 Tenant 实例注入服务容器，
 * 同时对已验证/活跃的域名自动设置租户上下文。
 *
 * 优先级：
 * 1. 如果匹配自定义域名 → 设置对应租户
 * 2. 已认证用户的租户上下文保留
 * 3. X-Tenant-Id 头（super-admin）覆盖
 */
class ResolveDomainTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // 测试环境下跳过域名解析
        if (app()->environment('testing')) {
            return $next($request);
        }

        // 本地开发 IP 直接放行（127.0.0.1 / localhost / ::1）
        $host = $request->getHost();
        $localHosts = ['127.0.0.1', 'localhost', '::1'];
        if (in_array($host, $localHosts)) {
            return $next($request);
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        // 主域名不做处理，保留默认逻辑
        if ($host === $appHost) {
            return $next($request);
        }

        // 尝试通过自定义域名匹配租户
        $customDomain = CustomDomain::where('domain', $host)
            ->where('is_active', true)
            ->where('verified', true)
            ->first();

        if ($customDomain) {
            $tenant = $customDomain->tenant;

            if ($tenant) {
                // 将租户注入容器，后续认证/路由可依赖
                app()->instance(Tenant::class, $tenant);
                $request->merge(['_tenant' => $tenant, '_custom_domain' => $customDomain]);
                $request->attributes->set('_tenant', $tenant);
                $request->attributes->set('_custom_domain', $customDomain);

                return $next($request);
            }
        }

        // 未匹配的自定义域名请求 — 返回友好提示
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => '该域名未绑定到任何有效的租户',
            ], 404);
        }

        // 非 API 请求照常流转（可能用于门户页），但注入占位标记
        $request->attributes->set('_unresolved_domain', $host);

        return $next($request);
    }
}
