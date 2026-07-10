<?php

namespace App\Services;

use App\Models\EventDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;

/**
 * Webhook 监控服务
 *
 * 提供端点健康状态、投递统计、延迟趋势、失败分析等监控指标。
 * 数据来源为已有的 event_deliveries 和 webhook_events 表。
 */
class WebhookMonitorService
{
    /**
     * 监控概览 — 所有端点的汇总指标
     */
    public function overview(int $tenantId): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $oneHourAgo = $now->copy()->subHour();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $endpointsCount = WebhookEndpoint::where('tenant_id', $tenantId)->count();
        $activeEndpoints = WebhookEndpoint::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $pausedEndpoints = WebhookEndpoint::where('tenant_id', $tenantId)->where('is_paused', true)->count();

        // 今日事件统计
        $todayEvents = WebhookEvent::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $todayStart);

        $todayTotal = (clone $todayEvents)->count();
        $todayDelivered = (clone $todayEvents)->where('status', 'delivered')->count();
        $todayFailed = (clone $todayEvents)->whereIn('status', ['dead_letter'])->count();
        $todayPending = (clone $todayEvents)->whereIn('status', ['pending', 'retrying'])->count();

        // 近7天投递量趋势
        $weeklyTrend = WebhookEvent::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered', ['delivered'])
            ->selectRaw('SUM(CASE WHEN status IN (?,?) THEN 1 ELSE 0 END) as failed', ['failed', 'dead_letter'])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();

        // 最近1小时失败率
        $hourlyEvents = WebhookEvent::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $oneHourAgo);
        $hourlyTotal = (clone $hourlyEvents)->count();
        $hourlyFailed = (clone $hourlyEvents)->whereIn('status', ['failed', 'dead_letter'])->count();

        // 端点健康状态
        $endpointHealth = WebhookEndpoint::where('tenant_id', $tenantId)
            ->withCount(['events as recent_events' => function ($q) use ($oneHourAgo) {
                $q->where('created_at', '>=', $oneHourAgo);
            }])
            ->withCount(['events as recent_failures' => function ($q) use ($oneHourAgo) {
                $q->where('created_at', '>=', $oneHourAgo)
                  ->whereIn('status', ['failed', 'dead_letter']);
            }])
            ->get()
            ->map(fn($ep) => [
                'id' => $ep->id,
                'name' => $ep->name,
                'url' => $ep->url,
                'is_active' => $ep->is_active,
                'is_paused' => $ep->is_paused,
                'recent_events' => (int) $ep->recent_events,
                'recent_failures' => (int) $ep->recent_failures,
                'health' => $this->computeHealth($ep->is_active, $ep->is_paused, $ep->recent_events, $ep->recent_failures),
            ])
            ->values()
            ->toArray();

        // 事件类型分布
        $eventTypeDist = WebhookEvent::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();

        // 7日和30日统计
        $weeklyStats = $this->periodStats($tenantId, $sevenDaysAgo);
        $monthlyStats = $this->periodStats($tenantId, $thirtyDaysAgo);

        return [
            'summary' => [
                'endpoints_total' => $endpointsCount,
                'active_endpoints' => $activeEndpoints,
                'paused_endpoints' => $pausedEndpoints,
                'today_total' => $todayTotal,
                'today_delivered' => $todayDelivered,
                'today_failed' => $todayFailed,
                'today_pending' => $todayPending,
                'today_success_rate' => $todayTotal > 0 ? round($todayDelivered / $todayTotal * 100, 1) : 100,
                'hourly_total' => $hourlyTotal,
                'hourly_failed' => $hourlyFailed,
                'hourly_failure_rate' => $hourlyTotal > 0 ? round($hourlyFailed / $hourlyTotal * 100, 1) : 0,
            ],
            'weekly_trend' => $weeklyTrend,
            'endpoint_health' => $endpointHealth,
            'event_type_distribution' => $eventTypeDist,
            'weekly_stats' => $weeklyStats,
            'monthly_stats' => $monthlyStats,
        ];
    }

    /**
     * 单个端点详情监控
     */
    public function endpointDetail(int $endpointId, int $tenantId): array
    {
        $endpoint = WebhookEndpoint::where('tenant_id', $tenantId)->findOrFail($endpointId);
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        // 总统计
        $totalEvents = WebhookEvent::where('webhook_endpoint_id', $endpointId)->count();
        $totalDelivered = WebhookEvent::where('webhook_endpoint_id', $endpointId)->where('status', 'delivered')->count();
        $totalFailed = WebhookEvent::where('webhook_endpoint_id', $endpointId)->whereIn('status', ['failed', 'dead_letter'])->count();

        // 今日
        $todayEvents = WebhookEvent::where('webhook_endpoint_id', $endpointId)
            ->where('created_at', '>=', $todayStart)->count();

        // 近7天趋势（含投递延迟）
        $trend = WebhookEvent::where('webhook_endpoint_id', $endpointId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered', ['delivered'])
            ->selectRaw('AVG(CASE WHEN status = ? THEN attempts ELSE NULL END) as avg_attempts', ['delivered'])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();

        // 最近投递记录
        $recentDeliveries = EventDelivery::whereHas('webhookEvent', function ($q) use ($endpointId) {
                $q->where('webhook_endpoint_id', $endpointId);
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->toArray();

        // 响应时间统计
        $latencyStats = DB::table('webhook_latency_records')
            ->where('webhook_endpoint_id', $endpointId)
            ->where('recorded_at', '>=', $sevenDaysAgo)
            ->selectRaw('AVG(response_time_ms) as avg_ms')
            ->selectRaw('MAX(response_time_ms) as max_ms')
            ->selectRaw('MIN(response_time_ms) as min_ms')
            ->selectRaw('COUNT(*) as samples')
            ->first();

        // 延迟趋势（近7天每日平均）
        $latencyTrend = DB::table('webhook_latency_records')
            ->where('webhook_endpoint_id', $endpointId)
            ->where('recorded_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(recorded_at) as date')
            ->selectRaw('AVG(response_time_ms) as avg_ms')
            ->selectRaw('COUNT(*) as samples')
            ->groupByRaw('DATE(recorded_at)')
            ->orderBy('date')
            ->get()
            ->toArray();

        // 事件类型分布
        $eventTypes = WebhookEvent::where('webhook_endpoint_id', $endpointId)
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        // HTTP 状态码分布
        $statusCodes = EventDelivery::whereHas('webhookEvent', function ($q) use ($endpointId) {
                $q->where('webhook_endpoint_id', $endpointId);
            })
            ->selectRaw('response_code, COUNT(*) as total')
            ->whereNotNull('response_code')
            ->groupBy('response_code')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return [
            'endpoint' => $endpoint->toArray(),
            'stats' => [
                'total_events' => $totalEvents,
                'total_delivered' => $totalDelivered,
                'total_failed' => $totalFailed,
                'success_rate' => $totalEvents > 0 ? round($totalDelivered / $totalEvents * 100, 1) : 100,
                'today_events' => $todayEvents,
            ],
            'trend' => $trend,
            'recent_deliveries' => $recentDeliveries,
            'latency' => $latencyStats ? (array) $latencyStats : ['avg_ms' => 0, 'max_ms' => 0, 'min_ms' => 0, 'samples' => 0],
            'latency_trend' => $latencyTrend,
            'event_types' => $eventTypes,
            'status_codes' => $statusCodes,
        ];
    }

    /**
     * 失败事件列表（含重试状态）
     */
    public function failures(int $tenantId, array $filters = []): array
    {
        $query = WebhookEvent::with(['endpoint:id,name,url'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['failed', 'dead_letter']);

        if (!empty($filters['endpoint_id'])) {
            $query->where('webhook_endpoint_id', $filters['endpoint_id']);
        }
        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderByDesc('created_at');

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);
        return $query->paginate($perPage)->toArray();
    }

    /**
     * 延迟分布 — 按区间统计
     */
    public function latencyDistribution(int $tenantId, int $days = 7): array
    {
        $since = now()->subDays($days);

        return DB::table('webhook_latency_records')
            ->where('tenant_id', $tenantId)
            ->where('recorded_at', '>=', $since)
            ->selectRaw("
                CASE
                    WHEN response_time_ms < 100 THEN '<100ms'
                    WHEN response_time_ms < 300 THEN '100-300ms'
                    WHEN response_time_ms < 500 THEN '300-500ms'
                    WHEN response_time_ms < 1000 THEN '500ms-1s'
                    WHEN response_time_ms < 3000 THEN '1-3s'
                    WHEN response_time_ms < 5000 THEN '3-5s'
                    ELSE '>5s'
                END as bucket,
                COUNT(*) as count
            ")
            ->groupBy('bucket')
            ->orderByRaw("CASE bucket WHEN '<100ms' THEN 1 WHEN '100-300ms' THEN 2 WHEN '300-500ms' THEN 3 WHEN '500ms-1s' THEN 4 WHEN '1-3s' THEN 5 WHEN '3-5s' THEN 6 ELSE 7 END")
            ->get()
            ->toArray();
    }

    /**
     * 写入延迟记录（供 WebhookService 投递成功后调用）
     */
    public function recordLatency(int $endpointId, int $tenantId, ?int $eventId, float $responseTimeMs, ?int $httpStatus, bool $isTimeout = false): void
    {
        DB::table('webhook_latency_records')->insert([
            'webhook_endpoint_id' => $endpointId,
            'tenant_id' => $tenantId,
            'webhook_event_id' => $eventId,
            'response_time_ms' => $responseTimeMs,
            'http_status_code' => $httpStatus,
            'is_timeout' => $isTimeout,
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 聚合每日统计数据（可定时任务执行）
     */
    public function aggregateDailyStats(int $tenantId, string $date): int
    {
        $statsDate = \Carbon\Carbon::parse($date);
        $dayStart = $statsDate->copy()->startOfDay();
        $dayEnd = $statsDate->copy()->endOfDay();

        $endpoints = WebhookEndpoint::where('tenant_id', $tenantId)->pluck('id');
        $inserted = 0;

        foreach ($endpoints as $endpointId) {
            $events = WebhookEvent::where('webhook_endpoint_id', $endpointId)
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $totalEvents = (clone $events)->count();
            if ($totalEvents === 0) continue;

            $successCount = (clone $events)->where('status', 'delivered')->count();
            $failedCount = (clone $events)->whereIn('status', ['failed', 'dead_letter'])->count();
            $deadLetter = (clone $events)->where('status', 'dead_letter')->count();

            $totalAttempts = EventDelivery::whereHas('webhookEvent', function ($q) use ($endpointId, $dayStart, $dayEnd) {
                    $q->where('webhook_endpoint_id', $endpointId)
                      ->whereBetween('created_at', [$dayStart, $dayEnd]);
                })->count();

            // 延迟统计
            $latencyData = DB::table('webhook_latency_records')
                ->where('webhook_endpoint_id', $endpointId)
                ->whereBetween('recorded_at', [$dayStart, $dayEnd])
                ->selectRaw('AVG(response_time_ms) as avg_ms')
                ->selectRaw('COUNT(*) as samples')
                ->first();

            // 事件类型分布
            $eventTypeDist = (clone $events)
                ->selectRaw('event_type, COUNT(*) as total')
                ->groupBy('event_type')
                ->pluck('total', 'event_type')
                ->toArray();

            // 状态码分布
            $statusCodeDist = EventDelivery::whereHas('webhookEvent', function ($q) use ($endpointId, $dayStart, $dayEnd) {
                    $q->where('webhook_endpoint_id', $endpointId)
                      ->whereBetween('created_at', [$dayStart, $dayEnd]);
                })
                ->selectRaw('response_code, COUNT(*) as total')
                ->whereNotNull('response_code')
                ->groupBy('response_code')
                ->pluck('total', 'response_code')
                ->toArray();

            DB::table('webhook_endpoint_daily_stats')->updateOrInsert(
                ['webhook_endpoint_id' => $endpointId, 'stat_date' => $date],
                [
                    'tenant_id' => $tenantId,
                    'total_events' => $totalEvents,
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'retried_count' => max(0, $totalAttempts - $totalEvents),
                    'dead_letter_count' => $deadLetter,
                    'total_attempts' => $totalAttempts,
                    'avg_response_time_ms' => round($latencyData->avg_ms ?? 0, 2),
                    'status_code_distribution' => json_encode($statusCodeDist),
                    'event_type_distribution' => json_encode($eventTypeDist),
                    'updated_at' => now(),
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    // ─── 辅助方法 ───

    private function computeHealth(bool $active, bool $paused, $recentEvents, $recentFailures): string
    {
        if (!$active) return 'inactive';
        if ($paused) return 'paused';
        if ($recentEvents === 0) return 'idle';
        $failureRate = $recentEvents > 0 ? $recentFailures / $recentEvents : 0;
        if ($failureRate >= 0.5) return 'critical';
        if ($failureRate >= 0.2) return 'warning';
        return 'healthy';
    }

    private function periodStats(int $tenantId, $since): array
    {
        $events = WebhookEvent::where('tenant_id', $tenantId)->where('created_at', '>=', $since);
        $total = (clone $events)->count();
        $delivered = (clone $events)->where('status', 'delivered')->count();
        $failed = (clone $events)->whereIn('status', ['failed', 'dead_letter'])->count();

        return [
            'total_events' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($delivered / $total * 100, 1) : 100,
        ];
    }
}
