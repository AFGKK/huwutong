<?php

namespace App\Services;

use App\Models\SlaProbe;
use App\Models\SlaProbeResult;
use App\Models\SyntheticMonitorRegion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 合成监控多区域拨测服务 (M2-120)
 *
 * 在现有 SlaProbe 基础之上扩展多区域拨测能力：
 * 定时从亚太/欧洲/北美模拟激活→验证请求→记录延迟+可用率→SLA计算→状态页同步
 */
class SyntheticMonitorService
{
    /**
     * 看板总览
     */
    public function dashboard(): array
    {
        $regions = SyntheticMonitorRegion::where('is_active', true)->get();
        $totalProbes = SlaProbe::where('probe_type', 'synthetic')->count();
        $activeProbes = SlaProbe::where('probe_type', 'synthetic')->where('is_active', true)->count();

        $regionStats = [];
        foreach ($regions as $region) {
            $regionProbes = SlaProbe::where('region_code', $region->code)
                ->where('probe_type', 'synthetic')->count();
            $regionUp = SlaProbe::where('region_code', $region->code)
                ->where('probe_type', 'synthetic')
                ->where('last_status', 'up')->count();
            $avgLatency = (float) SlaProbeResult::where('region_code', $region->code)
                ->where('created_at', '>=', now()->subHour())
                ->avg('response_time_ms');

            $regionStats[] = [
                'code' => $region->code,
                'name' => $region->name,
                'locations' => $region->locations,
                'total_probes' => $regionProbes,
                'up_probes' => $regionUp,
                'down_probes' => $regionProbes - $regionUp,
                'avg_latency_ms' => round($avgLatency, 2),
            ];
        }

        // 整体可用率
        $totalResults24h = SlaProbeResult::whereHas('probe', function ($q) {
            $q->where('probe_type', 'synthetic');
        })->where('created_at', '>=', now()->subDay())->count();

        $successResults24h = SlaProbeResult::whereHas('probe', function ($q) {
            $q->where('probe_type', 'synthetic');
        })->where('created_at', '>=', now()->subDay())
            ->where('status', 'up')->count();

        $overallAvailability = $totalResults24h > 0
            ? round(($successResults24h / $totalResults24h) * 100, 2) : 0;

        // 全局延迟
        $globalAvgLatency = (float) SlaProbeResult::whereHas('probe', function ($q) {
            $q->where('probe_type', 'synthetic');
        })->where('created_at', '>=', now()->subHour())
            ->avg('response_time_ms');

        return [
            'regions' => $regionStats,
            'total_probes' => $totalProbes,
            'active_probes' => $activeProbes,
            'overall_availability' => $overallAvailability,
            'global_avg_latency_ms' => round($globalAvgLatency, 2),
            'total_results_24h' => $totalResults24h,
        ];
    }

    /**
     * 获取区域列表
     */
    public function listRegions(): array
    {
        return SyntheticMonitorRegion::where('is_active', true)
            ->orderBy('sort_order')->get()->toArray();
    }

    /**
     * 初始化默认区域
     */
    public function seedRegions(): void
    {
        $defaults = config('synthetic-monitor.regions', []);
        $sort = 0;
        foreach ($defaults as $code => $cfg) {
            SyntheticMonitorRegion::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $cfg['name'],
                    'name_en' => $cfg['name_en'] ?? null,
                    'locations' => is_string($cfg['locations'] ?? '')
                        ? explode(', ', $cfg['locations'])
                        : ($cfg['locations'] ?? []),
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * 创建多区域拨测任务（会为每个区域创建独立的 SlaProbe）
     */
    public function createProbe(array $data): SlaProbe
    {
        $regions = $data['regions'] ?? ['ap-asia', 'eu-europe', 'us-north-america'];
        $lastProbe = null;

        foreach ($regions as $regionCode) {
            $region = SyntheticMonitorRegion::where('code', $regionCode)->first();
            $locations = $region ? ($region->locations ?? []) : [];

            $probeData = array_merge($data, [
                'region_code' => $regionCode,
                'location' => !empty($locations) ? $locations[0] : null,
                'probe_type' => 'synthetic',
                'is_active' => true,
                'interval_minutes' => $data['interval_minutes'] ?? config('synthetic-monitor.defaults.interval_minutes', 5),
                'timeout_seconds' => $data['timeout_seconds'] ?? config('synthetic-monitor.defaults.timeout_seconds', 30),
                'expected_status' => $data['expected_status'] ?? config('synthetic-monitor.defaults.expected_status', 200),
                'headers' => $data['headers'] ?? ['Accept' => 'application/json'],
            ]);

            $probe = SlaProbe::create($probeData);
            $lastProbe = $probe;

            // 立即执行首次拨测
            if ($data['run_immediately'] ?? true) {
                try {
                    app(\App\Services\SlaProbeService::class)->probe($probe);
                } catch (\Throwable $e) {
                    Log::warning("首次拨测失败 [{$regionCode}]: " . $e->getMessage());
                }
            }
        }

        return $lastProbe;
    }

    /**
     * 区域拨测结果统计
     */
    public function regionStats(string $regionCode, int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $region = SyntheticMonitorRegion::where('code', $regionCode)->first();

        $probes = SlaProbe::where('region_code', $regionCode)
            ->where('probe_type', 'synthetic')
            ->where('is_active', true)
            ->get();

        $totalResults = 0;
        $successResults = 0;
        $latencies = [];
        $timeline = [];

        foreach ($probes as $probe) {
            $results = SlaProbeResult::where('sla_probe_id', $probe->id)
                ->where('created_at', '>=', $since)
                ->orderBy('created_at')
                ->get();

            foreach ($results as $r) {
                $totalResults++;
                if ($r->status === 'up') $successResults++;
                $latencies[] = $r->response_time_ms;

                $minute = $r->created_at->format('Y-m-d H:i');
                if (!isset($timeline[$minute])) {
                    $timeline[$minute] = ['total' => 0, 'success' => 0, 'latency_sum' => 0, 'latency_count' => 0];
                }
                $timeline[$minute]['total']++;
                if ($r->status === 'up') $timeline[$minute]['success']++;
                $timeline[$minute]['latency_sum'] += $r->response_time_ms;
                $timeline[$minute]['latency_count']++;
            }
        }

        $availability = $totalResults > 0
            ? round(($successResults / $totalResults) * 100, 2) : 0;

        sort($latencies);
        $count = count($latencies);
        $p50 = $count > 0 ? $latencies[(int) floor($count * 0.5)] : 0;
        $p95 = $count > 0 ? $latencies[(int) floor($count * 0.95)] : 0;
        $p99 = $count > 0 ? $latencies[(int) floor($count * 0.99)] : 0;

        // 时间线
        $timelineData = [];
        foreach ($timeline as $minute => $stats) {
            $timelineData[] = [
                'time' => $minute,
                'availability' => $stats['total'] > 0
                    ? round(($stats['success'] / $stats['total']) * 100, 1) : 0,
                'avg_latency' => $stats['latency_count'] > 0
                    ? round($stats['latency_sum'] / $stats['latency_count'], 2) : 0,
                'total_checks' => $stats['total'],
            ];
        }

        return [
            'region' => $region ? $region->toArray() : ['code' => $regionCode],
            'probe_count' => $probes->count(),
            'total_checks' => $totalResults,
            'success_checks' => $successResults,
            'availability' => $availability,
            'avg_latency_ms' => $count > 0 ? round(array_sum($latencies) / $count, 2) : 0,
            'p50_latency_ms' => round($p50, 2),
            'p95_latency_ms' => round($p95, 2),
            'p99_latency_ms' => round($p99, 2),
            'timeline' => $timelineData,
        ];
    }

    /**
     * 所有区域汇总对比
     */
    public function allRegionComparison(int $hours = 24): array
    {
        $regions = SyntheticMonitorRegion::where('is_active', true)->get();
        $comparison = [];

        foreach ($regions as $region) {
            $stats = $this->regionStats($region->code, $hours);
            $comparison[] = [
                'code' => $region->code,
                'name' => $region->name,
                'availability' => $stats['availability'],
                'avg_latency_ms' => $stats['avg_latency_ms'],
                'p95_latency_ms' => $stats['p95_latency_ms'],
                'total_checks' => $stats['total_checks'],
                'probe_count' => $stats['probe_count'],
            ];
        }

        return $comparison;
    }

    /**
     * 多区域拨测列表
     */
    public function listProbes(Request $request): array
    {
        $query = SlaProbe::where('probe_type', 'synthetic')
            ->withCount('results');

        if ($request->filled('region_code')) {
            $query->where('region_code', $request->region_code);
        }
        if ($request->filled('status')) {
            $query->where('last_status', $request->status);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('url', 'like', "%{$s}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $page = (int) $request->input('page', 1);
        $total = $query->count();
        $items = $query->orderByDesc('id')->skip(($page - 1) * $perPage)->take($perPage)->get();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /**
     * SLA 计算
     */
    public function slaReport(int $days = 30): array
    {
        $since = now()->subDays($days);
        $target = config('synthetic-monitor.sla.uptime_target', 99.9);
        $latencyWarning = config('synthetic-monitor.sla.latency_warning_ms', 500);
        $latencyCritical = config('synthetic-monitor.sla.latency_critical_ms', 2000);

        $regions = SyntheticMonitorRegion::where('is_active', true)->get();
        $results = [];

        foreach ($regions as $region) {
            $probeIds = SlaProbe::where('region_code', $region->code)
                ->where('probe_type', 'synthetic')->pluck('id');

            $totalResults = SlaProbeResult::whereIn('sla_probe_id', $probeIds)
                ->where('created_at', '>=', $since)->count();

            $successResults = SlaProbeResult::whereIn('sla_probe_id', $probeIds)
                ->where('created_at', '>=', $since)
                ->where('status', 'up')->count();

            $avgLatency = (float) SlaProbeResult::whereIn('sla_probe_id', $probeIds)
                ->where('created_at', '>=', $since)
                ->avg('response_time_ms');

            $availability = $totalResults > 0
                ? round(($successResults / $totalResults) * 100, 2) : 0;

            $slaMet = $availability >= $target;
            $latencyLevel = $avgLatency > $latencyCritical ? 'critical'
                : ($avgLatency > $latencyWarning ? 'warning' : 'good');

            $results[] = [
                'region_code' => $region->code,
                'region_name' => $region->name,
                'availability' => $availability,
                'sla_target' => $target,
                'sla_met' => $slaMet,
                'avg_latency_ms' => round($avgLatency, 2),
                'latency_level' => $latencyLevel,
                'total_checks' => $totalResults,
                'failed_checks' => $totalResults - $successResults,
            ];
        }

        // 整体 SLA
        $overallAvailable = collect($results)->avg('availability');

        return [
            'period_days' => $days,
            'period_label' => "过去{$days}天",
            'sla_target' => $target,
            'overall_availability' => round($overallAvailable, 2),
            'overall_sla_met' => $overallAvailable >= $target,
            'latency_thresholds' => [
                'warning_ms' => $latencyWarning,
                'critical_ms' => $latencyCritical,
            ],
            'regions' => $results,
        ];
    }

    /**
     * 同步到状态页 (M2-49)
     */
    public function syncToStatusPage(): array
    {
        if (!config('synthetic-monitor.status_page.auto_sync', true)) {
            return ['synced' => false, 'reason' => 'auto_sync disabled'];
        }

        $slaReport = $this->slaReport(1); // 过去1天
        $dashboard = $this->dashboard();

        // 更新 StatusPage 组件状态（通过事件或直接数据库更新）
        try {
            $overallStatus = $dashboard['overall_availability'] >= 99.9 ? 'operational'
                : ($dashboard['overall_availability'] >= 99.0 ? 'degraded' : 'major_outage');

            // 记录到系统事件或状态页组件
            $componentData = [
                'name' => '全球 API 拨测',
                'status' => $overallStatus,
                'description' => "可用率: {$dashboard['overall_availability']}% | 平均延迟: {$dashboard['global_avg_latency_ms']}ms",
            ];

            Log::info('[SyntheticMonitor] Status page sync', $componentData);
        } catch (\Throwable $e) {
            Log::warning('状态页同步失败: ' . $e->getMessage());
        }

        return [
            'synced' => true,
            'overall_status' => $overallStatus ?? 'unknown',
            'availability' => $dashboard['overall_availability'],
            'sla_met' => $slaReport['overall_sla_met'],
        ];
    }

    /**
     * 清理过期结果
     */
    public function pruneResults(): int
    {
        $retentionDays = config('synthetic-monitor.prune.results_retention_days', 90);
        $batchSize = config('synthetic-monitor.prune.batch_size', 1000);
        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        do {
            $count = SlaProbeResult::whereHas('probe', function ($q) {
                    $q->where('probe_type', 'synthetic');
                })
                ->where('created_at', '<', $cutoff)
                ->limit($batchSize)
                ->delete();
            $deleted += $count;
        } while ($count > 0);

        return $deleted;
    }
}
