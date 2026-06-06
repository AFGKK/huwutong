<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ApmService;
use App\Services\OpenTelemetryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ApmController extends Controller
{
    public function __construct(
        protected ApmService $apmService,
        protected OpenTelemetryService $otelService,
    ) {}

    /**
     * APM 总览统计
     */
    public function overview(Request $request): \Illuminate\Http\JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $since = now()->subHours($period);

        return ApiResponse::success([
            'stats' => $this->apmService->getStats($since),
            'distribution' => $this->apmService->getDurationDistribution($since),
            'slow_reasons' => $this->apmService->getSlowReasonDistribution($since),
        ]);
    }

    /**
     * 慢请求列表
     */
    public function slowRequests(): \Illuminate\Http\JsonResponse
    {
        $requests = $this->apmService->getSlowRequests(20);

        return ApiResponse::success($requests);
    }

    /**
     * 最慢路由 Top N
     */
    public function slowestRoutes(Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = min((int) $request->input('limit', 10), 50);
        $routes = $this->apmService->getSlowestRoutes($limit);

        return ApiResponse::success($routes);
    }

    /**
     * 获取单个 APM 记录详情
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        $record = \App\Models\ApmRequest::find($id);

        if (! $record) {
            return ApiResponse::notFound('APM 记录不存在');
        }

        return ApiResponse::success($record);
    }

    /**
     * 手动触发 APM 数据清理
     */
    public function prune(): \Illuminate\Http\JsonResponse
    {
        $deleted = $this->apmService->prune();

        return ApiResponse::success(['deleted' => $deleted], "已清理 {$deleted} 条过期记录");
    }

    /**
     * OpenTelemetry 集成状态
     */
    public function otelStatus(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success(
            $this->otelService->getHealth()
        );
    }

    /**
     * 获取 APM 配置（采样率、保留期等）
     */
    public function config(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success([
            'slow_threshold_ms' => ApmService::SLOW_THRESHOLD_MS,
            'sample_rate' => ApmService::SAMPLE_RATE,
            'retention_days' => 7,
            'otel' => $this->otelService->getHealth(),
        ]);
    }

    /**
     * 解析统计周期（小时）
     */
    protected function resolvePeriod(Request $request): int
    {
        $periods = [1, 6, 12, 24, 72, 168];
        $period = (int) $request->input('period', 24);

        if (! in_array($period, $periods)) {
            $period = 24;
        }

        return $period;
    }
}
