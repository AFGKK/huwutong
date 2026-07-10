<?php

namespace App\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\SdkHeartbeat;
use App\Models\SdkTelemetryAggregate;
use App\Models\SdkTelemetryEvent;
use App\Models\SdkVersionSnapshot;
use App\Support\DbSql;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * SDK 心跳/Telemetry 上报服务
 *
 * SDK 客户端定期上报：
 *  - 健康状态（CPU/内存/磁盘）
 *  - SDK 版本分布
 *  - 功能使用统计（脱敏）
 *  - 运行环境信息
 *
 * 脱敏原则：不上报客户业务数据，仅上报版本号、性能指标、功能启用状态。
 */
class TelemetryService
{
    /**
     * 心跳上报频率限制（秒）
     */
    const REPORT_INTERVAL = 60;

    /**
     * 聚合缓存 TTL（秒）
     */
    const AGG_CACHE_TTL = 3600;

    /**
     * 处理 SDK 心跳上报
     *
     * @param License $license
     * @param Device|null $device
     * @param array $payload 心跳数据
     * @return SdkHeartbeat
     */
    public function processHeartbeat(License $license, ?Device $device, array $payload): SdkHeartbeat
    {
        $heartbeat = SdkHeartbeat::create([
            'license_id' => $license->id,
            'device_id' => $device?->id,
            'tenant_id' => $license->tenant_id,
            'sdk_version' => $payload['sdk_version'] ?? null,
            'sdk_language' => $payload['sdk_language'] ?? null,
            'sdk_platform' => $payload['platform'] ?? $payload['sdk_platform'] ?? null,
            'sdk_arch' => $payload['arch'] ?? $payload['sdk_arch'] ?? null,
            'hostname' => $payload['hostname'] ?? null,
            'ip_address' => $payload['ip'] ?? request()->ip(),
            'uptime_seconds' => $payload['uptime'] ?? $payload['uptime_seconds'] ?? null,
            'runtime_version' => $payload['runtime_version'] ?? null,
            'health_status' => $payload['health'] ?? $payload['health_status'] ?? null,
            'features_active' => $payload['features'] ?? $payload['features_active'] ?? null,
            'metrics' => $payload['metrics'] ?? null,
            'reported_at' => $payload['reported_at'] ?? now(),
        ]);

        // 更新设备最后活跃时间
        if ($device) {
            $device->updateQuietly(['last_seen_at' => $heartbeat->reported_at]);
        }

        // 异步更新聚合
        $this->updateAggregates($heartbeat);

        return $heartbeat;
    }

    /**
     * 处理 Telemtry 事件上报
     *
     * @param License $license
     * @param array $events 事件列表 [{event_type, event_name, event_data?, count?, occurred_at?}]
     * @return int 处理的事件数
     */
    public function processEvents(License $license, array $events): int
    {
        $count = 0;

        foreach ($events as $event) {
            $eventType = $event['event_type'] ?? 'custom';
            $eventName = $event['event_name'] ?? null;

            // 脱敏：移除可能包含业务数据的字段
            $eventData = $this->sanitizeEventData($event['event_data'] ?? []);

            SdkTelemetryEvent::create([
                'license_id' => $license->id,
                'tenant_id' => $license->tenant_id,
                'event_type' => $eventType,
                'event_name' => $eventName,
                'event_data' => $eventData,
                'count' => $event['count'] ?? 1,
                'occurred_at' => $event['occurred_at'] ?? now(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * 脱敏事件数据
     */
    protected function sanitizeEventData(array $data): array
    {
        // 敏感字段黑名单
        $sensitiveKeys = ['email', 'password', 'token', 'secret', 'key', 'username', 'phone'];

        $sanitized = [];
        foreach ($data as $key => $value) {
            // 递归处理嵌套
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeEventData($value);
                continue;
            }

            // 脱敏敏感键名
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $sanitized[$key] = '***';
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * 更新聚合统计
     */
    protected function updateAggregates(SdkHeartbeat $heartbeat): void
    {
        $today = now()->toDateString();
        $now = DbSql::now();

        try {
            $upsert = function (array $params, bool $incrementCount = true) use ($now, $today): void {
                $driver = DbSql::driver();
                if ($driver === 'pgsql') {
                    $countUpdate = $incrementCount
                        ? 'count = sdk_telemetry_aggregates.count + 1'
                        : 'count = sdk_telemetry_aggregates.count';
                    DB::statement(
                        "INSERT INTO sdk_telemetry_aggregates (tenant_id, metric_key, dimension, dimension_value, count, agg_date, created_at, updated_at)
                         VALUES (?, ?, ?, ?, 1, ?, {$now}, {$now})
                         ON CONFLICT (tenant_id, metric_key, dimension, dimension_value, agg_date)
                         DO UPDATE SET {$countUpdate}, updated_at = {$now}",
                        $params
                    );

                    return;
                }

                DB::statement(
                    "INSERT INTO sdk_telemetry_aggregates (tenant_id, metric_key, dimension, dimension_value, count, agg_date, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 1, ?, {$now}, {$now})
                     ON DUPLICATE KEY UPDATE count = count + ".($incrementCount ? '1' : '0').", updated_at = {$now}",
                    $params
                );
            };

            // SDK 版本分布聚合
            if ($heartbeat->sdk_language && $heartbeat->sdk_version) {
                $upsert([$heartbeat->tenant_id, 'sdk_version', 'sdk_language', $heartbeat->sdk_language, $today]);
                $upsert([$heartbeat->tenant_id, 'sdk_version', 'sdk_version', $heartbeat->sdk_version, $today]);
            }

            // 平台分布聚合
            if ($heartbeat->sdk_platform) {
                $upsert([$heartbeat->tenant_id, 'platform', 'platform', $heartbeat->sdk_platform, $today]);
            }

            // 运行时版本分布
            if ($heartbeat->runtime_version) {
                $upsert([$heartbeat->tenant_id, 'runtime', 'runtime_version', $heartbeat->runtime_version, $today]);
            }

            // 活跃 License 计数（分布式锁）
            $lockKey = "telemetry:active:{$heartbeat->license_id}";
            if (! Cache::has($lockKey)) {
                Cache::put($lockKey, true, now()->addMinutes(30));
                $upsert([$heartbeat->tenant_id, 'active_licenses', 'license_id', (string) $heartbeat->license_id, $today], false);
            }
        } catch (\Exception $e) {
            logger()->warning('Failed to update telemetry aggregate', [
                'heartbeat_id' => $heartbeat->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── 查询与统计 ───

    /**
     * 获取心跳历史
     */
    public function getHeartbeatHistory(?int $licenseId = null, ?int $tenantId = null, array $options = []): Collection
    {
        $query = SdkHeartbeat::query();

        if ($licenseId) {
            $query->where('license_id', $licenseId);
        }
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $dateFrom = $options['date_from'] ?? now()->subDays(7);
        $dateTo = $options['date_to'] ?? now();

        $query->whereBetween('reported_at', [$dateFrom, $dateTo]);
        $query->orderBy('reported_at', 'desc');

        $limit = $options['limit'] ?? 100;
        $query->limit($limit);

        return $query->get();
    }

    /**
     * 获取 SDK 版本分布
     */
    public function getVersionDistribution(?int $tenantId = null): array
    {
        $cacheKey = 'telemetry:version_dist:' . ($tenantId ?? 'global');

        return Cache::remember($cacheKey, self::AGG_CACHE_TTL, function () use ($tenantId) {
            $query = SdkTelemetryAggregate::where('metric_key', 'sdk_version')
                ->where('agg_date', now()->toDateString());

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $records = $query->select('dimension', 'dimension_value', DB::raw('SUM(count) as total'))
                ->groupBy('dimension', 'dimension_value')
                ->orderByDesc('total')
                ->get();

            $result = [];
            foreach ($records as $record) {
                $dimension = $record->dimension; // sdk_language or sdk_version
                if (!isset($result[$dimension])) {
                    $result[$dimension] = [];
                }
                $result[$dimension][] = [
                    'value' => $record->dimension_value,
                    'count' => (int) $record->total,
                ];
            }

            return $result;
        });
    }

    /**
     * 获取 Telemetry 概览仪表盘数据
     */
    public function getDashboardStats(?int $tenantId = null): array
    {
        $today = now()->toDateString();
        $last7d = now()->subDays(7)->toDateString();

        $query = SdkHeartbeat::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $totalHeartbeats = (clone $query)->count();
        $todayHeartbeats = (clone $query)->whereDate('reported_at', $today)->count();
        $weeklyHeartbeats = (clone $query)->whereDate('reported_at', '>=', $last7d)->count();

        $uniqueLicenses = (clone $query)
            ->select(DB::raw('COUNT(DISTINCT license_id)'))
            ->value('COUNT(DISTINCT license_id)') ?? 0;

        $uniqueDevices = (clone $query)
            ->select(DB::raw('COUNT(DISTINCT device_id)'))
            ->whereNotNull('device_id')
            ->value('COUNT(DISTINCT device_id)') ?? 0;

        $languageBreakdown = (clone $query)
            ->select('sdk_language', DB::raw('COUNT(*) as count'))
            ->whereNotNull('sdk_language')
            ->groupBy('sdk_language')
            ->orderByDesc('count')
            ->get()
            ->toArray();

        $latestVersions = (clone $query)
            ->select('sdk_language', 'sdk_version')
            ->whereNotNull('sdk_version')
            ->whereNotNull('sdk_language')
            ->orderBy('reported_at', 'desc')
            ->limit(20)
            ->get()
            ->groupBy('sdk_language')
            ->map(fn($items) => $items->pluck('sdk_version')->unique()->take(3))
            ->toArray();

        return [
            'total_heartbeats' => $totalHeartbeats,
            'today_heartbeats' => $todayHeartbeats,
            'weekly_heartbeats' => $weeklyHeartbeats,
            'unique_licenses' => $uniqueLicenses,
            'unique_devices' => $uniqueDevices,
            'language_breakdown' => $languageBreakdown,
            'latest_versions' => $latestVersions,
        ];
    }

    /**
     * 获取异常心跳（不健康状态）
     */
    public function getUnhealthyHeartbeats(?int $tenantId = null, int $limit = 50): Collection
    {
        $query = SdkHeartbeat::whereNotNull('health_status')
            ->orderBy('reported_at', 'desc')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->filter(function ($heartbeat) {
            $health = $heartbeat->health_status;
            return ($health['cpu'] ?? 100) > 90
                || ($health['memory'] ?? 100) > 90
                || ($health['disk'] ?? 100) > 95;
        })->values();
    }

    /**
     * 获取 Telemetry 事件统计
     */
    public function getEventStats(?int $tenantId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = SdkTelemetryEvent::select(
            'event_type',
            'event_name',
            DB::raw('SUM(count) as total_count'),
            DB::raw('COUNT(DISTINCT license_id) as unique_licenses'),
        )->groupBy('event_type', 'event_name');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($dateFrom) {
            $query->where('occurred_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('occurred_at', '<=', $dateTo);
        }

        return $query->orderByDesc('total_count')
            ->get()
            ->toArray();
    }

    /**
     * 创建版本分布日快照（定时任务用）
     */
    public function snapshotVersionDistribution(): int
    {
        $snapshotDate = now()->toDateString();
        $snapshotDateObj = now()->toDateString();
        $count = 0;

        // 按租户、SDK 语言、版本分组统计最近 24h 活跃实例
        $groups = SdkHeartbeat::whereDate('reported_at', now()->subDay()->toDateString())
            ->select(
                'tenant_id',
                'sdk_language',
                'sdk_version',
                DB::raw('COUNT(DISTINCT device_id) as instance_count'),
            )
            ->whereNotNull('sdk_language')
            ->whereNotNull('sdk_version')
            ->groupBy('tenant_id', 'sdk_language', 'sdk_version')
            ->get();

        foreach ($groups as $group) {
            try {
                SdkVersionSnapshot::create([
                    'tenant_id' => $group->tenant_id,
                    'sdk_language' => $group->sdk_language,
                    'sdk_version' => $group->sdk_version,
                    'instance_count' => $group->instance_count,
                    'snapshot_date' => $snapshotDate,
                ]);
                $count++;
            } catch (\Exception $e) {
                // 跳过重复
            }
        }

        return $count;
    }

    /**
     * 获取版本快照历史趋势
     */
    public function getVersionSnapshotTrend(?int $tenantId = null, int $days = 30): array
    {
        $query = SdkVersionSnapshot::select(
            'snapshot_date',
            'sdk_language',
            'sdk_version',
            DB::raw('SUM(instance_count) as total_instances'),
        )->where('snapshot_date', '>=', now()->subDays($days)->toDateString());

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->groupBy('snapshot_date', 'sdk_language', 'sdk_version')
            ->orderBy('snapshot_date')
            ->get()
            ->toArray();
    }

    /**
     * 检查心跳上报频率限制
     * 同一个 license_id + device_id 至少间隔 60 秒
     */
    public function checkReportInterval(int $licenseId, ?int $deviceId): bool
    {
        $lastHeartbeat = SdkHeartbeat::where('license_id', $licenseId)
            ->when($deviceId, fn($q) => $q->where('device_id', $deviceId))
            ->latest('reported_at')
            ->first();

        if (!$lastHeartbeat) {
            return true;
        }

        return $lastHeartbeat->reported_at->diffInSeconds(now()) >= self::REPORT_INTERVAL;
    }
}
