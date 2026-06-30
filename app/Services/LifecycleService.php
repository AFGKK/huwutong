<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLifecycleStage;
use App\Models\RfmScore;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 客户生命周期管理服务 (M3-19)
 *
 * 统一管理客户生命周期阶段：
 * 1. 阶段定义与迁移
 * 2. 自动阶段评估（基于RFM/健康分/订阅）
 * 3. 阶段迁移记录
 * 4. 生命周期仪表盘
 */
class LifecycleService
{
    const STAGE_PROSPECT = 'prospect';
    const STAGE_ONBOARDING = 'onboarding';
    const STAGE_ACTIVE = 'active';
    const STAGE_GROWING = 'growing';
    const STAGE_MATURE = 'mature';
    const STAGE_AT_RISK = 'at_risk';
    const STAGE_CHURNED = 'churned';

    /**
     * 将客户移至新阶段
     */
    public function transitionCustomer(Customer $customer, string $newStage, ?string $reason = null, string $triggeredBy = 'manual'): CustomerLifecycleStage
    {
        $oldStage = $customer->lifecycle_stage ?? self::STAGE_PROSPECT;

        // 关闭上一条记录
        CustomerLifecycleStage::where('customer_id', $customer->id)
            ->whereNull('exited_at')
            ->update(['exited_at' => now()]);

        // 创建新阶段记录
        $record = CustomerLifecycleStage::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'stage' => $newStage,
            'previous_stage' => $oldStage,
            'reason' => $reason,
            'triggered_by' => $triggeredBy,
            'entered_at' => now(),
        ]);

        // 更新 Customer 上的当前阶段
        $customer->update([
            'lifecycle_stage' => $newStage,
            'stage_entered_at' => now(),
        ]);

        return $record;
    }

    /**
     * 批量自动评估客户阶段
     */
    public function autoEvaluate(int $tenantId): array
    {
        $stats = ['evaluated' => 0, 'changed' => 0];

        Customer::where('tenant_id', $tenantId)
            ->chunk(50, function (Collection $customers) use (&$stats) {
                foreach ($customers as $customer) {
                    try {
                        $suggestedStage = $this->suggestStage($customer);
                        if ($suggestedStage && $suggestedStage !== ($customer->lifecycle_stage ?? self::STAGE_PROSPECT)) {
                            $this->transitionCustomer($customer, $suggestedStage, '自动阶段评估', 'auto');
                            $stats['changed']++;
                        }
                        $stats['evaluated']++;
                    } catch (\Throwable $e) {
                        // 记录但继续
                    }
                }
            });

        return $stats;
    }

    /**
     * 建议客户所处的生命周期阶段
     */
    public function suggestStage(Customer $customer): ?string
    {
        $rfm = RfmScore::where('customer_id', $customer->id)->first();
        $subCount = $customer->subscriptions()->count();
        $activeSubs = $customer->subscriptions()->whereIn('status', ['active', 'grace'])->count();
        $recentInvoice = $customer->invoices()
            ->where('created_at', '>=', now()->subDays(90))
            ->exists();

        // 已流失: 无活跃订阅，且最近无活动
        if ($activeSubs === 0 && !$recentInvoice && $customer->created_at < now()->subDays(30)) {
            return self::STAGE_CHURNED;
        }

        // 风险期: 宽限期或RFM低分
        $inGrace = $customer->subscriptions()->where('status', 'grace')->exists();
        if ($inGrace) {
            return self::STAGE_AT_RISK;
        }
        if ($rfm && $rfm->rfm_segment === 'At Risk') {
            return self::STAGE_AT_RISK;
        }

        // 无活跃订阅 → 潜在客户
        if ($activeSubs === 0) {
            return self::STAGE_PROSPECT;
        }

        // 引导期: 创建后30天内
        if ($customer->created_at >= now()->subDays(30)) {
            return self::STAGE_ONBOARDING;
        }

        // 成熟期: 超过1年，RFM高
        if ($customer->created_at < now()->subYear() && $rfm && $rfm->rfm_total >= 7) {
            return self::STAGE_MATURE;
        }

        // 成长期: 有订阅且RFM良好
        if ($subCount >= 2 || ($rfm && $rfm->rfm_total >= 5)) {
            return self::STAGE_GROWING;
        }

        // 活跃期
        return self::STAGE_ACTIVE;
    }

    /**
     * 获取客户阶段迁移历史
     */
    public function getTransitionHistory(int $tenantId, array $filters = []): array
    {
        $query = CustomerLifecycleStage::where('tenant_id', $tenantId)
            ->with('customer')
            ->orderByDesc('entered_at');

        if (!empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (!empty($filters['triggered_by'])) {
            $query->where('triggered_by', $filters['triggered_by']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage)
            ->withQueryString()
            ->through(function ($row) {
                $customer = $row->customer;
                return [
                    'id' => $row->id,
                    'customer_name' => $customer ? "#{$customer->id}" : '已删除',
                    'customer_id' => $row->customer_id,
                    'stage' => $row->stage,
                    'previous_stage' => $row->previous_stage,
                    'reason' => $row->reason,
                    'triggered_by' => $row->triggered_by,
                    'entered_at' => $row->entered_at,
                    'exited_at' => $row->exited_at,
                    'duration_days' => $row->exited_at
                        ? $row->entered_at->diffInDays($row->exited_at)
                        : ($row->entered_at->diffInDays(now())),
                ];
            })
            ->toArray();
    }

    /**
     * 获取生命周期仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $now = now();

        // 各阶段客户数
        $stageDistribution = Customer::where('tenant_id', $tenantId)
            ->selectRaw("COALESCE(lifecycle_stage, 'prospect') as stage, COUNT(*) as total")
            ->groupBy('stage')
            ->get()
            ->pluck('total', 'stage')
            ->toArray();

        // 各阶段的订阅数量和MRV
        $stageRevenue = Customer::where('customers.tenant_id', $tenantId)
            ->join('subscriptions', 'customers.id', '=', 'subscriptions.customer_id')
            ->selectRaw("COALESCE(customers.lifecycle_stage, 'prospect') as stage, COUNT(DISTINCT subscriptions.id) as sub_count, COALESCE(SUM(subscriptions.price), 0) as total_mrr")
            ->whereIn('subscriptions.status', ['active', 'grace'])
            ->groupBy('stage')
            ->get()
            ->keyBy('stage')
            ->toArray();

        // 近30天阶段迁移统计
        $recentTransitions = CustomerLifecycleStage::where('tenant_id', $tenantId)
            ->where('entered_at', '>=', $now->copy()->subDays(30))
            ->selectRaw("stage, COUNT(*) as total")
            ->groupBy('stage')
            ->get()
            ->pluck('total', 'stage')
            ->toArray();

        // 各阶段平均停留天数
        $avgDuration = CustomerLifecycleStage::where('tenant_id', $tenantId)
            ->whereNotNull('exited_at')
            ->selectRaw("stage, AVG(julianday(exited_at) - julianday(entered_at)) as avg_days")
            ->groupBy('stage')
            ->get()
            ->pluck('avg_days', 'stage')
            ->toArray();

        // 当前各阶段客户分布（带详细信息）
        $stageDetail = [];
        foreach (CustomerLifecycleStage::STAGES as $stage => $label) {
            $count = $stageDistribution[$stage] ?? 0;
            $revenue = $stageRevenue[$stage] ?? null;
            $movedIn = $recentTransitions[$stage] ?? 0;
            $avgDays = isset($avgDuration[$stage]) ? round((float) $avgDuration[$stage], 1) : null;

            // 获取该阶段的代表性客户
            $sampleCustomers = Customer::where('tenant_id', $tenantId)
                ->where('lifecycle_stage', $stage)
                ->orderByDesc('stage_entered_at')
                ->limit(5)
                ->get(['id']);

            $stageDetail[$stage] = [
                'name' => $stage,
                'label' => $label,
                'count' => $count,
                'percentage' => $this->calculatePercentage($count, array_sum($stageDistribution)),
                'total_mrr' => $revenue ? round((float) $revenue['total_mrr'], 2) : 0,
                'subscription_count' => $revenue ? (int) $revenue['sub_count'] : 0,
                'moved_in_30d' => $movedIn,
                'avg_duration_days' => $avgDays,
                'sample_customers' => $sampleCustomers->pluck('id')->toArray(),
            ];
        }

        return [
            'stages' => $stageDetail,
            'total_customers' => array_sum($stageDistribution),
            'recent_transitions' => $recentTransitions,
            'avg_duration' => $avgDuration,
            'evaluated_at' => $now->toDateTimeString(),
        ];
    }

    /**
     * 获取客户生命周期评分（0-100）
     */
    public function getLifecycleScore(Customer $customer): array
    {
        $rfm = RfmScore::where('customer_id', $customer->id)->first();
        $subCount = $customer->subscriptions()->count();
        $activeSubs = $customer->subscriptions()->whereIn('status', ['active', 'grace'])->count();
        $invoiceTotal = (float) $customer->invoices()->sum('amount');
        $ageInDays = $customer->created_at->diffInDays(now());

        // 参与度评分 (0-30)
        $engagementScore = 0;
        if ($rfm) {
            $engagementScore += $rfm->frequency_score * 6; // 0-30
        } elseif ($activeSubs > 0) {
            $engagementScore += 15; // 默认中等
        }

        // 消费力评分 (0-30)
        $spendingScore = 0;
        if ($invoiceTotal > 10000) $spendingScore = 30;
        elseif ($invoiceTotal > 5000) $spendingScore = 25;
        elseif ($invoiceTotal > 1000) $spendingScore = 20;
        elseif ($invoiceTotal > 100) $spendingScore = 10;
        elseif ($invoiceTotal > 0) $spendingScore = 5;

        // 忠诚度评分 (0-25)
        $loyaltyScore = 0;
        if ($ageInDays > 730) $loyaltyScore = 25;     // >2年
        elseif ($ageInDays > 365) $loyaltyScore = 20;  // >1年
        elseif ($ageInDays > 180) $loyaltyScore = 15;   // >6个月
        elseif ($ageInDays > 90) $loyaltyScore = 10;    // >3个月
        elseif ($ageInDays > 30) $loyaltyScore = 5;     // >1个月

        // 续费健康度 (0-15)
        $renewalScore = 0;
        if ($subCount > 0) {
            $autoRenewCount = $customer->subscriptions()->where('auto_renew', true)->count();
            $renewalScore = min(15, ($autoRenewCount / $subCount) * 15);
        }

        $totalScore = min(100, $engagementScore + $spendingScore + $loyaltyScore + $renewalScore);

        $grade = $totalScore >= 80 ? 'gold' : ($totalScore >= 60 ? 'silver' : ($totalScore >= 40 ? 'bronze' : 'basic'));

        return [
            'score' => $totalScore,
            'grade' => $grade,
            'dimensions' => [
                'engagement' => round($engagementScore, 1),
                'spending' => round($spendingScore, 1),
                'loyalty' => round($loyaltyScore, 1),
                'renewal_health' => round($renewalScore, 1),
            ],
            'customer_age_days' => $ageInDays,
        ];
    }

    /**
     * 计算百分比
     */
    protected function calculatePercentage(int $part, int $total): float
    {
        if ($total === 0) return 0;
        return round(($part / $total) * 100, 1);
    }
}
