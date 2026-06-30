<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceGeoRecord;
use App\Models\TenantGeoStat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 设备地理位置记录与地域分布统计服务 (M2-26)
 */
class GeoLocationService
{
    /**
     * 解析 IP 归属地
     */
    public function locate(string $ip): ?array
    {
        if ($this->isPrivateIp($ip)) {
            return [
                'ip' => $ip,
                'country' => '内网',
                'country_code' => 'LAN',
                'region' => null,
                'city' => null,
                'isp' => null,
                'latitude' => null,
                'longitude' => null,
                'timezone' => null,
            ];
        }

        $cacheKey = 'geo_ip:' . md5($ip);
        $ttl = config('geo-location.ip_resolver.cache_ttl', 86400);

        return Cache::remember($cacheKey, $ttl, function () use ($ip) {
            $driver = config('geo-location.ip_resolver.driver', 'local');

            return match ($driver) {
                'api' => $this->resolveViaApi($ip),
                default => $this->resolveViaApi($ip), // fallback to API
            };
        });
    }

    /**
     * 记录设备地理位置
     */
    public function record(
        int $tenantId,
        string $ip,
        ?int $deviceId = null,
        ?int $licenseId = null,
        ?int $customerId = null,
        string $source = 'activation'
    ): DeviceGeoRecord {
        $location = $this->locate($ip);
        $blacklistCountries = config('geo-location.blacklist.countries', []);
        $isBlacklisted = in_array($location['country_code'] ?? '', $blacklistCountries);

        $record = DeviceGeoRecord::create([
            'tenant_id' => $tenantId,
            'device_id' => $deviceId,
            'license_id' => $licenseId,
            'customer_id' => $customerId,
            'ip_address' => $ip,
            'country' => $location['country'] ?? null,
            'country_code' => $location['country_code'] ?? null,
            'region' => $location['region'] ?? null,
            'city' => $location['city'] ?? null,
            'isp' => $location['isp'] ?? null,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'timezone' => $location['timezone'] ?? null,
            'source' => $source,
            'is_blacklisted' => $isBlacklisted,
        ]);

        // 更新 devices 表缓存字段
        if ($deviceId) {
            Device::where('id', $deviceId)->update([
                'last_ip' => $ip,
                'last_country' => $location['country'] ?? null,
                'last_country_code' => $location['country_code'] ?? null,
                'last_city' => $location['city'] ?? null,
                'last_latitude' => $location['latitude'] ?? null,
                'last_longitude' => $location['longitude'] ?? null,
            ]);
        }

        // 异步更新统计数据
        $this->updateStatsAsync($tenantId, $location);

        return $record;
    }

    /**
     * 仪表盘总览
     */
    public function getDashboard(int $tenantId): array
    {
        $today = Carbon::today();

        $totalRecords = DeviceGeoRecord::where('tenant_id', $tenantId)->count();
        $coveredCountries = DeviceGeoRecord::where('tenant_id', $tenantId)
            ->whereNotNull('country_code')
            ->distinct('country_code')
            ->count('country_code');

        $todayActivations = DeviceGeoRecord::where('tenant_id', $tenantId)
            ->whereDate('created_at', $today)
            ->count();

        $blacklistedCount = DeviceGeoRecord::where('tenant_id', $tenantId)
            ->where('is_blacklisted', true)
            ->count();

        $topCountries = DeviceGeoRecord::where('tenant_id', $tenantId)
            ->whereNotNull('country_code')
            ->selectRaw('country_code, country, COUNT(*) as total')
            ->groupBy('country_code', 'country')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'total_records' => $totalRecords,
            'covered_countries' => $coveredCountries,
            'today_activations' => $todayActivations,
            'blacklisted_count' => $blacklistedCount,
            'top_countries' => $topCountries,
        ];
    }

    /**
     * 地域分布统计（用于地图可视化）
     */
    public function getRegionalStats(int $tenantId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = DeviceGeoRecord::where('tenant_id', $tenantId)
            ->whereNotNull('country_code')
            ->selectRaw('country_code, country, COUNT(*) as total, COUNT(DISTINCT device_id) as device_count');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59']);
        }

        return $query->groupBy('country_code', 'country')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * 世界地图数据（经纬度点集）
     */
    public function getMapData(int $tenantId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = DeviceGeoRecord::where('tenant_id', $tenantId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('country, country_code, city, latitude, longitude, COUNT(*) as total');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59']);
        }

        return $query->groupBy('country', 'country_code', 'city', 'latitude', 'longitude')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * 黑名单国家列表
     */
    public function getBlacklist(): array
    {
        return config('geo-location.blacklist.countries', []);
    }

    /**
     * 更新黑名单
     */
    public function updateBlacklist(array $countryCodes): void
    {
        // 写入 .env 或持久化存储 — 此处存储到 config 缓存
        $path = config_path('geo-location.php');
        $config = include $path;
        $config['blacklist']['countries'] = $countryCodes;

        $export = '<?php return ' . var_export($config, true) . ';' . PHP_EOL;
        file_put_contents($path, $export);

        // 清除缓存
        if (app()->configurationIsCached()) {
            app()->make('config')->set('geo-location.blacklist.countries', $countryCodes);
        }
    }

    /**
     * 地理位置记录列表
     */
    public function getRecords(int $tenantId, array $filters = []): Collection
    {
        $query = DeviceGeoRecord::where('tenant_id', $tenantId)
            ->with(['device:id,fingerprint,platform']);

        if (!empty($filters['country_code'])) {
            $query->where('country_code', $filters['country_code']);
        }
        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (!empty($filters['is_blacklisted'])) {
            $query->where('is_blacklisted', true);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('ip_address', 'like', "%{$filters['search']}%")
                  ->orWhere('country', 'like', "%{$filters['search']}%")
                  ->orWhere('city', 'like', "%{$filters['search']}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 20);
    }

    // ─── 私有方法 ───

    private function resolveViaApi(string $ip): ?array
    {
        $apiUrl = config('geo-location.ip_resolver.api.url', 'https://api.ip.sb/geoip/{ip}');
        $apiKey = config('geo-location.ip_resolver.api.key', '');
        $timeout = config('geo-location.ip_resolver.api.timeout', 5);

        $url = str_replace('{ip}', $ip, $apiUrl);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($apiKey ? ['Key' => $apiKey] : [])
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'ip' => $ip,
                    'country' => $data['country'] ?? $data['country_name'] ?? null,
                    'country_code' => $data['country_code'] ?? $data['countryCode'] ?? null,
                    'region' => $data['region'] ?? $data['regionName'] ?? null,
                    'city' => $data['city'] ?? null,
                    'isp' => $data['isp'] ?? $data['org'] ?? null,
                    'latitude' => $data['latitude'] ?? $data['lat'] ?? null,
                    'longitude' => $data['longitude'] ?? $data['lon'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('GeoLocation API 解析失败', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return [
            'ip' => $ip,
            'country' => null,
            'country_code' => null,
            'region' => null,
            'city' => null,
            'isp' => null,
            'latitude' => null,
            'longitude' => null,
            'timezone' => null,
        ];
    }

    private function isPrivateIp(string $ip): bool
    {
        $filtered = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        return $filtered === false;
    }

    private function updateStatsAsync(int $tenantId, ?array $location): void
    {
        if (empty($location['country_code'])) {
            return;
        }

        // 使用队列或直接更新聚合表
        try {
            TenantGeoStat::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'country_code' => $location['country_code'],
                    'stat_date' => Carbon::today(),
                ],
                [
                    'country' => $location['country'] ?? $location['country_code'],
                    'region' => $location['region'] ?? null,
                    'device_count' => \DB::raw('device_count + 1'),
                    'activation_count' => \DB::raw('activation_count + 1'),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('更新地域统计失败', ['error' => $e->getMessage()]);
        }
    }
}
