<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\WebhookMonitorService;
use Illuminate\Http\Request;

class WebhookMonitorController extends Controller
{
    public function __construct(
        protected WebhookMonitorService $monitorService
    ) {}

    /**
     * 监控概览
     */
    public function overview(Request $request)
    {
        return ApiResponse::success(
            $this->monitorService->overview($request->user()->tenant_id)
        );
    }

    /**
     * 单个端点监控详情
     */
    public function endpointDetail(Request $request, int $endpointId)
    {
        return ApiResponse::success(
            $this->monitorService->endpointDetail($endpointId, $request->user()->tenant_id)
        );
    }

    /**
     * 失败事件列表
     */
    public function failures(Request $request)
    {
        $paginated = $this->monitorService->failures(
            $request->user()->tenant_id,
            $request->only(['endpoint_id', 'event_type', 'status', 'date_from', 'date_to', 'page', 'per_page'])
        );

        return ApiResponse::success($paginated['data'] ?? $paginated, __("app.webhook_monitor.msg_d72b49e1"));
    }

    /**
     * 延迟分布
     */
    public function latencyDistribution(Request $request)
    {
        return ApiResponse::success(
            $this->monitorService->latencyDistribution(
                $request->user()->tenant_id,
                (int) $request->input('days', 7)
            )
        );
    }

    /**
     * 聚合每日统计
     */
    public function aggregateDaily(Request $request)
    {
        $date = $request->input('date', now()->subDay()->format('Y-m-d'));
        $count = $this->monitorService->aggregateDailyStats(
            $request->user()->tenant_id,
            $date
        );

        return ApiResponse::success(['aggregated' => $count, 'date' => $date], __("app.webhook_monitor.msg_87e3df01"));
    }
}
