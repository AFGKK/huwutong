<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Services\BruteForceGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 暴力枚举实时阻断中间件
 *
 * 检测连续无效 License Key 尝试，自动临时封禁 IP
 *
 * 使用方式：
 *   // 默认配置（5次无效→15分钟封禁）
 *   Route::middleware('brute-force')->post('/api/license/activate', ...);
 *
 *   // 自定义阈值
 *   Route::middleware('brute-force:10')->post('/api/license/activate', ...);
 */
class BruteForceMiddleware
{
    public function handle(Request $request, Closure $next, ?string $threshold = null): Response
    {
        $ip = $request->ip() ?? 'unknown';
        $licenseKey = $request->input('license_key') ?? $request->header('X-License-Key', '');

        $guard = app(BruteForceGuard::class);

        // 检查 IP 是否已被封禁
        if ($guard->isIpBanned($ip)) {
            $remaining = $guard->getBanRemainingTtl($ip);
            return ApiResponse::error(
                'IP_BANNED',
                __('app.middleware.ip_temp_banned'),
                429,
                [
                    'retry_after_seconds' => $remaining,
                ],
            );
        }

        $response = $next($request);

        // 如果是无效 License Key 的响应（404 或特定错误码），记录尝试
        if ($response instanceof Response && $response->getStatusCode() === 404) {
            $body = json_decode($response->getContent(), true);
            $errorCode = $body['error']['code'] ?? '';

            if (in_array($errorCode, ['LICENSE_NOT_FOUND', 'INVALID_LICENSE_KEY', 'LICENSE_KEY_INVALID'])) {
                $guard->recordInvalidAttempt(
                    $ip,
                    $licenseKey,
                    "HTTP {$response->getStatusCode()} - {$errorCode}",
                );
            }
        }

        return $response;
    }
}
