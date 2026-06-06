<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Services\CircuitBreakerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 服务熔断降级中间件
 *
 * 功能：
 * - Redis 不可用 → 降级到 DB（业务逻辑继续处理）
 * - 关键服务熔断 → 返回友好的熔断响应
 * - 全部不可用 → 紧急熔断（503 Service Unavailable）
 *
 * 使用方式：
 *   Route::middleware('circuit-breaker')->post('/api/license/activate', ...);
 *   Route::middleware('circuit-breaker:redis')->get('/api/features', ...); // 仅检查 Redis
 *   Route::middleware('circuit-breaker:db')->get('/api/reports', ...);     // 仅检查 DB
 */
class CircuitBreakerMiddleware
{
    public function handle(Request $request, Closure $next, string $service = 'all'): Response
    {
        $breaker = app(CircuitBreakerService::class);

        // 检查 Redis 是否可用（如果需要）
        if ($service === 'all' || $service === 'redis') {
            $redisAvailable = $breaker->isRedisAvailable();
            if (! $redisAvailable) {
                // Redis 不可用—记录但不阻断（业务可降级到 DB）
                $request->attributes->set('circuit_breaker.redis_degraded', true);
            }
        }

        // 检查 DB 是否可用
        if ($service === 'all' || $service === 'db' || $service === 'database') {
            $dbAvailable = $breaker->isDatabaseAvailable();
            if (! $dbAvailable) {
                $breaker->recordFailure('db');
                return ApiResponse::error(
                    'SERVICE_UNAVAILABLE',
                    '服务暂时不可用，请稍后再试',
                    503,
                    ['retry_after_seconds' => 30],
                );
            }
        }

        // 自定义服务熔断检查
        if (! in_array($service, ['all', 'redis', 'db', 'database'])) {
            $available = $breaker->checkCustomService($service);

            if (! $available) {
                // 尝试半开恢复
                if ($breaker->attemptReset($service)) {
                    $breaker->recordHalfOpenRequest($service);
                } else {
                    return ApiResponse::error(
                        'CIRCUIT_OPEN',
                        "服务 [{$service}] 暂时不可用，请稍后再试",
                        503,
                        ['retry_after_seconds' => CircuitBreakerService::DEFAULT_RESET_TIMEOUT],
                    );
                }
            }
        }

        // 处理请求
        $response = $next($request);

        // 标记 Redis 降级
        if ($request->attributes->get('circuit_breaker.redis_degraded')) {
            if ($response instanceof Response) {
                $response->headers->set('X-Redis-Degraded', 'true');
            }
            $breaker->recordSuccess('redis'); // 成功降级也算成功
        }

        return $response;
    }
}
