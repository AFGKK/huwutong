<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\EcommerceDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 电商数据看板 (M2-154 🛒)
 */
class EcommerceDashboardController extends Controller
{
    public function __construct(
        protected EcommerceDashboardService $dashboardService,
    ) {}

    /**
     * 看板全部数据
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->dashboardService->getDashboard($tenantId)
        );
    }

    /**
     * 今日统计
     */
    public function today(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->dashboardService->getTodayStats($tenantId)
        );
    }

    /**
     * 商品销量排行
     */
    public function productRanking(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $limit = (int) $request->get('limit', 10);
        return ApiResponse::success(
            $this->dashboardService->getProductSalesRanking($tenantId, $limit)
        );
    }

    /**
     * 支付成功率
     */
    public function paymentSuccessRate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->dashboardService->getPaymentSuccessRate($tenantId)
        );
    }

    /**
     * 退款率
     */
    public function refundRate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->dashboardService->getRefundRate($tenantId)
        );
    }

    /**
     * 趋势数据
     */
    public function trend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 7);
        return ApiResponse::success(
            $this->dashboardService->getTrend($tenantId, $days)
        );
    }
}
