<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseAnalyticsEvent;
use App\Services\LicenseAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseAnalyticsController extends Controller
{
    public function __construct(
        protected LicenseAnalyticsService $analyticsService,
    ) {}

    /**
     * 分析看板综合数据
     *
     * GET /api/license-analytics/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $this->analyticsService->getDashboardData($tenantId);

        return ApiResponse::success($data);
    }

    /**
     * 激活地理分布
     *
     * GET /api/license-analytics/geo-distribution
     */
    public function geoDistribution(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $this->analyticsService->getGeoDistribution($tenantId);

        return ApiResponse::success($data);
    }

    /**
     * 激活趋势（逐日）
     *
     * GET /api/license-analytics/activation-trend?days=30
     */
    public function activationTrend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = min((int) $request->input('days', 30), 365);

        $data = $this->analyticsService->getEventTrend('activation', $days, $tenantId);

        return ApiResponse::success($data);
    }

    /**
     * 违规趋势（逐日）
     *
     * GET /api/license-analytics/violation-trend?days=30
     */
    public function violationTrend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = min((int) $request->input('days', 30), 365);

        $data = $this->analyticsService->getEventTrend('violation', $days, $tenantId);

        return ApiResponse::success($data);
    }

    /**
     * License 使用饱和度
     *
     * GET /api/license-analytics/utilization
     */
    public function utilization(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $this->analyticsService->getUtilizationStats($tenantId);

        return ApiResponse::success($data);
    }

    /**
     * SDK 版本分布
     *
     * GET /api/license-analytics/sdk-stats
     */
    public function sdkStats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $this->analyticsService->getSdkVersionStats($tenantId);

        return ApiResponse::success($data);
    }

    /**
     * 按产品统计
     *
     * GET /api/license-analytics/product-stats
     */
    public function productStats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $this->analyticsService->getProductStats($tenantId);

        return ApiResponse::success($data);
    }

    /**
     * 热力图经纬度数据
     *
     * GET /api/license-analytics/heatmap?days=30
     */
    public function heatmap(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = min((int) $request->input('days', 30), 365);

        $data = $this->analyticsService->getHeatmapData($tenantId, $days);

        return ApiResponse::success($data);
    }

    /**
     * 违规检测列表
     *
     * GET /api/license-analytics/violations?violation_type=&date_from=&date_to=&page=
     */
    public function violations(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $this->analyticsService->getViolations($tenantId, $request->only([
            'violation_type', 'date_from', 'date_to',
        ]), (int) $request->input('per_page', 20));

        return ApiResponse::success($data);
    }

    /**
     * 手动触发违规检测
     *
     * POST /api/license-analytics/detect-violations
     */
    public function detectViolations(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __("app.license_analytics.msg_d618d0f5"), 403);
        }

        $tenantId = $request->user()->tenant_id;

        $result = $this->analyticsService->detectViolations($tenantId);

        return ApiResponse::success($result, __("app.license_analytics.msg_5c4236f5"));
    }

    /**
     * 回填历史数据到分析引擎
     *
     * POST /api/license-analytics/backfill
     */
    public function backfill(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.license_analytics.not_authorized'), 403);
        }

        $result = $this->analyticsService->backfillAnalytics();

        return ApiResponse::success($result, __("app.license_analytics.msg_d29872aa"));
    }

    /**
     * 获取违规类型列表
     *
     * GET /api/license-analytics/violation-types
     */
    public function violationTypes(): JsonResponse
    {
        return ApiResponse::success($this->analyticsService->getViolationTypes());
    }

    /**
     * 概要统计
     *
     * GET /api/license-analytics/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success($this->analyticsService->getSummary($tenantId));
    }

    /**
     * License 类型分布
     *
     * GET /api/license-analytics/type-distribution
     */
    public function typeDistribution(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success($this->analyticsService->getLicenseTypeDistribution($tenantId));
    }

    /**
     * License 状态分布
     *
     * GET /api/license-analytics/status-distribution
     */
    public function statusDistribution(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success($this->analyticsService->getLicenseStatusDistribution($tenantId));
    }

    /**
     * 设备平台分布
     *
     * GET /api/license-analytics/platform-distribution
     */
    public function platformDistribution(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success($this->analyticsService->getDevicePlatformDistribution($tenantId));
    }

    /**
     * License 创建趋势
     *
     * GET /api/license-analytics/creation-trend
     */
    public function creationTrend(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $days = min((int) $request->input('days', 30), 365);

        return ApiResponse::success($this->analyticsService->getLicenseCreationTrend($days, $tenantId));
    }

    /**
     * License 综合看板
     *
     * GET /api/license-analytics/license-dashboard
     */
    public function licenseDashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success($this->analyticsService->getLicenseDashboard($tenantId));
    }

    /**
     * 按国别查看详细分析
     *
     * GET /api/license-analytics/geo/{countryCode}
     */
    public function geoDetail(Request $request, string $countryCode): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = LicenseAnalyticsEvent::query()
            ->where('country_code', strtoupper($countryCode))
            ->with(['license:id,license_key,status'])
            ->orderByDesc('occurred_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $events = $query->paginate(20);

        $stats = LicenseAnalyticsEvent::query()
            ->where('country_code', strtoupper($countryCode))
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw("
                event_type,
                count(*) as count,
                count(distinct license_id) as unique_licenses,
                count(distinct ip_address) as unique_ips
            ")
            ->groupBy('event_type')
            ->get();

        return ApiResponse::success([
            'events' => $events,
            'stats' => $stats,
        ]);
    }
}
