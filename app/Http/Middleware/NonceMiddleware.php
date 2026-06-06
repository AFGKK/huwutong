<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nonce + Timestamp 防重放攻击中间件
 *
 * 客户端需在请求头中携带：
 * - X-Nonce: 一次性随机字符串（UUID v4）
 * - X-Timestamp: Unix 时间戳（秒）
 *
 * 校验逻辑：
 * 1. Timestamp 必须在允许的时间窗口内（默认 ±60 秒）
 * 2. Nonce 在 Redis 中检查是否已使用（防止重放）
 * 3. Nonce 使用后写入 Redis（TTL = 时间窗口 * 2）
 *
 * 适用于敏感操作（激活、验证、支付回调等）
 */
class NonceMiddleware
{
    /**
     * 默认时间窗口（秒）
     */
    const DEFAULT_WINDOW = 60;

    /**
     * 最大时间窗口（秒）— 防止客户端时间偏差过大
     */
    const MAX_WINDOW = 300;

    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'nonce:';

    public function __construct(
        protected ?int $timeWindow = null,
    ) {
        $this->timeWindow ??= config('security.nonce_window', self::DEFAULT_WINDOW);
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $window 可选：自定义时间窗口（秒）
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?string $window = null): Response
    {
        // 仅对 POST/PUT/PATCH/DELETE 生效
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $nonce = $request->header('X-Nonce');
        $timestamp = $request->header('X-Timestamp');

        // 1. 必填校验
        if (empty($nonce) || empty($timestamp)) {
            return ApiResponse::error(
                'MISSING_NONCE_OR_TIMESTAMP',
                '缺少防重放参数',
                400,
                ['required_headers' => ['X-Nonce', 'X-Timestamp']],
            );
        }

        // 2. Timestamp 格式校验
        if (! ctype_digit((string) $timestamp) || (int) $timestamp <= 0) {
            return ApiResponse::error(
                'INVALID_TIMESTAMP',
                'Timestamp 格式无效',
                400,
            );
        }

        $now = time();
        $ts = (int) $timestamp;
        $window = (int) ($window ?? $this->timeWindow);

        // 3. Timestamp 范围校验
        if (abs($now - $ts) > min($window, self::MAX_WINDOW)) {
            Log::warning('Nonce 防重放: Timestamp 超出时间窗口', [
                'timestamp' => $ts,
                'server_time' => $now,
                'window' => $window,
                'client_ip' => $request->ip(),
            ]);

            return ApiResponse::error(
                'TIMESTAMP_OUT_OF_WINDOW',
                '请求已过期，请检查客户端时间',
                400,
                ['server_time' => $now, 'allowed_window_seconds' => $window],
            );
        }

        // 4. Nonce 格式校验（UUID v4 格式）
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $nonce)) {
            return ApiResponse::error(
                'INVALID_NONCE',
                'Nonce 格式无效，需为 UUID v4',
                400,
            );
        }

        // 5. 检查 Nonce 是否已使用（防重放）
        $cacheKey = self::CACHE_PREFIX . $nonce;
        if (Cache::has($cacheKey)) {
            Log::warning('Nonce 防重放: 检测到重复 Nonce', [
                'nonce' => $nonce,
                'client_ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return ApiResponse::error(
                'NONCE_ALREADY_USED',
                '请求已被处理，请勿重复提交',
                409,
            );
        }

        // 6. 记录 Nonce（TTL = 时间窗口 * 2，至少 120 秒）
        $ttl = max(min($window * 2, self::MAX_WINDOW * 2), 120);
        Cache::put($cacheKey, true, $ttl);

        return $next($request);
    }
}
