<?php

namespace App\Http\Middleware;

use App\Services\CorsManagerService;
use App\Services\CspManagerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        // ─── OPTIONS 预检请求：直接返回 CORS 头 + 204 ───
        if ($request->isMethod('OPTIONS')) {
            $response = new Response('', 204);
        } else {
            $response = $next($request);
        }

        if (! $response instanceof Response) {
            return $response;
        }

        // ─── CORS 头（通过 CorsManagerService 从数据库读取） ───
        $corsHeaders = $this->corsManager->buildHeaders($request);
        foreach ($corsHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Vary: Origin — 当 ACAC 为特定 origin 时，CDN 需要此头
        $origin = $request->header('Origin');
        if ($origin && $response->headers->get('Access-Control-Allow-Origin') !== '*') {
            $response->headers->set('Vary', 'Origin');
        }

        // ─── CSP（通过 CspManagerService 从数据库读取） ───
        $cspHeaders = $this->cspManager->buildHeaders($request);
        foreach ($cspHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        // ─── 动态安全响应头（从缓存读取配置，支持后台管理） ───
        $config = \Illuminate\Support\Facades\Cache::get(\App\Http\Controllers\Api\SecurityHeadersController::CACHE_KEY);
        if ($config) {
            if ($config['hsts'] ?? true) {
                $hstsValue = "max-age={$config['hsts_max_age']}";
                if ($config['hsts_include_subdomains'] ?? true) {
                    $hstsValue .= '; includeSubDomains';
                }
                $response->headers->set('Strict-Transport-Security', $hstsValue);
            }
            if (($config['x_frame_options'] ?? 'DENY') !== 'off') {
                $value = $config['x_frame_options'];
                if ($value === 'ALLOW-FROM' && !empty($config['x_frame_options_origin'])) {
                    $value .= " {$config['x_frame_options_origin']}";
                }
                $response->headers->set('X-Frame-Options', $value);
            } else {
                $response->headers->remove('X-Frame-Options');
            }
            if (($config['x_content_type_options'] ?? 'nosniff') !== 'off') {
                $response->headers->set('X-Content-Type-Options', $config['x_content_type_options']);
            }
            if (($config['referrer_policy'] ?? 'strict-origin-when-cross-origin') !== 'off') {
                $response->headers->set('Referrer-Policy', $config['referrer_policy']);
            }
            if ($config['permissions_policy_enabled'] ?? true) {
                $response->headers->set('Permissions-Policy', $config['permissions_policy'] ?? 'camera=(), microphone=(), geolocation=(), payment=()');
            }
            if (($config['x_xss_protection'] ?? '1; mode=block') !== 'off') {
                $response->headers->set('X-XSS-Protection', $config['x_xss_protection']);
            }
            if ($config['cache_control_enabled'] ?? true) {
                if (! $response->headers->has('Cache-Control')) {
                    $response->headers->set('Cache-Control', $config['cache_control'] ?? 'no-store, no-cache, must-revalidate');
                }
            }
        }

        // X-Request-Id — 链路追踪
        if (! $response->headers->has('X-Request-Id')) {
            $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
    }
}
