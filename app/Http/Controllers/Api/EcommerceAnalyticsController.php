<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\EcommerceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EcommerceAnalyticsController extends Controller
{
    public function __construct(
        protected EcommerceAnalyticsService $ecommerceAnalyticsService,
    ) {}

    /**
     * 分析看板
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $period = $request->get('period', '30d');

        return ApiResponse::success(
            $this->ecommerceAnalyticsService->getDashboard($tenantId, $period)
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
            $this->ecommerceAnalyticsService->getSummary($tenantId, $days)
        );
    }

    /**
     * 销售趋势
     */
    public function salesTrend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->ecommerceAnalyticsService->getSalesTrend($tenantId, $days)
        );
    }

    /**
     * 热销商品排行榜
     */
    public function productRanking(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $period = $request->get('period', '30d');

        return ApiResponse::success(
            $this->ecommerceAnalyticsService->getProductSalesRanking($tenantId, $period)
        );
    }

    /**
     * 客户复购率
     */
    public function repurchaseRate(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 90);

        return ApiResponse::success(
            $this->ecommerceAnalyticsService->getRepurchaseRate($tenantId, $days)
        );
    }

    /**
     * 支付渠道分布
     */
    public function paymentChannels(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->ecommerceAnalyticsService->getPaymentChannelBreakdown($tenantId, $days)
        );
    }

    /**
     * 客户指标
     */
    public function customerMetrics(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->ecommerceAnalyticsService->getCustomerMetrics($tenantId, $days)
        );
    }

    /**
     * 同比/环比
     */
    public function comparison(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 30);

        return ApiResponse::success(
            $this->ecommerceAnalyticsService->getPeriodComparison($tenantId, $days)
        );
    }

    /**
     * 销售预测
     */
    public function forecast(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = (int) $request->get('days', 90);
        $forecastDays = (int) $request->get('forecast_days', 30);

        return ApiResponse::success(
            $this->ecommerceAnalyticsService->getSalesForecast($tenantId, $days, $forecastDays)
        );
    }

    /**
     * 导出CSV
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $tenantId = $request->user()->tenant_id;
        $type = $request->get('type', 'sales_trend');
        $days = (int) $request->get('days', 30);

        $filename = match ($type) {
            'sales_trend' => "销售趋势_{$days}天.csv",
            'product_ranking' => "热销商品_{$days}天.csv",
            'payment_channels' => "支付渠道_{$days}天.csv",
            default => "报表_{$days}天.csv",
        };

        $csv = $this->ecommerceAnalyticsService->exportCsv($tenantId, $type, $days);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
