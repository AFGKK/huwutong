<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CacheWarmupService;
use App\Services\DatabaseReadWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseReadWriteController extends Controller
{
    public function __construct(
        protected DatabaseReadWriteService $readWriteService,
        protected CacheWarmupService $cacheWarmupService,
    ) {}

    /**
     * 读写分离状态
     */
    public function status(): JsonResponse
    {
        return ApiResponse::success($this->readWriteService->getStatus());
    }

    /**
     * 重置熔断器
     */
    public function resetCircuitBreaker(): JsonResponse
    {
        $this->readWriteService->resetCircuitBreaker();
        return ApiResponse::success(null, __("app.database_read_write.msg_97dafaa3"));
    }

    /**
     * 手动健康检查
     */
    public function healthCheck(): JsonResponse
    {
        $result = $this->readWriteService->checkReplicaHealth();
        return ApiResponse::success($result);
    }

    /**
     * 缓存预热状态
     */
    public function cacheStatus(): JsonResponse
    {
        $status = $this->cacheWarmupService->getStatus();
        $status['stats'] = $this->cacheWarmupService->getStats();
        return ApiResponse::success($status);
    }

    /**
     * 手动触发缓存预热
     */
    public function triggerWarmup(Request $request): JsonResponse
    {
        $source = $request->input('source');
        $result = $this->cacheWarmupService->warmup($source);

        return $result['success']
            ? ApiResponse::success($result, __("app.database_read_write.msg_b2631390"))
            : ApiResponse::error($result['message'], 400);
    }
}
