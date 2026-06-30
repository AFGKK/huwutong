<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\EndpointUsageAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-132 API 用量分端点统计 API
 *
 * 客户用量看板的端点级增强：
 * - 按API端点(activate/validate/revoke/check)拆分调用量
 * - 各端点延迟 P50/P99
 * - 错误率
 * - 用量趋势图
 * - 超额预警
 */
class EndpointUsageAnalyticsController extends Controller
{
    public function __construct(
        protected EndpointUsageAnalyticsService $analyticsService,
    ) {}

    /**
     * 获取各端点用量概览
     *
     * GET /api/usage/endpoint/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $result = $this->resolveTenantAndCustomer($request);
        if ($result instanceof JsonResponse) return $result;

        ['tenant' => $tenant, 'customer' => $customer] = $result;

        $overview = $this->analyticsService->getEndpointOverview($tenant, $customer);

        return ApiResponse::success([
            'endpoints' => $overview,
            'total_today' => collect($overview)->sum('today_quantity'),
            'total_month' => collect($overview)->sum('this_month_quantity'),
        ]);
    }

    /**
     * 获取用量趋势（按天）
     *
     * GET /api/usage/endpoint/trend?days=30
     */
    public function trend(Request $request): JsonResponse
    {
        $result = $this->resolveTenantAndCustomer($request);
        if ($result instanceof JsonResponse) return $result;

        $days = min((int) $request->input('days', 30), 90);
        ['tenant' => $tenant, 'customer' => $customer] = $result;

        $trend = $this->analyticsService->getUsageTrend($tenant, $customer, $days);

        return ApiResponse::success([
            'trend' => $trend,
            'endpoints' => EndpointUsageAnalyticsService::ENDPOINT_METRICS,
        ]);
    }

    /**
     * 获取各端点延迟统计
     *
     * GET /api/usage/endpoint/latency?days=7
     */
    public function latency(Request $request): JsonResponse
    {
        $result = $this->resolveTenantAndCustomer($request);
        if ($result instanceof JsonResponse) return $result;

        $days = min((int) $request->input('days', 7), 30);
        ['tenant' => $tenant, 'customer' => $customer] = $result;

        $latencyStats = $this->analyticsService->getLatencyStats($tenant, $customer, $days);

        return ApiResponse::success([
            'latency' => $latencyStats,
            'endpoints' => EndpointUsageAnalyticsService::ENDPOINT_METRICS,
        ]);
    }

    /**
     * 获取各端点错误率统计
     *
     * GET /api/usage/endpoint/errors?days=7
     */
    public function errors(Request $request): JsonResponse
    {
        $result = $this->resolveTenantAndCustomer($request);
        if ($result instanceof JsonResponse) return $result;

        $days = min((int) $request->input('days', 7), 30);
        ['tenant' => $tenant, 'customer' => $customer] = $result;

        $errorStats = $this->analyticsService->getErrorStats($tenant, $customer, $days);
        $errorDetail = $this->analyticsService->getErrorDetail($tenant, $customer, $days);

        return ApiResponse::success([
            'error_stats' => $errorStats,
            'error_detail' => $errorDetail,
            'endpoints' => EndpointUsageAnalyticsService::ENDPOINT_METRICS,
        ]);
    }

    /**
     * 获取超额预警
     *
     * GET /api/usage/endpoint/alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $result = $this->resolveTenantAndCustomer($request);
        if ($result instanceof JsonResponse) return $result;

        ['tenant' => $tenant, 'customer' => $customer] = $result;

        $alertData = $this->analyticsService->getAlertData($tenant, $customer);

        $criticalCount = count(array_filter($alertData, fn($a) => $a['level'] === 'critical'));
        $warningCount = count(array_filter($alertData, fn($a) => $a['level'] === 'warning'));

        return ApiResponse::success([
            'alerts' => $alertData,
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
        ]);
    }

    /**
     * 获取端点用量总览看板（整合所有数据）
     *
     * GET /api/usage/endpoint/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $result = $this->resolveTenantAndCustomer($request);
        if ($result instanceof JsonResponse) return $result;

        ['tenant' => $tenant, 'customer' => $customer] = $result;

        $overview = $this->analyticsService->getEndpointOverview($tenant, $customer);
        $trend = $this->analyticsService->getUsageTrend($tenant, $customer, 14);
        $latencyStats = $this->analyticsService->getLatencyStats($tenant, $customer, 7);
        $errorStats = $this->analyticsService->getErrorStats($tenant, $customer, 7);
        $errorDetail = $this->analyticsService->getErrorDetail($tenant, $customer, 7);
        $alertData = $this->analyticsService->getAlertData($tenant, $customer);

        return ApiResponse::success([
            'overview' => $overview,
            'trend' => $trend,
            'latency' => $latencyStats,
            'errors' => $errorStats,
            'error_detail' => $errorDetail,
            'alerts' => $alertData,
            'endpoints' => EndpointUsageAnalyticsService::ENDPOINT_METRICS,
        ]);
    }

    /**
     * 解析租户和客户
     */
    protected function resolveTenantAndCustomer(Request $request): array|JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer;

        if (! $customer) {
            return ApiResponse::notFound('未找到客户资料');
        }

        $tenant = Tenant::find($customer->tenant_id);
        if (! $tenant) {
            return ApiResponse::notFound('租户不存在');
        }

        return ['tenant' => $tenant, 'customer' => $customer];
    }
}
