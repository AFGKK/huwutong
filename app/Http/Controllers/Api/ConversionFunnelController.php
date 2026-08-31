<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ConversionFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trial→付费转化漏斗控制器 (M2-101)
 */
class ConversionFunnelController extends Controller
{
    public function __construct(
        protected ConversionFunnelService $funnel,
    ) {}

    /**
     * 仪表盘
     * GET /api/admin/conversion-funnel/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success($this->funnel->getDashboard($tenantId));
    }

    /**
     * 漏斗数据
     * GET /api/admin/conversion-funnel/data
     */
    public function data(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date']);
        return ApiResponse::success($this->funnel->getFunnelData($tenantId, $filters));
    }

    /**
     * 按来源拆分
     * GET /api/admin/conversion-funnel/by-source
     */
    public function bySource(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        return ApiResponse::success($this->funnel->getBySource($tenantId, $startDate, $endDate));
    }

    /**
     * 趋势数据
     * GET /api/admin/conversion-funnel/trend
     */
    public function trend(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $days = $request->integer('days', 30);
        return ApiResponse::success($this->funnel->getTrend($tenantId, $days));
    }

    /**
     * 记录事件（供内部/SDK调用）
     * POST /api/admin/conversion-funnel/track
     */
    public function track(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'stage' => 'required|string|max:50',
            'event' => 'required|string|max:100',
            'customer_id' => 'nullable|integer',
            'license_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        $event = $this->funnel->trackEvent(
            $tenantId,
            $validated['stage'],
            $validated['event'],
            $validated['customer_id'] ?? null,
            $validated['license_id'] ?? null,
            $validated['metadata'] ?? [],
        );

        return ApiResponse::created($event, __("app.conversion_funnel.msg_d7e017ec"));
    }
}
