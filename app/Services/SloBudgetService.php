<?php

namespace App\Services;

use App\Models\ApmRequest;
use App\Models\SloBudgetEvent;
use App\Models\SloDailyRecord;
use App\Models\SloDefinition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SloBudgetService
{
    /**
     * 创建SLO定义
     */
    public function createDefinition(array $data): SloDefinition
    {
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']) . '-' . uniqid();
        }

        $definition = SloDefinition::create($data);

        // 初始化错误预算
        $totalBudget = $definition->totalBudgetMinutes();
        $definition->update(['remaining_budget' => $totalBudget]);

        return $definition->fresh();
    }

    /**
     * 更新SLO定义
     */
    public function updateDefinition(SloDefinition $definition, array $data): SloDefinition
    {
        $definition->update($data);
        return $definition->fresh();
    }

    /**
     * 删除SLO定义
     */
    public function deleteDefinition(SloDefinition $definition): void
    {
        $definition->dailyRecords()->delete();
        $definition->budgetEvents()->delete();
        $definition->delete();
    }

    /**
     * 获取SLO列表
     */
    public function listDefinitions(int $tenantId, array $filters = [], int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = SloDefinition::where('tenant_id', $tenantId);

        if (!empty($filters['service_name'])) {
            $query->where('service_name', $filters['service_name']);
        }
        if (!empty($filters['sli_type'])) {
            $query->where('sli_type', $filters['sli_type']);
        }
        if (!empty($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('service_name', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * 获取单个SLO详情
     */
    public function getDefinition(int $id): SloDefinition
    {
        return SloDefinition::with(['dailyRecords' => function ($q) {
            $q->orderByDesc('record_date')->limit(90);
        }, 'budgetEvents' => function ($q) {
            $q->orderByDesc('created_at')->limit(20);
        }])->findOrFail($id);
    }

    // ─── 错误预算计算 ───

    /**
     * 基于APM数据计算单个SLO的错误预算
     */
    public function calculateBudget(SloDefinition $definition, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->subDays((int) $definition->window_days)->startOfDay();
        $to = $to ?? now()->endOfDay();

        // 获取该时间窗口内的APM请求
        $apmQuery = ApmRequest::where('created_at', '>=', $from)
            ->where('created_at', '<=', $to);

        if ($definition->tenant_id) {
            $apmQuery->where('tenant_id', $definition->tenant_id);
        }

        $total = (clone $apmQuery)->count();

        // 根据SLI类型判断"good"请求
        $good = match ($definition->sli_type) {
            'latency' => $this->countGoodByLatency($apmQuery, $definition),
            'availability' => $this->countGoodByAvailability($apmQuery),
            'error_rate' => $this->countGoodByErrorRate($apmQuery),
            'throughput' => $total, // throughput SLI通常不按请求好坏算
            default => $total - (clone $apmQuery)->where('status_code', '>=', 500)->count(),
        };

        $bad = $total - $good;
        $currentSli = $total > 0 ? round(($good / $total) * 100, 2) : 100;

        // 计算错误预算
        $totalBudgetMinutes = $definition->totalBudgetMinutes();
        $totalMinutesInWindow = $definition->window_days * 24 * 60;
        $badRatio = $total > 0 ? ($bad / $total) : 0;

        // 已消耗的错误预算 = 总预算 * (bad_ratio / (1 - target/100))
        // 限制最大为总预算
        $targetBadRatio = 1 - ($definition->target / 100);
        $consumedRatio = $targetBadRatio > 0 ? min(1, $badRatio / $targetBadRatio) : 1;
        $consumedBudget = round($totalBudgetMinutes * $consumedRatio, 2);
        $remainingBudget = round(max(0, $totalBudgetMinutes - $consumedBudget), 2);

        // 燃烧率 = consumed / elapsed_days
        $elapsedMinutes = max(1, now()->diffInMinutes($from));
        $elapsedDays = max(1, $elapsedMinutes / (24 * 60));
        $burnRate = $elapsedDays > 0
            ? round($consumedBudget / $elapsedDays, 2)
            : 0;

        // 更新 SLO
        $definition->update([
            'total_requests' => $total,
            'good_requests' => $good,
            'current_sli' => $currentSli,
            'remaining_budget' => $remainingBudget,
            'burn_rate' => $burnRate,
        ]);

        // 保存每日记录
        $this->saveDailyRecords($definition, $from, $to, $totalBudgetMinutes);

        // 检查燃烧率告警
        $this->checkBurnRateAlerts($definition, $remainingBudget, $burnRate);

        return [
            'total_requests' => $total,
            'good_requests' => $good,
            'bad_requests' => $bad,
            'current_sli' => $currentSli,
            'total_budget_minutes' => $totalBudgetMinutes,
            'remaining_budget' => $remainingBudget,
            'consumed_budget' => $consumedBudget,
            'consumed_percent' => $totalBudgetMinutes > 0
                ? round(($consumedBudget / $totalBudgetMinutes) * 100, 1)
                : 0,
            'burn_rate' => $burnRate,
            'target_sli' => $definition->target,
        ];
    }

    /**
     * 批量计算所有活跃SLO的错误预算
     */
    public function calculateAllBudgets(): int
    {
        $definitions = SloDefinition::where('is_active', true)->get();
        $count = 0;

        foreach ($definitions as $definition) {
            try {
                $this->calculateBudget($definition);
                $count++;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('SLO计算异常', [
                    'slo_id' => $definition->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * 获取SLO仪表盘统计
     */
    public function getDashboard(int $tenantId): array
    {
        $totalSlo = SloDefinition::where('tenant_id', $tenantId)->count();
        $activeSlo = SloDefinition::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $healthySlo = SloDefinition::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('remaining_budget', '>', 0)
            ->count();
        $exhaustedSlo = SloDefinition::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('remaining_budget', '<=', 0)
            ->count();

        $recentEvents = SloBudgetEvent::whereIn('slo_definition_id',
            SloDefinition::where('tenant_id', $tenantId)->select('id')
        )->where('created_at', '>=', now()->subDays(7))
            ->with('definition:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $byService = SloDefinition::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->selectRaw('service_name, COUNT(*) as cnt, AVG(current_sli) as avg_sli')
            ->groupBy('service_name')
            ->get()
            ->toArray();

        return [
            'total_slo' => $totalSlo,
            'active_slo' => $activeSlo,
            'healthy_slo' => $healthySlo,
            'exhausted_slo' => $exhaustedSlo,
            'recent_events' => $recentEvents,
            'by_service' => $byService,
        ];
    }

    // ─── 内部方法 ───

    protected function countGoodByLatency($query, SloDefinition $definition): int
    {
        // 对于latency SLI, 从sla_targets取阈值
        $thresholdMs = 500; // 默认500ms
        return (clone $query)->where('duration_ms', '<', $thresholdMs)->count();
    }

    protected function countGoodByAvailability($query): int
    {
        return (clone $query)->where('status_code', '<', 500)->count();
    }

    protected function countGoodByErrorRate($query): int
    {
        return (clone $query)->where('status_code', '<', 500)->count();
    }

    /**
     * 保存每日SLO记录
     */
    protected function saveDailyRecords(SloDefinition $definition, Carbon $from, Carbon $to, float $totalBudgetMinutes): void
    {
        $current = $from->copy()->startOfDay();

        while ($current->lte($to)) {
            $dayStart = $current->copy();
            $dayEnd = $current->copy()->endOfDay();

            $dayQuery = ApmRequest::whereBetween('created_at', [$dayStart, $dayEnd]);
            $dayTotal = (clone $dayQuery)->count();
            $dayGood = match ($definition->sli_type) {
                'latency' => (clone $dayQuery)->where('duration_ms', '<', 500)->count(),
                'availability', 'error_rate' => (clone $dayQuery)->where('status_code', '<', 500)->count(),
                default => $dayTotal,
            };
            $dayBad = $dayTotal - $dayGood;
            $daySli = $dayTotal > 0 ? round(($dayGood / $dayTotal) * 100, 2) : null;

            // 每日消耗 = 总预算 * (今日bad_ratio / (1 - target%)) / 窗口天数
            $dailyBudgetShare = $totalBudgetMinutes / $definition->window_days;
            $targetBadRatio = max(0.001, 1 - $definition->target / 100);
            $dailyBadRatio = $dayTotal > 0 ? ($dayBad / max($dayTotal, 1)) : 0;
            $budgetConsumed = round($dailyBudgetShare * min(1, $dailyBadRatio / $targetBadRatio), 2);

            SloDailyRecord::updateOrCreate(
                [
                    'slo_definition_id' => $definition->id,
                    'record_date' => $current->format('Y-m-d'),
                ],
                [
                    'total_requests' => $dayTotal,
                    'good_requests' => $dayGood,
                    'bad_requests' => $dayBad,
                    'sli' => $daySli,
                    'budget_consumed' => $budgetConsumed,
                ]
            );

            $current->addDay();
        }
    }

    /**
     * 检查燃烧率告警条件
     */
    protected function checkBurnRateAlerts(SloDefinition $definition, float $remainingBudget, float $burnRate): void
    {
        $alerts = $definition->burn_rate_alerts ?? [];

        foreach ($alerts as $alert) {
            $windowMinutes = ($alert['window_hours'] ?? 1) * 60;
            $threshold = $alert['threshold'] ?? 2;

            // 按当前燃烧率计算，该窗口内会消耗多少预算
            $projectedConsumption = $burnRate * ($windowMinutes / (24 * 60));
            $budgetPercent = $definition->totalBudgetMinutes() > 0
                ? ($remainingBudget / $definition->totalBudgetMinutes()) * 100
                : 0;

            if ($projectedConsumption > $remainingBudget && $remainingBudget > 0) {
                SloBudgetEvent::create([
                    'slo_definition_id' => $definition->id,
                    'event_type' => 'burn_rate_alert',
                    'budget_remaining' => $remainingBudget,
                    'burn_rate' => $burnRate,
                    'context' => [
                        'alert_config' => $alert,
                        'projected_consumption' => round($projectedConsumption, 2),
                        'budget_percent' => round($budgetPercent, 1),
                        'message' => "燃烧率{$burnRate}/天，预计{$alert['window_hours']}小时内消耗全部错误预算",
                    ],
                ]);
            }
        }

        // 错误预算耗尽
        if ($remainingBudget <= 0) {
            SloBudgetEvent::create([
                'slo_definition_id' => $definition->id,
                'event_type' => 'budget_exhausted',
                'budget_remaining' => 0,
                'burn_rate' => $burnRate,
                'context' => ['message' => '错误预算已耗尽'],
            ]);
        } elseif ($remainingBudget < $definition->totalBudgetMinutes() * 0.2) {
            // 错误预算剩余不足20%
            SloBudgetEvent::create([
                'slo_definition_id' => $definition->id,
                'event_type' => 'budget_warning',
                'budget_remaining' => $remainingBudget,
                'burn_rate' => $burnRate,
                'context' => ['message' => "错误预算仅剩{$remainingBudget}分钟（" . round(($remainingBudget / max($definition->totalBudgetMinutes(), 1)) * 100, 1) . "%）"],
            ]);
        }
    }
}
