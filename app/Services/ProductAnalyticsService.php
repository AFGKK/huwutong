<?php

namespace App\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\LicenseAnalyticsEvent;
use App\Models\Product;
use App\Models\UsageAggregate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 产品使用分析看板
 *
 * 覆盖三个核心维度：
 * 1. 畅销产品分析（License 销量、激活率、订阅收入）
 * 2. 功能模块使用率（模块分布、功能 Flag 使用）
 * 3. 区域增长热力图（国家/城市级 License 分布与增长）
 */
class ProductAnalyticsService
{
    /**
     * 获取分析看板聚合数据
     */
    public function getDashboard(int $tenantId, int $days = 30): array
    {
        $periodStart = Carbon::now()->subDays($days);

        return [
            'product_ranking' => $this->getProductRanking($tenantId),
            'module_usage' => $this->getModuleUsage($tenantId),
            'regional_growth' => $this->getRegionalGrowth($tenantId, $periodStart),
            'license_trend' => $this->getLicenseTrend($tenantId, $periodStart),
            'activation_trend' => $this->getActivationTrend($tenantId, $periodStart),
            'summary' => $this->getSummary($tenantId, $periodStart),
        ];
    }

    /**
     * 畅销产品榜单
     */
    public function getProductRanking(int $tenantId): array
    {
        $products = Product::all();

        $ranking = [];
        foreach ($products as $product) {
            $licenses = License::where('product_id', $product->id)
                ->where('tenant_id', $tenantId);

            $totalLicenses = (clone $licenses)->count();
            $activeLicenses = (clone $licenses)->where('status', 'active')->count();
            $expiredLicenses = (clone $licenses)->whereIn('status', ['expired', 'revoked'])->count();

            // 获取该产品下所有License的事件数
            $eventCount = LicenseAnalyticsEvent::whereIn('license_id', function ($q) use ($product, $tenantId) {
                $q->select('id')->from('licenses')
                    ->where('product_id', $product->id)
                    ->where('tenant_id', $tenantId);
            })->count();

            // 设备数
            $deviceCount = Device::whereIn('license_id', function ($q) use ($product, $tenantId) {
                $q->select('id')->from('licenses')
                    ->where('product_id', $product->id)
                    ->where('tenant_id', $tenantId);
            })->count();

            // 激活率
            $activationRate = $totalLicenses > 0
                ? round(($activeLicenses / $totalLicenses) * 100, 1) : 0;

            $ranking[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'slug' => $product->slug,
                'version' => $product->version,
                'total_licenses' => $totalLicenses,
                'active_licenses' => $activeLicenses,
                'expired_licenses' => $expiredLicenses,
                'activation_rate' => $activationRate,
                'total_events' => $eventCount,
                'total_devices' => $deviceCount,
            ];
        }

        // 按总License数降序排序
        usort($ranking, fn($a, $b) => $b['total_licenses'] <=> $a['total_licenses']);

        return $ranking;
    }

    /**
     * 功能模块使用率
     */
    public function getModuleUsage(int $tenantId): array
    {
        $products = Product::all();

        $result = [];

        foreach ($products as $product) {
            $modules = $product->modules ?? [];
            if (empty($modules)) continue;

            $licenseIds = License::where('product_id', $product->id)
                ->where('tenant_id', $tenantId)
                ->pluck('id');

            if ($licenseIds->isEmpty()) continue;

            $totalLicenses = $licenseIds->count();
            $moduleStats = [];

            foreach ($modules as $module) {
                $moduleKey = is_string($module) ? $module : ($module['key'] ?? $module['name'] ?? 'unknown');
                $moduleName = is_string($module) ? $module : ($module['name'] ?? $moduleKey);

                // 统计使用了此模块的事件数
                $eventCount = LicenseAnalyticsEvent::whereIn('license_id', $licenseIds)
                    ->where('event_type', 'heartbeat')
                    ->where('metadata->module', $moduleKey)
                    ->count();

                $moduleStats[] = [
                    'module_key' => $moduleKey,
                    'module_name' => $moduleName,
                    'event_count' => $eventCount,
                    'usage_rate' => $totalLicenses > 0
                        ? round(($eventCount / $totalLicenses) * 100, 1) : 0,
                ];
            }

            usort($moduleStats, fn($a, $b) => $b['usage_rate'] <=> $a['usage_rate']);

            $result[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'total_licenses' => $totalLicenses,
                'modules' => $moduleStats,
            ];
        }

        return $result;
    }

    /**
     * 区域增长数据
     */
    public function getRegionalGrowth(int $tenantId, ?Carbon $periodStart = null): array
    {
        $periodStart = $periodStart ?? Carbon::now()->subDays(30);

        // 按国家统计事件
        $countryStats = LicenseAnalyticsEvent::whereIn('license_id', function ($q) use ($tenantId) {
            $q->select('id')->from('licenses')->where('tenant_id', $tenantId);
        })
            ->selectRaw('country_code, country_name, count(*) as total_events')
            ->selectRaw('SUM(CASE WHEN occurred_at >= ? THEN 1 ELSE 0 END) as period_events', [$periodStart])
            ->selectRaw('COUNT(DISTINCT license_id) as active_licenses')
            ->selectRaw('COUNT(DISTINCT city) as city_count')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('total_events')
            ->limit(30)
            ->get();

        $countries = [];
        $totalEventsAll = 0;

        foreach ($countryStats as $stat) {
            if (!$stat->country_code) continue;
            $countries[] = [
                'country_code' => $stat->country_code,
                'country_name' => $stat->country_name ?: $stat->country_code,
                'total_events' => (int) $stat->total_events,
                'period_events' => (int) $stat->period_events,
                'active_licenses' => (int) $stat->active_licenses,
                'city_count' => (int) $stat->city_count,
            ];
            $totalEventsAll += (int) $stat->period_events;
        }

        // 增长百分比
        foreach ($countries as &$country) {
            $country['share_percent'] = $totalEventsAll > 0
                ? round(($country['period_events'] / $totalEventsAll) * 100, 1) : 0;
        }

        return [
            'countries' => $countries,
            'total_countries' => count($countries),
            'total_period_events' => $totalEventsAll,
        ];
    }

    /**
     * License 增长趋势（按天）
     */
    public function getLicenseTrend(int $tenantId, ?Carbon $periodStart = null): array
    {
        $periodStart = $periodStart ?? Carbon::now()->subDays(30);
        $periodEnd = Carbon::now();

        $trends = License::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $periodStart)
            ->selectRaw("DATE(created_at) as date")
            ->selectRaw("COUNT(*) as new_licenses")
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_added")
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // 填充缺失日期
        $result = [];
        $cursor = $periodStart->copy();
        while ($cursor <= $periodEnd) {
            $dateStr = $cursor->format('Y-m-d');
            $dayData = $trends->get($dateStr);
            $result[] = [
                'date' => $dateStr,
                'new_licenses' => (int) ($dayData->new_licenses ?? 0),
                'active_added' => (int) ($dayData->active_added ?? 0),
            ];
            $cursor->addDay();
        }

        return $result;
    }

    /**
     * 激活趋势（按天）
     */
    public function getActivationTrend(int $tenantId, ?Carbon $periodStart = null): array
    {
        $periodStart = $periodStart ?? Carbon::now()->subDays(30);

        $events = LicenseAnalyticsEvent::whereIn('license_id', function ($q) use ($tenantId) {
            $q->select('id')->from('licenses')->where('tenant_id', $tenantId);
        })
            ->whereIn('event_type', ['activation', 'heartbeat'])
            ->where('occurred_at', '>=', $periodStart)
            ->selectRaw("DATE(occurred_at) as date")
            ->selectRaw("COUNT(*) as total_events")
            ->selectRaw("COUNT(DISTINCT license_id) as active_licenses")
            ->selectRaw("SUM(CASE WHEN event_type = 'activation' THEN 1 ELSE 0 END) as activations")
            ->groupBy(DB::raw("DATE(occurred_at)"))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $cursor = $periodStart->copy();
        while ($cursor <= Carbon::now()) {
            $dateStr = $cursor->format('Y-m-d');
            $dayData = $events->get($dateStr);
            $result[] = [
                'date' => $dateStr,
                'total_events' => (int) ($dayData->total_events ?? 0),
                'active_licenses' => (int) ($dayData->active_licenses ?? 0),
                'activations' => (int) ($dayData->activations ?? 0),
            ];
            $cursor->addDay();
        }

        return $result;
    }

    /**
     * 概要统计
     */
    public function getSummary(int $tenantId, ?Carbon $periodStart = null): array
    {
        $periodStart = $periodStart ?? Carbon::now()->subDays(30);

        $totalProducts = Product::count();

        $totalLicenses = License::where('tenant_id', $tenantId)->count();
        $activeLicenses = License::where('tenant_id', $tenantId)->where('status', 'active')->count();

        $newLicensesPeriod = License::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $periodStart)
            ->count();

        $eventsPeriod = LicenseAnalyticsEvent::whereIn('license_id', function ($q) use ($tenantId) {
            $q->select('id')->from('licenses')->where('tenant_id', $tenantId);
        })
            ->where('occurred_at', '>=', $periodStart)
            ->count();

        $totalDevices = Device::whereIn('license_id', function ($q) use ($tenantId) {
            $q->select('id')->from('licenses')->where('tenant_id', $tenantId);
        })->count();

        $activationRate = $totalLicenses > 0
            ? round(($activeLicenses / $totalLicenses) * 100, 1) : 0;

        return [
            'total_products' => $totalProducts,
            'total_licenses' => $totalLicenses,
            'active_licenses' => $activeLicenses,
            'new_licenses_period' => $newLicensesPeriod,
            'period_events' => $eventsPeriod,
            'total_devices' => $totalDevices,
            'activation_rate' => $activationRate,
            'period_days' => $periodStart->diffInDays(now()),
        ];
    }

    /**
     * 区域热力图数据（经纬度坐标点）
     */
    public function getHeatmap(int $tenantId, int $days = 30): array
    {
        $periodStart = Carbon::now()->subDays($days);

        $points = LicenseAnalyticsEvent::whereIn('license_id', function ($q) use ($tenantId) {
            $q->select('id')->from('licenses')->where('tenant_id', $tenantId);
        })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('occurred_at', '>=', $periodStart)
            ->selectRaw('
                latitude, longitude,
                country_code, country_name, city,
                COUNT(*) as intensity
            ')
            ->groupBy('latitude', 'longitude', 'country_code', 'country_name', 'city')
            ->orderByDesc('intensity')
            ->limit(200)
            ->get();

        $maxIntensity = $points->max('intensity') ?: 1;

        return $points->map(fn($p) => [
            'lat' => (float) $p->latitude,
            'lng' => (float) $p->longitude,
            'country_code' => $p->country_code,
            'country_name' => $p->country_name,
            'city' => $p->city,
            'intensity' => (int) $p->intensity,
            'weight' => round((int) $p->intensity / $maxIntensity, 2),
        ])->toArray();
    }

    /**
     * 按产品统计的月度使用趋势
     */
    public function getProductMonthlyTrend(int $tenantId, int $months = 6): array
    {
        $periodStart = Carbon::now()->subMonths($months);

        $products = Product::pluck('name', 'id');

        $monthlyData = License::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $periodStart)
            ->selectRaw($this->dateFormatMonth('created_at') . ' as month')
            ->selectRaw('product_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('month', 'product_id')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        $result = [];
        foreach ($monthlyData as $month => $records) {
            $productBreakdown = [];
            foreach ($records as $record) {
                $productName = $products->get($record->product_id, '未知');
                $productBreakdown[] = [
                    'product_id' => $record->product_id,
                    'product_name' => $productName,
                    'new_licenses' => (int) $record->total,
                ];
            }
            $result[] = [
                'month' => $month,
                'total_new_licenses' => (int) $records->sum('total'),
                'products' => $productBreakdown,
            ];
        }

        return $result;
    }

    /**
     * 按月分组日期格式化（兼容 MySQL / SQLite）
     */
    protected function dateFormatMonth(string $column): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }
        return "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * 区域增长趋势（月度）
     */
    public function getRegionalTrend(int $tenantId, int $months = 6): array
    {
        $periodStart = Carbon::now()->subMonths($months);

        $trends = LicenseAnalyticsEvent::whereIn('license_id', function ($q) use ($tenantId) {
            $q->select('id')->from('licenses')->where('tenant_id', $tenantId);
        })
            ->whereNotNull('country_code')
            ->where('occurred_at', '>=', $periodStart)
            ->selectRaw($this->dateFormatMonth('occurred_at') . ' as month')
            ->selectRaw('country_code, country_name')
            ->selectRaw('COUNT(*) as event_count')
            ->selectRaw('COUNT(DISTINCT license_id) as license_count')
            ->groupBy('month', 'country_code', 'country_name')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        $topCountries = LicenseAnalyticsEvent::whereIn('license_id', function ($q) use ($tenantId) {
            $q->select('id')->from('licenses')->where('tenant_id', $tenantId);
        })
            ->whereNotNull('country_code')
            ->selectRaw('country_code, country_name, COUNT(*) as total')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('country_name', 'country_code');

        $result = [];
        foreach ($trends as $month => $records) {
            $countries = [];
            foreach ($records as $record) {
                $countries[] = [
                    'country_code' => $record->country_code,
                    'country_name' => $record->country_name,
                    'event_count' => (int) $record->event_count,
                    'license_count' => (int) $record->license_count,
                ];
            }
            $result[] = [
                'month' => $month,
                'total_events' => (int) $records->sum('event_count'),
                'countries' => $countries,
            ];
        }

        return [
            'monthly_trend' => $result,
            'top_countries' => $topCountries->map(fn($name, $code) => [
                'country_code' => $code,
                'country_name' => $name,
            ])->values()->toArray(),
        ];
    }
}
