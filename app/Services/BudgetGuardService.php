<?php

namespace App\Services;

use App\Models\BudgetAlert;
use App\Models\BudgetLimit;
use App\Models\BudgetOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 消费预警+预算上限服务 (M2-79)
 *
 * 客户设置月度/季度预算上限 → 80%告警 → 100%自动拦截超额消费 → 审批解锁
 */
class BudgetGuardService
{
    /**
     * 获取客户的预算配置
     */
    public function getBudget(string $budgetableType, int $budgetableId, ?string $period = null): ?BudgetLimit
    {
        $query = BudgetLimit::active()
            ->where('budgetable_type', $budgetableType)
            ->where('budgetable_id', $budgetableId);

        if ($period) {
            $query->where('period', $period);
        }

        return $query->first();
    }

    /**
     * 保存/更新预算配置
     */
    public function saveBudget(string $budgetableType, int $budgetableId, array $data, ?int $userId = null): BudgetLimit
    {
        $period = $data['period'] ?? 'monthly';

        $budget = BudgetLimit::where('budgetable_type', $budgetableType)
            ->where('budgetable_id', $budgetableId)
            ->where('period', $period)
            ->first();

        if ($budget) {
            $budget->update([
                'budget_amount' => $data['budget_amount'] ?? $budget->budget_amount,
                'currency' => $data['currency'] ?? $budget->currency,
                'status' => $data['status'] ?? $budget->status,
                'notifications_enabled' => $data['notifications_enabled'] ?? $budget->notifications_enabled,
                'notes' => $data['notes'] ?? $budget->notes,
                'period_start_at' => $data['period_start_at'] ?? $budget->period_start_at,
                'period_end_at' => $data['period_end_at'] ?? $budget->period_end_at,
            ]);
        } else {
            $periodStart = $data['period_start_at'] ?? now()->startOfMonth();
            $periodEnd = $data['period_end_at'] ?? now()->endOfMonth();

            $budget = BudgetLimit::create([
                'budgetable_type' => $budgetableType,
                'budgetable_id' => $budgetableId,
                'period' => $period,
                'budget_amount' => $data['budget_amount'] ?? 0,
                'currency' => $data['currency'] ?? config('budget-guard.default_currency', 'CNY'),
                'status' => $data['status'] ?? 'active',
                'notifications_enabled' => $data['notifications_enabled'] ?? true,
                'notes' => $data['notes'] ?? '',
                'period_start_at' => $periodStart,
                'period_end_at' => $periodEnd,
                'created_by' => $userId,
            ]);
        }

        return $budget->fresh();
    }

    /**
     * 检查消费是否允许（核心方法：在计费/订阅消费前调用）
     *
     * @return array{allowed: bool, reason: ?string, budget: ?BudgetLimit}
     */
    public function checkSpend(string $budgetableType, int $budgetableId, float $amount): array
    {
        $budget = $this->getBudget($budgetableType, $budgetableId);
        if (!$budget) {
            return ['allowed' => true, 'reason' => null, 'budget' => null];
        }

        $usage = $budget->usagePercentage();
        $newUsage = $budget->budget_amount > 0
            ? round(($budget->spent_amount + $budget->pending_amount + $amount) / $budget->budget_amount * 100, 2)
            : 0;

        // 检查是否有有效的审批
        $validOverride = BudgetOverride::where('budget_limit_id', $budget->id)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($validOverride) {
            return ['allowed' => true, 'reason' => '预算超额审批通过', 'budget' => $budget, 'override' => $validOverride];
        }

        // Hard limit: 100%
        $hardLimit = config('budget-guard.alert_thresholds.hard_limit', 100);
        if ($newUsage >= $hardLimit) {
            $action = config('budget-guard.hard_limit_action', 'block');
            if ($action === 'block') {
                $this->createAlert($budget->id, 'blocked', $newUsage);
                return ['allowed' => false, 'reason' => "预算已用完 ({$newUsage}%)", 'budget' => $budget];
            }
        }

        // 检查预警阈值
        $thresholds = config('budget-guard.alert_thresholds', []);
        $lastAlertLevel = null;

        if ($newUsage >= ($thresholds['hard_limit'] ?? 100)) {
            $lastAlertLevel = 'blocked';
        } elseif ($newUsage >= ($thresholds['critical'] ?? 95)) {
            $lastAlertLevel = 'critical';
        } elseif ($newUsage >= ($thresholds['warning'] ?? 80)) {
            $lastAlertLevel = 'warning';
        }

        if ($lastAlertLevel && $budget->notifications_enabled) {
            $shouldNotify = true;
            if ($budget->last_alert_at && $budget->last_alert_at->diffInHours(now()) < 24) {
                $shouldNotify = false; // 每天最多一次
            }
            if ($shouldNotify) {
                $this->createAlert($budget->id, $lastAlertLevel, $newUsage, true);
                $budget->update(['last_alert_at' => now()]);
            }
        }

        return ['allowed' => true, 'reason' => $lastAlertLevel ? "预算使用 {$newUsage}%, 已达 {$lastAlertLevel} 阈值" : null, 'budget' => $budget];
    }

    /**
     * 记录消费（消费发生后调用，更新已用金额）
     */
    public function recordSpend(string $budgetableType, int $budgetableId, float $amount, string $type = 'spent'): void
    {
        $budget = $this->getBudget($budgetableType, $budgetableId);
        if (!$budget) return;

        $field = $type === 'pending' ? 'pending_amount' : 'spent_amount';

        $budget->increment($field, $amount);
    }

    /**
     * 释放待结算金额
     */
    public function releasePending(string $budgetableType, int $budgetableId, float $amount): void
    {
        $budget = $this->getBudget($budgetableType, $budgetableId);
        if (!$budget) return;

        $budget->decrement('pending_amount', $amount);
    }

    /**
     * 请求审批超预算
     */
    public function requestOverride(int $budgetId, float $requestedAmount, string $reason, ?int $userId = null): BudgetOverride
    {
        $budget = BudgetLimit::findOrFail($budgetId);
        $usageAfter = $budget->budget_amount > 0
            ? round(($budget->spent_amount + $requestedAmount) / $budget->budget_amount * 100, 2)
            : 0;

        $override = BudgetOverride::create([
            'budget_limit_id' => $budgetId,
            'requested_amount' => $requestedAmount,
            'override_percentage' => $usageAfter,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $userId,
        ]);

        // 自动审批（小幅度超额）
        $autoApprovalLimit = config('budget-guard.approval.auto_approve_threshold', 120);
        if ($usageAfter <= $autoApprovalLimit) {
            $this->approveOverride($override->id, $userId);
        }

        return $override->fresh();
    }

    /**
     * 审批通过
     */
    public function approveOverride(int $overrideId, ?int $adminId = null): BudgetOverride
    {
        $override = BudgetOverride::findOrFail($overrideId);
        $expiryHours = config('budget-guard.override_expiry_hours', 24);

        $override->update([
            'status' => 'approved',
            'approved_by' => $adminId,
            'approved_at' => now(),
            'expires_at' => now()->addHours($expiryHours),
        ]);

        return $override->fresh();
    }

    /**
     * 拒绝审批
     */
    public function rejectOverride(int $overrideId, ?int $adminId = null): BudgetOverride
    {
        $override = BudgetOverride::findOrFail($overrideId);
        $override->update(['status' => 'rejected', 'approved_by' => $adminId]);
        return $override->fresh();
    }

    /**
     * 创建预警记录
     */
    protected function createAlert(int $budgetId, string $level, float $usagePercentage, bool $notified = false): BudgetAlert
    {
        $budget = BudgetLimit::find($budgetId);
        return BudgetAlert::create([
            'budget_limit_id' => $budgetId,
            'level' => $level,
            'usage_percentage' => $usagePercentage,
            'spent_at_alert' => $budget ? ($budget->spent_amount + $budget->pending_amount) : 0,
            'notified' => $notified,
            'notified_at' => $notified ? now() : null,
        ]);
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(string $budgetableType, int $budgetableId): array
    {
        $budgets = BudgetLimit::where('budgetable_type', $budgetableType)
            ->where('budgetable_id', $budgetableId)
            ->get();

        $total = $budgets->sum('budget_amount');
        $spent = $budgets->sum('spent_amount') + $budgets->sum('pending_amount');
        $overallUsage = $total > 0 ? round($spent / $total * 100, 2) : 0;

        $byPeriod = [];
        foreach ($budgets as $b) {
            $byPeriod[$b->period] = [
                'budget' => $b->budget_amount,
                'spent' => $b->spent_amount + $b->pending_amount,
                'usage' => $b->usagePercentage(),
                'currency' => $b->currency,
                'status' => $b->status,
            ];
        }

        $pendingOverrides = BudgetOverride::whereIn('budget_limit_id', $budgets->pluck('id'))
            ->where('status', 'pending')
            ->count();

        return [
            'total_budget' => $total,
            'total_spent' => $spent,
            'overall_usage' => $overallUsage,
            'by_period' => $byPeriod,
            'pending_overrides' => $pendingOverrides,
            'alert_thresholds' => config('budget-guard.alert_thresholds'),
        ];
    }

    /**
     * 获取预警历史
     */
    public function getAlertHistory(int $budgetId, int $limit = 20): array
    {
        return BudgetAlert::where('budget_limit_id', $budgetId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取审批列表
     */
    public function getOverrides(int $budgetId, ?string $status = null): array
    {
        $query = BudgetOverride::where('budget_limit_id', $budgetId);
        if ($status) {
            $query->where('status', $status);
        }
        return $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    /**
     * 获取所有待审批
     */
    public function getPendingOverviews(): array
    {
        return BudgetOverride::with('budgetLimit')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 删除预算配置
     */
    public function deleteBudget(int $budgetId): bool
    {
        return BudgetLimit::findOrFail($budgetId)->delete() ? true : false;
    }
}
