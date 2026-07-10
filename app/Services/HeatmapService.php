<?php

namespace App\Services;

use App\Models\HeatmapLayer;
use App\Models\LicenseAnalyticsEvent;
use App\Support\DbSql;
use Illuminate\Support\Facades\DB;

/**
 * 多层热力地图服务 (M3-41)
 *
 * 核心功能：
 * 1. 热力图层管理（CRUD）
 * 2. 多数据源热力图数据聚合（激活/使用/API/收入）
 * 3. 按国家/地区钻取数据
 */
class HeatmapService
{
    // ═══════ 图层管理 ═══════

    public function listLayers(int $tenantId): array
    {
        return HeatmapLayer::where('tenant_id', $tenantId)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function createLayer(array $data): HeatmapLayer
    {
        return HeatmapLayer::create($data);
    }

    public function updateLayer(HeatmapLayer $layer, array $data): HeatmapLayer
    {
        $layer->update($data);
        return $layer->fresh();
    }

    public function deleteLayer(HeatmapLayer $layer): void
    {
        $layer->delete();
    }

    // ═══════ 热力图数据 ═══════

    /**
     * 获取多层热力图数据
     */
    public function getMultiLayerData(int $tenantId, array $filters = []): array
    {
        $days = $filters['days'] ?? 30;
        $layers = !empty($filters['layers'])
            ? explode(',', $filters['layers'])
            : ['license_activations'];

        $result = [];

        foreach ($layers as $layerSource) {
            $data = match ($layerSource) {
                'license_activations' => $this->getActivationHeatmap($tenantId, $days),
                'product_usage' => $this->getUsageHeatmap($tenantId, $days),
                'api_calls' => $this->getApiHeatmap($tenantId, $days),
                'revenue' => $this->getRevenueHeatmap($tenantId, $days),
                default => [],
            };

            $result[$layerSource] = [
                'points' => $data['points'] ?? [],
                'countries' => $data['countries'] ?? [],
                'summary' => $data['summary'] ?? [],
            ];
        }

        return $result;
    }

    /**
     * License 激活热力图（基于 LicenseAnalyticsEvent）
     */
    protected function getActivationHeatmap(int $tenantId, int $days): array
    {
        $query = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->whereIn('event_type', ['activation', 'heartbeat'])
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('occurred_at', '>=', now()->subDays($days));

        $points = (clone $query)
            ->selectRaw('latitude, longitude, country_code, country_name, city, count(*) as intensity')
            ->groupBy('latitude', 'longitude', 'country_code', 'country_name', 'city')
            ->orderByDesc('intensity')
            ->limit(500)
            ->get()
            ->toArray();

        $countries = (clone $query)
            ->selectRaw('country_code, country_name, count(*) as total, count(DISTINCT license_id) as licenses')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return [
            'points' => $points,
            'countries' => $countries,
            'summary' => [
                'total_events' => (clone $query)->count(),
                'total_countries' => count($countries),
                'total_points' => count($points),
            ],
        ];
    }

    /**
     * 产品使用热力图
     */
    protected function getUsageHeatmap(int $tenantId, int $days): array
    {
        // 基于 LicenseAnalyticsEvent 的 validate/heartbeat 事件
        $query = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->where('event_type', 'heartbeat')
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('occurred_at', '>=', now()->subDays($days));

        $points = (clone $query)
            ->selectRaw('latitude, longitude, country_code, country_name, city, count(*) as intensity')
            ->groupBy('latitude', 'longitude', 'country_code', 'country_name', 'city')
            ->orderByDesc('intensity')
            ->limit(500)
            ->get()
            ->toArray();

        $countries = (clone $query)
            ->selectRaw('country_code, country_name, count(*) as total, count(DISTINCT license_id) as licenses')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return [
            'points' => $points,
            'countries' => $countries,
            'summary' => [
                'total_events' => (clone $query)->count(),
                'total_countries' => count($countries),
            ],
        ];
    }

    /**
     * API 调用热力图（基于事件中的 metadata 判断）
     */
    protected function getApiHeatmap(int $tenantId, int $days): array
    {
        $heatSourceExpr = DbSql::jsonStringEquals('metadata', 'source', 'api');

        $query = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->where('event_type', 'heartbeat')
            ->whereRaw($heatSourceExpr)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->where('occurred_at', '>=', now()->subDays($days));

        $points = (clone $query)
            ->selectRaw('latitude, longitude, country_code, country_name, count(*) as intensity')
            ->groupBy('latitude', 'longitude', 'country_code', 'country_name')
            ->orderByDesc('intensity')
            ->limit(500)
            ->get()
            ->toArray();

        $countries = (clone $query)
            ->selectRaw('country_code, country_name, count(*) as total')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return [
            'points' => $points,
            'countries' => $countries,
            'summary' => [
                'total_events' => (clone $query)->count() ?: 0,
                'total_countries' => count($countries),
            ],
        ];
    }

    /**
     * 收入分布热力图（基于 Invoice 的 billing_country）
     */
    protected function getRevenueHeatmap(int $tenantId, int $days): array
    {
        // 使用 Invoice 中的 billing_country
        $countries = DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('billing_country')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('billing_country as country_code, SUM(amount) as total, COUNT(*) as invoice_count')
            ->groupBy('billing_country')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return [
            'points' => [],
            'countries' => $countries,
            'summary' => [
                'total_revenue' => array_sum(array_column($countries, 'total')),
                'total_countries' => count($countries),
            ],
        ];
    }

    // ═══════ 国家钻取 ═══════

    /**
     * 钻取到国家级别详情
     */
    public function getCountryDetail(int $tenantId, string $countryCode, array $filters = []): array
    {
        $days = $filters['days'] ?? 30;

        $events = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->where('country_code', $countryCode)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->selectRaw('event_type, COUNT(*) as cnt')
            ->groupBy('event_type')
            ->orderByDesc('cnt')
            ->get()
            ->toArray();

        $cities = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->where('country_code', $countryCode)
            ->whereNotNull('city')
            ->where('occurred_at', '>=', now()->subDays($days))
            ->selectRaw('city, COUNT(*) as cnt, AVG(latitude) as lat, AVG(longitude) as lng')
            ->groupBy('city')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get()
            ->toArray();

        $dailyTrend = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->where('country_code', $countryCode)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        return [
            'country_code' => $countryCode,
            'events' => $events,
            'cities' => $cities,
            'daily_trend' => $dailyTrend,
        ];
    }

    // ═══════ 仪表盘 ═══════

    public function getDashboardStats(int $tenantId): array
    {
        $activatedCountries = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->whereNotNull('country_code')
            ->distinct('country_code')
            ->count('country_code');

        $totalPoints = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        $recentEvents = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->count();

        $topCountries = LicenseAnalyticsEvent::where('tenant_id', $tenantId)
            ->whereNotNull('country_code')
            ->selectRaw('country_code, country_name, COUNT(*) as cnt')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'activated_countries' => $activatedCountries,
            'total_geo_points' => $totalPoints,
            'recent_30d_events' => $recentEvents,
            'top_countries' => $topCountries,
        ];
    }
}
