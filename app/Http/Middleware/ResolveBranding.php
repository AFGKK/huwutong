<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use App\Services\PortalBrandingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 品牌化解析中间件
 *
 * M3-47
 * 检测自定义域名→解析租户→注入品牌CSS变量到响应
 * 用于品牌化登录页和门户页面
 */
class ResolveBranding
{
    public function __construct(
        protected PortalBrandingService $brandingService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $tenantId = null;

        // 通过自定义域名解析租户
        $customDomain = CustomDomain::where('domain', $host)
            ->where('verified', true)
            ->where('is_active', true)
            ->first();

        if ($customDomain) {
            $tenantId = $customDomain->tenant_id;
        }

        // 提取品牌数据并注入到请求属性中
        if ($tenantId) {
            $branding = $this->brandingService->getBrandingData($tenantId);
            $request->attributes->set('branding_data', $branding['config']);
            $request->attributes->set('branding_css', $branding['css_variables']);

            // 设置租户上下文
            $request->attributes->set('resolved_tenant_id', $tenantId);
        } else {
            // 使用默认品牌
            $branding = $this->brandingService->getBrandingData(null);
            $request->attributes->set('branding_data', $branding['config']);
            $request->attributes->set('branding_css', $branding['css_variables'] ?? []);
        }

        return $next($request);
    }
}
