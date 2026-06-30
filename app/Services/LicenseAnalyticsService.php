<?php

namespace App\Services;

use App\Models\Device;
use App\Models\GeoLookup;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseAnalyticsEvent;
use App\Models\SdkHeartbeat;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LicenseAnalyticsService
{
    /**
     * 获取分析看板综合数据
     */
    public function getDashboardData(?int $tenantId = null): array
    {
        $eventQuery = LicenseAnalyticsEvent::query();
        $licenseQuery = License::query();
        $deviceQuery = Device::query();

        if ($tenantId) {
            $eventQuery->where('tenant_id', $tenantId);
            $licenseQuery->where('tenant_id', $tenantId);
            $deviceQuery->where('tenant_id', $tenantId);
        }

        // ── 地理分布 ──
        $geoDistribution = $this->getGeoDistribution($tenantId);

        // ── 激活趋势（近30天逐日） ──
        $activationTrend = $this->getEventTrend('activation', 30, $tenantId);

        // ── 违规趋势（近30天逐日） ──
        $violationTrend = $this->getEventTrend('violation', 30, $tenantId);

        // ── 平台分布 ──
        $platformDistribution = (clone $deviceQuery)
            ->selectRaw('platform, count(*) as count')
            ->whereNotNull('platform')
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();

        // ── SDK 版本分布（最近30天心跳） ──
        $sdkVersionStats = $this->getSdkVersionStats($tenantId, 30);

        // ── 综合 License 统计 ──
        $totalLicenses = (clone $licenseQuery)->count();
        $activeLicenses = (clone $licenseQuery)->where('status', 'active')->count();
        $totalActivations = (clone $licenseQuery)->withCount('activations')->get()->sum('activations_count');
        $totalDevices = (clone $deviceQuery)->count();
        $blacklistedDevices = (clone $deviceQuery)->where('is_blacklisted', true)->count();
        $totalEvents = (clone $eventQuery)->count();
        $totalViolations = (clone $eventQuery)->where('event_type', 'violation')->count();
        $suspiciousActivations = (clone $eventQuery)
            ->where('event_type', 'violation')
            ->where('violation_type', 'excessive_activations')
            ->count();

        // ── 违规分类 ──
        $violationsByType = (clone $eventQuery)
            ->where('event_type', 'violation')
            ->selectRaw('violation_type, count(*) as count')
            ->groupBy('violation_type')
            ->pluck('count', 'violation_type')
            ->toArray();

        // ── License 使用饱和度 ──
        $utilizationStats = $this->getUtilizationStats($tenantId);

        return [
            'geo_distribution' => $geoDistribution,
            'activation_trend' => $activationTrend,
            'violation_trend' => $violationTrend,
            'platform_distribution' => $platformDistribution,
            'sdk_version_stats' => $sdkVersionStats,
            'total_licenses' => $totalLicenses,
            'active_licenses' => $activeLicenses,
            'total_activations' => $totalActivations,
            'total_devices' => $totalDevices,
            'blacklisted_devices' => $blacklistedDevices,
            'total_events' => $totalEvents,
            'total_violations' => $totalViolations,
            'suspicious_activations' => $suspiciousActivations,
            'violations_by_type' => $violationsByType,
            'utilization' => $utilizationStats,
        ];
    }

    /**
     * 激活地理分布
     */
    public function getGeoDistribution(?int $tenantId = null): array
    {
        $query = LicenseAnalyticsEvent::query()
            ->whereIn('event_type', ['activation', 'heartbeat'])
            ->whereNotNull('country_code');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $byCountry = (clone $query)
            ->selectRaw('country_code, country_name, count(*) as count')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('count')
            ->limit(50)
            ->get()
            ->toArray();

        $total = array_sum(array_column($byCountry, 'count'));

        // Top 10 城市
        $byCity = (clone $query)
            ->whereNotNull('city')
            ->selectRaw('city, country_code, count(*) as count')
            ->groupBy('city', 'country_code')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->toArray();

        return [
            'countries' => $byCountry,
            'cities' => $byCity,
            'total' => $total,
        ];
    }

    /**
     * 获取事件趋势（近N天逐日）
     */
    public function getEventTrend(string $eventType, int $days = 30, ?int $tenantId = null): array
    {
        $query = LicenseAnalyticsEvent::query()
            ->where('event_type', $eventType)
            ->where('occurred_at', '>=', now()->subDays($days));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $raw = (clone $query)
            ->selectRaw('DATE(occurred_at) as date, count(*) as count')
            ->groupByRaw('DATE(occurred_at)')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // 填充所有日期
        $result = [];
        $start = now()->subDays($days - 1)->startOfDay();
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'count' => (int) ($raw[$date] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * SDK 版本分布（最近N天活跃）
     */
    public function getSdkVersionStats(?int $tenantId = null, int $days = 30): array
    {
        $query = SdkHeartbeat::query()
            ->where('reported_at', '>=', now()->subDays($days));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $byVersion = (clone $query)
            ->selectRaw('sdk_version, count(*) as count')
            ->whereNotNull('sdk_version')
            ->groupBy('sdk_version')
            ->orderByDesc('count')
            ->limit(20)
            ->pluck('count', 'sdk_version')
            ->toArray();

        $byLanguage = (clone $query)
            ->selectRaw('sdk_language, count(*) as count')
            ->whereNotNull('sdk_language')
            ->groupBy('sdk_language')
            ->orderByDesc('count')
            ->pluck('count', 'sdk_language')
            ->toArray();

        return [
            'by_version' => $byVersion,
            'by_language' => $byLanguage,
        ];
    }

    /**
     * License 使用饱和度分析
     */
    public function getUtilizationStats(?int $tenantId = null): array
    {
        $query = License::query()->where('status', 'active');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $licenses = (clone $query)
            ->withCount('devices')
            ->get(['id', 'license_key', 'seats', 'max_devices']);

        if ($licenses->isEmpty()) {
            return [
                'avg_seat_utilization' => 0,
                'avg_device_utilization' => 0,
                'over_capacity_count' => 0,
                'near_capacity_count' => 0,
            ];
        }

        $totalSeats = 0;
        $totalUsedSeats = 0;
        $totalMaxDevices = 0;
        $totalUsedDevices = 0;
        $overCapacity = 0;
        $nearCapacity = 0;

        foreach ($licenses as $license) {
            $deviceCount = $license->devices_count;

            $totalSeats += $license->seats;
            $totalUsedSeats += min($deviceCount, $license->seats);

            $totalMaxDevices += $license->max_devices;
            $totalUsedDevices += $deviceCount;

            if ($license->max_devices > 0 && $deviceCount > $license->max_devices) {
                $overCapacity++;
            } elseif ($license->max_devices > 0 && $deviceCount >= $license->max_devices * 0.8) {
                $nearCapacity++;
            }
        }

        return [
            'avg_seat_utilization' => $totalSeats > 0 ? round(($totalUsedSeats / $totalSeats) * 100, 1) : 0,
            'avg_device_utilization' => $totalMaxDevices > 0 ? round(($totalUsedDevices / $totalMaxDevices) * 100, 1) : 0,
            'over_capacity_count' => $overCapacity,
            'near_capacity_count' => $nearCapacity,
            'total_licenses' => $licenses->count(),
            'total_seats' => $totalSeats,
            'total_used_seats' => $totalUsedSeats,
            'total_max_devices' => $totalMaxDevices,
            'total_used_devices' => $totalUsedDevices,
        ];
    }

    /**
     * 违规检测详情列表
     */
    public function getViolations(?int $tenantId = null, array $filters = [], int $perPage = 20): array
    {
        $query = LicenseAnalyticsEvent::query()
            ->where('event_type', 'violation')
            ->with(['license:id,license_key,status', 'license.product:id,name']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (!empty($filters['violation_type'])) {
            $query->where('violation_type', $filters['violation_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('occurred_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('occurred_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        $paginator = $query->orderByDesc('occurred_at')->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 记录分析事件
     */
    public function recordEvent(
        int    $licenseId,
        string $eventType,
        string $ipAddress = null,
        array  $extra = []
    ): LicenseAnalyticsEvent {
        $license = License::findOrFail($licenseId);

        $geo = null;
        if ($ipAddress && $ipAddress !== '127.0.0.1' && $ipAddress !== '::1') {
            $geo = $this->lookupGeo($ipAddress);
        }

        $data = [
            'license_id' => $licenseId,
            'tenant_id' => $license->tenant_id,
            'event_type' => $eventType,
            'ip_address' => $ipAddress,
            'country_code' => $geo['country_code'] ?? null,
            'country_name' => $geo['country_name'] ?? null,
            'city' => $geo['city'] ?? null,
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
            'platform' => $extra['platform'] ?? null,
            'sdk_version' => $extra['sdk_version'] ?? null,
            'sdk_language' => $extra['sdk_language'] ?? null,
            'sdk_arch' => $extra['sdk_arch'] ?? null,
            'violation_type' => $extra['violation_type'] ?? null,
            'violation_detail' => $extra['violation_detail'] ?? null,
            'metadata' => $extra['metadata'] ?? null,
            'occurred_at' => $extra['occurred_at'] ?? now(),
        ];

        return LicenseAnalyticsEvent::create($data);
    }

    /**
     * 查找/缓存 IP 地理位置
     */
    public function lookupGeo(string $ip): ?array
    {
        // 先从缓存查找
        $cached = GeoLookup::find($ip);
        if ($cached && $cached->cached_at->gt(now()->subDays(7))) {
            return $cached->only(['country_code', 'country_name', 'city', 'latitude', 'longitude', 'isp']);
        }

        // 使用 ip-api.com (免费，无需 API Key，支持 45 次/分钟)
        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode,country,city,lat,lon,isp,query");
            if ($response) {
                $data = json_decode($response, true);
                if ($data && ($data['status'] ?? '') === 'success') {
                    $geoData = [
                        'country_code' => $data['countryCode'] ?? null,
                        'country_name' => $data['country'] ?? null,
                        'city' => $data['city'] ?? null,
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                        'isp' => $data['isp'] ?? null,
                    ];

                    GeoLookup::updateOrCreate(
                        ['ip_address' => $ip],
                        array_merge($geoData, [
                            'source' => 'api',
                            'cached_at' => now(),
                        ])
                    );

                    return $geoData;
                }
            }
        } catch (\Exception $e) {
            // 静默失败，不阻断主流程
        }

        return [
            'country_code' => null,
            'country_name' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
            'isp' => null,
        ];
    }

    /**
     * 执行全量扫描 — 从现有数据填充 analytics_events
     */
    public function backfillAnalytics(): array
    {
        $counts = ['activations' => 0, 'violations' => 0];

        // 从激活记录回填
        LicenseActivation::chunk(200, function ($activations) use (&$counts) {
            foreach ($activations as $activation) {
                $eventType = match ($activation->action) {
                    'activate' => 'activation',
                    'deactivate' => 'deactivation',
                    default => 'checkin',
                };

                $this->recordEvent(
                    $activation->license_id,
                    $eventType,
                    $activation->ip_address,
                    [
                        'metadata' => $activation->payload,
                        'occurred_at' => $activation->created_at,
                    ]
                );
                $counts['activations']++;
            }
        });

        return $counts;
    }

    /**
     * 违规检测引擎 — 查找可疑活动
     */
    public function detectViolations(?int $tenantId = null): array
    {
        $violations = [];

        // 1. 检测超量激活（座位/设备超出限制）
        $licenseQuery = License::query()->where('status', 'active');
        if ($tenantId) {
            $licenseQuery->where('tenant_id', $tenantId);
        }

        $licenseQuery->withCount('devices')->chunk(100, function ($licenses) use (&$violations) {
            foreach ($licenses as $license) {
                $deviceCount = $license->devices_count;

                // 超过 max_devices
                if ($license->max_devices > 0 && $deviceCount > $license->max_devices) {
                    $violations[] = [
                        'license_id' => $license->id,
                        'violation_type' => 'excessive_activations',
                        'violation_detail' => "License #{$license->id} 已激活 {$deviceCount} 台设备，超过限制 {$license->max_devices}",
                        'ip_address' => null,
                    ];
                }

                // 超过 seats（每个 seat 可能对应多个设备，但此处做保守检测）
                if ($license->seats > 0 && $deviceCount > $license->seats * 2) {
                    $violations[] = [
                        'license_id' => $license->id,
                        'violation_type' => 'excessive_activations',
                        'violation_detail' => "License #{$license->id} 设备数 {$deviceCount} 远超座位数 {$license->seats}",
                        'ip_address' => null,
                    ];
                }
            }
        });

        // 2. 检测过期后仍在使用
        $expiredActiveQuery = LicenseAnalyticsEvent::query()
            ->where('event_type', 'heartbeat')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('licenses')
                    ->whereColumn('licenses.id', 'license_analytics_events.license_id')
                    ->whereNotNull('licenses.expires_at')
                    ->where('licenses.expires_at', '<', now())
                    ->where('licenses.status', '!=', 'expired');
            });

        if ($tenantId) {
            $expiredActiveQuery->where('tenant_id', $tenantId);
        }

        $expiredUsing = $expiredActiveQuery
            ->distinct('license_id')
            ->count('license_id');

        if ($expiredUsing > 0) {
            // 标记每条过期事件
            $expiredEvents = $expiredActiveQuery->get();
            foreach ($expiredEvents as $event) {
                $violations[] = [
                    'license_id' => $event->license_id,
                    'violation_type' => 'expired_use',
                    'violation_detail' => "过期 License 仍在发送心跳",
                    'ip_address' => $event->ip_address,
                ];
            }
        }

        // 3. 检测黑名单设备活动
        $blacklistEventQuery = LicenseAnalyticsEvent::query()
            ->where('event_type', 'heartbeat')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('devices')
                    ->whereColumn('devices.id', 'license_analytics_events.license_id')
                    ->where('devices.is_blacklisted', true);
            });

        if ($tenantId) {
            $blacklistEventQuery->where('tenant_id', $tenantId);
        }

        $blacklistEvents = $blacklistEventQuery->get();
        foreach ($blacklistEvents as $event) {
            $violations[] = [
                'license_id' => $event->license_id,
                'violation_type' => 'blacklisted_device',
                'violation_detail' => "黑名单设备尝试激活/心跳",
                'ip_address' => $event->ip_address,
            ];
        }

        // 记录检测到的违规
        $recorded = 0;
        foreach ($violations as $v) {
            // 使用唯一键去重：同 license + violation_type 一天内只记录一条
            $exists = LicenseAnalyticsEvent::query()
                ->where('license_id', $v['license_id'])
                ->where('event_type', 'violation')
                ->where('violation_type', $v['violation_type'])
                ->whereDate('occurred_at', today())
                ->exists();

            if (!$exists) {
                $this->recordEvent(
                    $v['license_id'],
                    'violation',
                    $v['ip_address'],
                    [
                        'violation_type' => $v['violation_type'],
                        'violation_detail' => $v['violation_detail'],
                    ]
                );
                $recorded++;
            }
        }

        return [
            'violations_found' => count($violations),
            'violations_recorded' => $recorded,
            'violation_count_by_type' => array_count_values(array_column($violations, 'violation_type')),
        ];
    }

    /**
     * 获取热力图数据（用于地图展示）
     */
    public function getHeatmapData(?int $tenantId = null, int $days = 30): array
    {
        $query = LicenseAnalyticsEvent::query()
            ->whereIn('event_type', ['activation', 'heartbeat'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('occurred_at', '>=', now()->subDays($days));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return (clone $query)
            ->selectRaw('latitude, longitude, count(*) as intensity')
            ->groupBy('latitude', 'longitude')
            ->get()
            ->toArray();
    }

    /**
     * 按产品统计 License 使用量
     */
    public function getProductStats(?int $tenantId = null): array
    {
        $query = License::query()
            ->selectRaw('product_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as active_count', ['active'])
            ->with('product:id,name')
            ->groupBy('product_id');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $stats = $query->get();

        $result = [];
        foreach ($stats as $stat) {
            $eventCount = LicenseAnalyticsEvent::query()
                ->whereIn('event_type', ['activation', 'heartbeat'])
                ->whereIn('license_id', function ($q) use ($stat) {
                    $q->select('id')->from('licenses')->where('product_id', $stat->product_id);
                })
                ->count();

            $result[] = [
                'product_id' => $stat->product_id,
                'product_name' => $stat->product?->name ?? '未知',
                'total_licenses' => $stat->total,
                'active_licenses' => $stat->active_count,
                'total_events' => $eventCount,
            ];
        }

        return $result;
    }

    /**
     * 获取所有可用的违规类型
     */
    public function getViolationTypes(): array
    {
        return [
            'excessive_activations' => '超量激活',
            'expired_use' => '过期使用',
            'tampered' => '信息篡改',
            'blacklisted_device' => '黑名单设备',
            'suspicious_location' => '异常地理位置',
        ];
    }

    // ─── M3-09 增强方法 ───

    /**
     * 概要统计卡片
     */
    public function getSummary(?int $tenantId = null): array
    {
        $licenseQuery = License::query();
        if ($tenantId) {
            $licenseQuery->where('tenant_id', $tenantId);
        }

        $totalLicenses = (clone $licenseQuery)->count();
        $activeLicenses = (clone $licenseQuery)->where('status', 'active')->count();
        $expiredLicenses = (clone $licenseQuery)->where('status', 'expired')->count();
        $revokedLicenses = (clone $licenseQuery)->whereIn('status', ['revoked', 'blacklisted'])->count();
        $suspendedLicenses = (clone $licenseQuery)->where('status', 'suspended')->count();
        $pendingLicenses = (clone $licenseQuery)->where('status', 'pending')->count();

        $activationRate = $totalLicenses > 0
            ? round(($activeLicenses / $totalLicenses) * 100, 1) : 0;

        $deviceQuery = Device::query();
        if ($tenantId) {
            $deviceQuery->where('tenant_id', $tenantId);
        }
        $totalDevices = (clone $deviceQuery)->count();
        $blacklistedDevices = (clone $deviceQuery)->where('is_blacklisted', true)->count();

        $eventQuery = LicenseAnalyticsEvent::query();
        if ($tenantId) {
            $eventQuery->where('tenant_id', $tenantId);
        }
        $totalEvents = (clone $eventQuery)->count();
        $totalViolations = (clone $eventQuery)->where('event_type', 'violation')->count();

        return [
            'total_licenses' => $totalLicenses,
            'active_licenses' => $activeLicenses,
            'expired_licenses' => $expiredLicenses,
            'revoked_licenses' => $revokedLicenses,
            'suspended_licenses' => $suspendedLicenses,
            'pending_licenses' => $pendingLicenses,
            'activation_rate' => $activationRate,
            'total_devices' => $totalDevices,
            'blacklisted_devices' => $blacklistedDevices,
            'total_events' => $totalEvents,
            'total_violations' => $totalViolations,
            'total_activations' => (clone $licenseQuery)->withCount('activations')->get()->sum('activations_count'),
        ];
    }

    /**
     * License 类型分布
     */
    public function getLicenseTypeDistribution(?int $tenantId = null): array
    {
        $query = License::query()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->orderByDesc('count');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->toArray();
    }

    /**
     * License 状态分布
     */
    public function getLicenseStatusDistribution(?int $tenantId = null): array
    {
        $query = License::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->orderByDesc('count');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->toArray();
    }

    /**
     * 设备平台分布详细
     */
    public function getDevicePlatformDistribution(?int $tenantId = null): array
    {
        $query = Device::query()
            ->selectRaw('platform, count(*) as count')
            ->whereNotNull('platform')
            ->groupBy('platform')
            ->orderByDesc('count');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->toArray();
    }

    /**
     * License 创建趋势（近N天逐日）
     */
    public function getLicenseCreationTrend(int $days = 30, ?int $tenantId = null): array
    {
        $query = License::query()
            ->where('created_at', '>=', now()->subDays($days));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $raw = (clone $query)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return $this->fillDateRange($raw, $days);
    }

    /**
     * 综合看板
     */
    public function getLicenseDashboard(?int $tenantId = null): array
    {
        return [
            'summary' => $this->getSummary($tenantId),
            'type_distribution' => $this->getLicenseTypeDistribution($tenantId),
            'status_distribution' => $this->getLicenseStatusDistribution($tenantId),
            'platform_distribution' => $this->getDevicePlatformDistribution($tenantId),
            'activation_trend' => $this->getEventTrend('activation', 30, $tenantId),
            'license_creation_trend' => $this->getLicenseCreationTrend(30, $tenantId),
            'geo_distribution' => $this->getGeoDistribution($tenantId),
            'utilization' => $this->getUtilizationStats($tenantId),
            'violations_by_type' => $this->getViolationsByType($tenantId),
        ];
    }

    /**
     * 按类型统计违规
     */
    protected function getViolationsByType(?int $tenantId = null): array
    {
        $query = LicenseAnalyticsEvent::query()
            ->where('event_type', 'violation')
            ->selectRaw('violation_type, count(*) as count')
            ->groupBy('violation_type')
            ->orderByDesc('count');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->pluck('count', 'violation_type')->toArray();
    }

    /**
     * 填充日期范围
     */
    protected function fillDateRange(array $raw, int $days): array
    {
        $result = [];
        $start = now()->subDays($days - 1)->startOfDay();
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'count' => (int) ($raw[$date] ?? 0),
            ];
        }
        return $result;
    }
}
