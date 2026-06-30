<?php

namespace App\Services;

use App\Models\AuditAnalysisSummary;
use App\Models\AuditAnomaly;
use App\Models\AuditReportConfig;
use App\Models\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 审计可视化服务
 *
 * 提供审计数据的图表分析、趋势图、异常检测、预聚合统计等功能。
 * 数据源基于 logs 表，支持多租户。
 */
class AuditVisualizationService
{
    // ─── 概览仪表盘 ───

    public function getDashboard(int $tenantId = null): array
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth()->startOfMonth();

        $logQuery = fn($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q;

        // 今日统计
        $todayLogs = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $today)->count();

        // 本月统计
        $monthLogs = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $thisMonth)->count();

        // 上月统计
        $lastMonthLogs = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$lastMonth, $thisMonth])->count();

        // 类型分布
        $typeDistribution = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $thisMonth)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')->pluck('count', 'type')->toArray();

        // 未处理异常
        $openAnomalies = AuditAnomaly::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'open')->count();

        // 操作活跃用户
        $activeUsers = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $today)
            ->distinct('user_id')->count('user_id');

        // 最近异常
        $recentAnomalies = AuditAnomaly::with('tenant:id,name')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('detected_at')->limit(5)->get()->toArray();

        return [
            'today_logs' => $todayLogs,
            'month_logs' => $monthLogs,
            'last_month_logs' => $lastMonthLogs,
            'month_change_pct' => $lastMonthLogs > 0
                ? round((($monthLogs - $lastMonthLogs) / $lastMonthLogs) * 100, 1) : 0,
            'type_distribution' => $typeDistribution,
            'open_anomalies' => $openAnomalies,
            'active_users_today' => $activeUsers,
            'recent_anomalies' => $recentAnomalies,
        ];
    }

    // ─── 趋势分析 ───

    /**
     * 审计日志趋势数据
     *
     * @param int|null $tenantId
     * @param string $startDate  Y-m-d
     * @param string $endDate    Y-m-d
     * @param string $granularity daily|weekly|monthly
     * @param string|null $type  audit|security|error|system
     * @return array
     */
    public function getTrend(int $tenantId = null, string $startDate, string $endDate,
                             string $granularity = 'daily', string $type = null): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $dateFormat = match ($granularity) {
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $query = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$start, $end->endOfDay()])
            ->when($type, fn($q, $v) => $q->where('type', $v))
            ->selectRaw("DATE_FORMAT(created_at, ?) as period_label", [$dateFormat])
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
            ->selectRaw('COUNT(DISTINCT ip_address) as unique_ips')
            ->groupBy('period_label')
            ->orderBy('period_label')
            ->get();

        // 填充空白日期
        $filled = $this->fillDateGaps($start, $end, $granularity, $query);

        // 细分 type 趋势
        $byType = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$start, $end->endOfDay()])
            ->when($type, fn($q, $v) => $q->where('type', $v))
            ->selectRaw("DATE_FORMAT(created_at, ?) as period_label", [$dateFormat])
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('period_label', 'type')
            ->orderBy('period_label')
            ->get()
            ->groupBy('period_label');

        $typeSeries = [];
        foreach ($filled as $point) {
            $period = $point['period'];
            $dayTypes = $byType->get($period, collect());
            $typeSeries[] = [
                'period' => $period,
                'label' => $point['label'],
                'audit' => $dayTypes->where('type', 'audit')->sum('count'),
                'security' => $dayTypes->where('type', 'security')->sum('count'),
                'error' => $dayTypes->where('type', 'error')->sum('count'),
                'system' => $dayTypes->where('type', 'system')->sum('count'),
            ];
        }

        return [
            'granularity' => $granularity,
            'total' => $query->sum('count'),
            'summary' => $filled,
            'by_type' => $typeSeries,
        ];
    }

    /**
     * 热门操作 Top-N
     */
    public function getTopActions(int $tenantId = null, string $startDate, string $endDate,
                                  int $limit = 10, string $type = null): array
    {
        return Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)->endOfDay()])
            ->when($type, fn($q, $v) => $q->where('type', $v))
            ->selectRaw('action, COUNT(*) as count, COUNT(DISTINCT user_id) as unique_users')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 活跃用户 Top-N
     */
    public function getTopUsers(int $tenantId = null, string $startDate, string $endDate, int $limit = 10): array
    {
        return Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)->endOfDay()])
            ->selectRaw('user_id, COUNT(*) as count, COUNT(DISTINCT action) as action_count')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit($limit)
            ->with('user:id,name,email')
            ->get()
            ->map(fn($l) => [
                'user_id' => $l->user_id,
                'user_name' => $l->user?->name ?? 'System',
                'user_email' => $l->user?->email ?? '',
                'count' => $l->count,
                'action_count' => $l->action_count,
            ])
            ->toArray();
    }

    /**
     * IP 地址分布 Top-N
     */
    public function getTopIps(int $tenantId = null, string $startDate, string $endDate, int $limit = 10): array
    {
        return Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)->endOfDay()])
            ->selectRaw('ip_address, COUNT(*) as count, COUNT(DISTINCT user_id) as unique_users')
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 时间分布（按小时）
     */
    public function getHourlyDistribution(int $tenantId = null, string $startDate, string $endDate): array
    {
        $result = [];
        for ($h = 0; $h < 24; $h++) {
            $result[$h] = ['hour' => $h, 'count' => 0];
        }

        Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)->endOfDay()])
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->each(fn($r) => $result[(int)$r->hour] = ['hour' => (int)$r->hour, 'count' => $r->count]);

        return array_values($result);
    }

    /**
     * 类型分布饼图
     */
    public function getTypeDistribution(int $tenantId = null, string $startDate, string $endDate): array
    {
        return Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)->endOfDay()])
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    /**
     * 按类别分组（取 action 前缀）
     */
    public function getCategoryDistribution(int $tenantId = null, string $startDate, string $endDate): array
    {
        return Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)->endOfDay()])
            ->selectRaw("SUBSTRING_INDEX(action, '.', 1) as category, COUNT(*) as count")
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    // ─── 异常检测 ───

    /**
     * 执行异常检测：基于日志量的突增/突降
     */
    public function detectAnomalies(int $tenantId = null): array
    {
        $detected = [];

        // 1. 日环比突增检测
        $spike = $this->detectSpike($tenantId);
        if ($spike) $detected[] = $spike;

        // 2. 周同比检测
        $weekly = $this->detectWeeklyAnomaly($tenantId);
        if ($weekly) $detected = array_merge($detected, $weekly);

        return $detected;
    }

    protected function detectSpike(int $tenantId = null): ?array
    {
        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $baselineStart = $today->copy()->subDays(8);
        $baselineEnd = $today->copy()->subDay();

        $todayCount = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $today)->count();

        $baselineAvg = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$baselineStart, $baselineEnd])
            ->count() / 7;

        if ($baselineAvg > 0) {
            $deviation = round((($todayCount - $baselineAvg) / $baselineAvg) * 100, 2);
            $threshold = 200; // 200% 以上视为突增

            if (abs($deviation) >= $threshold) {
                $type = $deviation > 0 ? 'spike' : 'drop';
                $severity = abs($deviation) >= 500 ? 'critical' : 'warning';

                $anomaly = AuditAnomaly::create([
                    'tenant_id' => $tenantId,
                    'anomaly_type' => $type,
                    'severity' => $severity,
                    'metric' => '每日审计日志量',
                    'baseline_value' => round($baselineAvg, 2),
                    'actual_value' => $todayCount,
                    'deviation' => $deviation,
                    'description' => $type === 'spike'
                        ? "今日审计日志量 {$todayCount}，较近7日均值 {$baselineAvg} 突增 {$deviation}%"
                        : "今日审计日志量 {$todayCount}，较近7日均值 {$baselineAvg} 突降 {$deviation}%",
                    'context' => [
                        'baseline_period' => [$baselineStart->format('Y-m-d'), $baselineEnd->format('Y-m-d')],
                        'today' => $today->format('Y-m-d'),
                        'threshold' => $threshold,
                    ],
                    'status' => 'open',
                ]);

                return $anomaly->toArray();
            }
        }

        return null;
    }

    protected function detectWeeklyAnomaly(int $tenantId = null): array
    {
        $detected = [];

        $thisWeek = now()->startOfWeek();
        $lastWeek = $thisWeek->copy()->subWeek();

        $typeCounts = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $lastWeek)
            ->selectRaw("DATE(created_at) as dt, type, COUNT(*) as count")
            ->groupBy('dt', 'type')
            ->get();

        // 检测某类型占比异常变化
        $thisWeekData = $typeCounts->filter(fn($r) => Carbon::parse($r->dt)->greaterThanOrEqualTo($thisWeek));
        $lastWeekData = $typeCounts->filter(fn($r) => Carbon::parse($r->dt)->lessThan($thisWeek));

        foreach (['audit', 'security', 'error', 'system'] as $type) {
            $thisCnt = $thisWeekData->where('type', $type)->sum('count');
            $lastCnt = $lastWeekData->where('type', $type)->sum('count');

            if ($lastCnt > 0 && $thisCnt > 0) {
                $change = round((($thisCnt - $lastCnt) / $lastCnt) * 100, 2);
                if (abs($change) >= 150) {
                    $anomaly = AuditAnomaly::create([
                        'tenant_id' => $tenantId,
                        'anomaly_type' => 'pattern_change',
                        'severity' => abs($change) >= 300 ? 'critical' : 'warning',
                        'metric' => "{$type} 类型日志量",
                        'baseline_value' => $lastCnt,
                        'actual_value' => $thisCnt,
                        'deviation' => $change,
                        'description' => "本周 {$type} 日志量 {$thisCnt}，较上周 {$lastCnt} 变化 {$change}%",
                        'status' => 'open',
                    ]);
                    $detected[] = $anomaly->toArray();
                }
            }
        }

        return $detected;
    }

    /**
     * 获取异常列表
     */
    public function getAnomalies(int $tenantId = null, array $filters = []): array
    {
        $query = AuditAnomaly::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($filters['severity'] ?? null, fn($q, $v) => $q->where('severity', $v))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn($q, $v) => $q->where('anomaly_type', $v))
            ->orderByDesc('detected_at');

        return $query->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1)->toArray();
    }

    public function updateAnomalyStatus(int $id, string $status): AuditAnomaly
    {
        $anomaly = AuditAnomaly::findOrFail($id);
        $data = ['status' => $status];
        if ($status === 'acknowledged') {
            $data['acknowledged_at'] = now();
        }
        $anomaly->update($data);
        return $anomaly->fresh();
    }

    // ─── 预聚合（定时任务 / 手动调用） ───

    /**
     * 聚合每日数据到 summaries 表
     */
    public function aggregateDailySummaries(int $tenantId = null, string $date = null): int
    {
        $targetDate = $date ? Carbon::parse($date) : now()->subDay();
        $start = $targetDate->copy()->startOfDay();
        $end = $targetDate->copy()->endOfDay();
        $created = 0;

        $grouped = Log::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('tenant_id, type, action, COUNT(*) as cnt, COUNT(DISTINCT user_id) as uq_users, COUNT(DISTINCT ip_address) as uq_ips')
            ->groupBy('tenant_id', 'type', 'action')
            ->get();

        foreach ($grouped as $row) {
            AuditAnalysisSummary::updateOrCreate(
                [
                    'tenant_id' => $row->tenant_id,
                    'summary_date' => $targetDate->format('Y-m-d'),
                    'period' => 'daily',
                    'type' => $row->type,
                    'action' => $row->action,
                ],
                [
                    'count' => $row->cnt,
                    'unique_users' => $row->uq_users,
                    'unique_ips' => $row->uq_ips,
                ]
            );
            $created++;
        }

        return $created;
    }

    // ─── 报表配置 ───

    public function getReportConfigs(int $tenantId, int $userId): array
    {
        return AuditReportConfig::where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('is_shared', true);
            })
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function saveReportConfig(array $data): AuditReportConfig
    {
        return AuditReportConfig::create($data);
    }

    public function deleteReportConfig(int $id): void
    {
        AuditReportConfig::findOrFail($id)->delete();
    }

    // ─── 帮助方法 ───

    protected function fillDateGaps(Carbon $start, Carbon $end, string $granularity, $query): array
    {
        $data = $query->keyBy('period_label');
        $filled = [];
        $current = $start->copy();
        $fmt = $granularity === 'monthly' ? 'Y-m' : ($granularity === 'weekly' ? 'Y-W' : 'Y-m-d');

        while ($current->lte($end)) {
            $key = $current->format($fmt);
            $row = $data->get($key);
            $filled[] = [
                'period' => $key,
                'label' => $granularity === 'daily' ? $current->format('m-d') : $key,
                'count' => $row->count ?? 0,
                'unique_users' => $row->unique_users ?? 0,
                'unique_ips' => $row->unique_ips ?? 0,
            ];
            $current->add($granularity === 'monthly' ? 'month' : ($granularity === 'weekly' ? 'week' : 'day'));
        }

        return $filled;
    }
}
