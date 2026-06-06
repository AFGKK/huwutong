<?php

namespace App\Http\Middleware;

use App\Services\CorsManagerService;
use App\Services\CspManagerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 安全响应头中间件
 *
 * 按 M0-11 ADR，安全响应头/CORS/CSP 统一由应用层处理。
 * 网关层不再设置这些响应头，避免冲突和重复。
 *
 * 职责：
 * - CORS 跨域头（通过 CorsManagerService 从数据库读取配置）
 * - CSP 内容安全策略（通过 CspManagerService 从数据库读取配置）
 * - HSTS/HPKP 等安全头
 */
class SecurityHeadersMiddleware
{
    protected CorsManagerService $corsManager;
    protected CspManagerService $cspManager;

    public function __construct(CorsManagerService $corsManager, CspManagerService $cspManager)
    {
        $this->corsManager = $corsManager;
        $this->cspManager = $cspManager;
    }

    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        // ─── CORS 头（通过 CorsManagerService 从数据库读取） ───
        $corsHeaders = $this->corsManager->buildHeaders($request);
        foreach ($corsHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        // ─── CSP（通过 CspManagerService 从数据库读取） ───
        $cspHeaders = $this->cspManager->buildHeaders($request);
        foreach ($cspHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        // X-Frame-Options — 禁止 iframe 嵌套（防点击劫持）
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options — 禁止 MIME 嗅探
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer-Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy — 限制浏览器 API 权限
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // X-XSS-Protection (已废弃但保留兼容性)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 缓存控制 — API 响应默认不缓存
        if (! $response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        // X-Request-Id — 链路追踪
        if (! $response->headers->has('X-Request-Id')) {
            $requestId = $request->header('X-Request-Id') ?: str_replace('-', '', (string) \Illuminate\Support\Str::uuid());
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
    }
}
