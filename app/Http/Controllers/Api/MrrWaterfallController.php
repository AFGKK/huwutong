<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MrrWaterfallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MRR 瀑布图 (M3-59)
 */
class MrrWaterfallController extends Controller
{
    public function __construct(
        protected MrrWaterfallService $mrrService,
    ) {}

    public function waterfall(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $months = (int) $request->get('months', 6);

        return ApiResponse::success($this->mrrService->getWaterfall(
            $tenantId,
            $months,
            $request->get('year_month'),
        ));
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getSummary($tenantId, $yearMonth));
    }

    public function drilldown(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getDrilldown(
            $tenantId,
            $yearMonth,
            $request->get('change_type'),
            (int) $request->get('per_page', 20),
        ));
    }

    public function breakdownByProduct(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getBreakdownByProduct($tenantId, $yearMonth));
    }

    public function breakdownByRegion(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getBreakdownByRegion($tenantId, $yearMonth));
    }

    public function breakdownByCustomerSegment(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        return ApiResponse::success($this->mrrService->getBreakdownByCustomerSegment($tenantId, $yearMonth));
    }

    /**
     * 手动触发扫描并记录当月MRR变化
     */
    public function scanChanges(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $yearMonth = $request->get('year_month', now()->format('Y-m'));

        $result = $this->mrrService->scanAndRecordMonthlyChanges($tenantId, $yearMonth);

        return ApiResponse::success($result, "已记录 {$result['recorded']} 条MRR变化");
    }
}
