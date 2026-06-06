<?php

namespace App\Http\Middleware;

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
 * - CORS 跨域头
 * - CSP 内容安全策略
 * - HSTS/HPKP 等安全头
 */
class SecurityHeadersMiddleware
{
    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            return $response;
        }

        // ─── CORS 头（应用层统一处理） ───
        $origin = $request->header('Origin', '*');
        $allowedOrigins = config('cors.allowed_origins', ['*']);

        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Api-Key, X-License-Key, X-Tenant-Id, X-Idempotency-Key, X-Nonce, X-Signature');
        $response->headers->set('Access-Control-Expose-Headers', 'X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, X-Request-Id');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');

        // ─── 安全头 ───
        // HSTS (仅 HTTPS)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // CSP 内容安全策略
        $csp = config('security.csp_policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' *; frame-ancestors 'none'; base-uri 'self'");
        $response->headers->set('Content-Security-Policy', $csp);

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
