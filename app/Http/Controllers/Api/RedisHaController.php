<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\RedisHaService;
use Illuminate\Http\JsonResponse;

class RedisHaController extends Controller
{
    public function __construct(
        protected RedisHaService $redisHaService
    ) {}

    /**
     * 获取 Redis HA 综合状态
     * GET /api/v1/redis-ha/status
     */
    public function status(): JsonResponse
    {
        $status = $this->redisHaService->checkStatus();

        return ApiResponse::success($status, __('app.api.redis_ha.status_fetched'));
    }

    /**
     * 健康检查
     * GET /api/v1/redis-ha/health
     */
    public function health(): JsonResponse
    {
        $health = $this->redisHaService->healthCheck();

        return ApiResponse::success($health, __('app.api.redis_ha.health_check_done'));
    }

    /**
     * Sentinel 哨兵状态
     * GET /api/v1/redis-ha/sentinel
     */
    public function sentinel(): JsonResponse
    {
        $status = $this->redisHaService->sentinelStatus();

        return ApiResponse::success($status, __('app.api.redis_ha.sentinel_fetched'));
    }

    /**
     * 获取详细统计
     * GET /api/v1/redis-ha/stats
     */
    public function stats(): JsonResponse
    {
        $stats = $this->redisHaService->getStats();

        return ApiResponse::success($stats, __('app.api.redis_ha.stats_fetched'));
    }

    /**
     * 触发故障转移
     * POST /api/v1/redis-ha/failover
     */
    public function failover(): JsonResponse
    {
        $result = $this->redisHaService->triggerFailover();

        if (!($result['success'] ?? false)) {
            return ApiResponse::success($result, __('app.api.redis_ha.failover_failed'), false, 500);
        }

        return ApiResponse::success($result, __('app.api.redis_ha.failover_triggered'));
    }

    /**
     * 清除缓存
     * POST /api/v1/redis-ha/flush
     */
    public function flush(): JsonResponse
    {
        $result = $this->redisHaService->flushCache();

        if (!($result['success'] ?? false)) {
            return ApiResponse::success($result, __('app.api.redis_ha.cache_clear_failed'), false, 500);
        }

        return ApiResponse::success($result, __('app.api.redis_ha.cache_cleared'));
    }

    /**
     * 重置熔断器
     * POST /api/v1/redis-ha/reset-circuit-breaker
     */
    public function resetCircuitBreaker(): JsonResponse
    {
        $this->redisHaService->resetCircuitBreaker();

        return ApiResponse::success(null, __('app.api.redis_ha.circuit_reset'));
    }
}
