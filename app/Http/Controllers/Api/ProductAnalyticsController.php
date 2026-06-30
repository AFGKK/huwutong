<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ProductAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAnalyticsController extends Controller
{
    public function __construct(
        protected ProductAnalyticsService $productAnalyticsService,
    ) {}

    /**
     * 产品分析看板聚合数据
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->productAnalyticsService->getDashboard($tenantId, $days)
        );
    }

    /**
     * 畅销产品榜单
     */
    public function productRanking(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success(
            $this->productAnalyticsService->getProductRanking($tenantId)
        );
    }

    /**
     * 功能模块使用率
     */
    public function moduleUsage(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success(
            $this->productAnalyticsService->getModuleUsage($tenantId)
        );
    }

    /**
     * 区域增长数据
     */
    public function regionalGrowth(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->productAnalyticsService->getRegionalGrowth($tenantId, null)
        );
    }

    /**
     * License 增长趋势
     */
    public function licenseTrend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->productAnalyticsService->getLicenseTrend($tenantId, null)
        );
    }

    /**
     * 激活趋势
     */
    public function activationTrend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->productAnalyticsService->getActivationTrend($tenantId, null)
        );
    }

    /**
     * 热力图数据
     */
    public function heatmap(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->productAnalyticsService->getHeatmap($tenantId, $days)
        );
    }

    /**
     * 产品月度趋势
     */
    public function productMonthlyTrend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $months = (int) $request->get('months', 6);

        return ApiResponse::success(
            $this->productAnalyticsService->getProductMonthlyTrend($tenantId, $months)
        );
    }

    /**
     * 区域增长趋势（月度）
     */
    public function regionalTrend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $months = (int) $request->get('months', 6);

        return ApiResponse::success(
            $this->productAnalyticsService->getRegionalTrend($tenantId, $months)
        );
    }

    /**
     * 概要统计
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->productAnalyticsService->getSummary($tenantId, null)
        );
    }
}
