<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 基于 Redis 的速率限制中间件（应用层——业务限流）
 *
 * 注意：按 M0-11 ADR，此中间件仅处理按租户/API 分级的业务限流。
 * 全局限流（IP 黑名单/CC 防护/硬限制）由网关层 Kong/APISIX 负责。
 * 两层级不冲突，共同作用：
 * - 网关层：硬限制（防止 DDoS/IP 滥用）
 * - 应用层：软限制（按业务规则精细化限流）
 *
 * 支持三种限流模式：
 * 1. IP 限流（按客户端 IP）
 * 2. License Key 限流（按 License）
 * 3. 全局限流（按路径）
 *
 * 使用滑动窗口算法，基于 Redis 实现精确限流。
 * 响应头中会返回限流信息：
 * - X-RateLimit-Limit: 窗口内最大请求数
 * - X-RateLimit-Remaining: 剩余请求数
 * - X-RateLimit-Reset: 窗口重置时间戳
 */
class RateLimitMiddleware
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'ratelimit:';

    /**
     * 默认配置
     */
    const DEFAULT_CONFIG = [
        'max_attempts' => 60,
        'window_seconds' => 60,
        'key_type' => 'ip', // ip | license | global
    ];

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $maxAttempts 最大请求数
     * @param string|null $windowSeconds 时间窗口（秒）
     * @param string|null $keyType 限流键类型
     * @return Response
     */
    public function handle(
        Request  $request,
        Closure  $next,
        ?string  $maxAttempts = null,
        ?string  $windowSeconds = null,
        ?string  $keyType = null,
    ): Response {
        $config = [
            'max_attempts' => (int) ($maxAttempts ?? self::DEFAULT_CONFIG['max_attempts']),
            'window_seconds' => (int) ($windowSeconds ?? self::DEFAULT_CONFIG['window_seconds']),
            'key_type' => $keyType ?? self::DEFAULT_CONFIG['key_type'],
        ];

        // 构建限流键
        $rateLimitKey = $this->buildRateLimitKey($request, $config);

        if ($rateLimitKey === null) {
            return $next($request);
        }

        // 滑动窗口计数器
        $result = $this->checkRateLimit($rateLimitKey, $config);

        // 添加响应头
        $response = $result['allowed']
            ? $next($request)
            : $this->rateLimitExceededResponse($rateLimitKey, $config, $result);

        if ($response instanceof Response) {
            $response->headers->set('X-RateLimit-Limit', (string) $config['max_attempts']);
            $response->headers->set('X-RateLimit-Remaining', (string) $result['remaining']);
            $response->headers->set('X-RateLimit-Reset', (string) $result['reset_time']);
        }

        return $response;
    }

    /**
     * 构建限流键
     */
    protected function buildRateLimitKey(Request $request, array $config): ?string
    {
        return match ($config['key_type']) {
            'ip' => self::CACHE_PREFIX . 'ip:' . ($request->ip() ?? 'unknown'),
            'license' => self::CACHE_PREFIX . 'license:' . ($request->input('license_key') ?? $request->header('X-License-Key', 'unknown')),
            'global' => self::CACHE_PREFIX . 'path:' . str_replace('/', '_', trim($request->getPathInfo(), '/')),
            default => null,
        };
    }

    /**
     * 检查速率限制（滑动窗口算法）
     *
     * Redis 实现使用有序集合（sorted set）：
     * - member: 请求时间戳
     * - score: 请求时间戳
     * - 窗口内的请求数 = ZCOUNT(key, now - window, now)
     */
    protected function checkRateLimit(string $key, array $config): array
    {
        $now = microtime(true);
        $windowStart = $now - $config['window_seconds'];

        // 使用 Redis 有序集合实现滑动窗口
        $requests = $this->getWindowRequests($key, $windowStart, $now);

        // 清理过期数据（概率性执行）
        if (random_int(1, 100) <= 10) {
            $this->cleanExpiredEntries($key, $windowStart);
        }

        $allowed = $requests < $config['max_attempts'];

        // 记录当前请求
        if ($allowed) {
            $this->recordRequest($key, $now, $config['window_seconds']);
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $config['max_attempts'] - $requests - ($allowed ? 1 : 0)),
            'reset_time' => (int) ($now + $config['window_seconds']),
            'current_count' => $requests + ($allowed ? 1 : 0),
        ];
    }

    /**
     * 获取窗口内的请求数
     */
    protected function getWindowRequests(string $key, float $windowStart, float $now): int
    {
        $requests = Cache::get($key, []);
        if (! is_array($requests)) {
            return 0;
        }

        // 移除窗口外的记录
        $requests = array_filter($requests, fn($timestamp) => $timestamp >= $windowStart);

        return count($requests);
    }

    /**
     * 记录请求
     */
    protected function recordRequest(string $key, float $now, int $ttl): void
    {
        $requests = Cache::get($key, []);
        if (! is_array($requests)) {
            $requests = [];
        }

        $requests[] = $now;

        // 保留窗口内的记录 + 额外缓冲
        $windowStart = $now - $ttl;
        $requests = array_values(array_filter($requests, fn($ts) => $ts >= $windowStart));

        Cache::put($key, $requests, $ttl * 2);
    }

    /**
     * 清理过期数据
     */
    protected function cleanExpiredEntries(string $key, float $windowStart): void
    {
        $requests = Cache::get($key, []);
        if (is_array($requests)) {
            $requests = array_values(array_filter($requests, fn($ts) => $ts >= $windowStart));
            Cache::put($key, $requests, 300);
        }
    }

    /**
     * 限流响应
     */
    protected function rateLimitExceededResponse(string $key, array $config, array $result): Response
    {
        Log::warning('速率限制触发', [
            'key' => $key,
            'max_attempts' => $config['max_attempts'],
            'window_seconds' => $config['window_seconds'],
            'current_count' => $result['current_count'],
        ]);

        return ApiResponse::error(
            'RATE_LIMIT_EXCEEDED',
            __('app.middleware.too_many_requests'),
            429,
            [
                'max_attempts' => $config['max_attempts'],
                'window_seconds' => $config['window_seconds'],
                'retry_after_seconds' => max(1, (int) ($result['reset_time'] - time())),
            ],
        );
    }
}
