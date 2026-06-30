<?php

namespace App\Services;

use App\Models\MonthlyRevenueSnapshot;
use App\Models\MrrChangeDetail;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MRR 瀑布图服务 (M3-59)
 */
class MrrWaterfallService
{
    public function getWaterfall(int $tenantId, int $months = 6, ?string $yearMonth = null): array
    {
        $end = $yearMonth ? Carbon::parse($yearMonth . '-01')->endOfMonth() : now();
        $start = (clone $end)->subMonths($months - 1)->startOfMonth();

        $snapshots = MonthlyRevenueSnapshot::where('tenant_id', $tenantId)
            ->where('year_month', '>=', $start->format('Y-m'))
            ->where('year_month', '<=', $end->format('Y-m'))
            ->orderBy('year_month')
            ->get()
            ->keyBy('year_month');

        $waterfall = [];
        $previousMrr = null;

        for ($i = 0; $i < $months; $i++) {
            $date = (clone $start)->addMonths($i);
            $ym = $date->format('Y-m');
            $snapshot = $snapshots->get($ym);

            $mrr = 0;
            $newArr = 0;
            $expansionArr = 0;
            $contractionArr = 0;
            $churnedArr = 0;

            if ($snapshot) {
                $mrr = (float) $snapshot->recognized_revenue;
                $newArr = (float) $snapshot->net_new_arr;
                $expansionArr = (float) $snapshot->expansion_arr;
                $contractionArr = (float) $snapshot->contraction_arr;
                $churnedArr = (float) $snapshot->churned_arr;
            }

            $startingMrr = $previousMrr ?? $mrr - ($newArr + $expansionArr + $contractionArr + $churnedArr);

            $waterfall[] = [
                'month' => $ym,
                'month_label' => $date->format('Y年n月'),
                'starting_mrr' => round($startingMrr, 2),
                'new' => round($newArr, 2),
                'expansion' => round($expansionArr, 2),
                'contraction' => round(abs($contractionArr), 2),
                'churned' => round(abs($churnedArr), 2),
                'net_change' => round($newArr + $expansionArr + $contractionArr + $churnedArr, 2),
                'ending_mrr' => round($mrr, 2),
                'active_subscriptions' => $snapshot ? $snapshot->active_subscriptions : 0,
                'has_snapshot' => $snapshot !== null,
            ];

            $previousMrr = $mrr;
        }

        return $waterfall;
    }

    public function getSummary(int $tenantId, string $yearMonth): array
    {
        $snapshot = MonthlyRevenueSnapshot::where('tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->first();

        $totalSubscriptions = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $changes = MrrChangeDetail::where('tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->selectRaw('change_type, COUNT(*) as count, SUM(mrr_impact) as total_impact')
            ->groupBy('change_type')
            ->pluck('total_impact', 'change_type');

        return [
            'year_month' => $yearMonth,
            'total_subscriptions' => $totalSubscriptions,
            'mrr' => $snapshot ? (float) $snapshot->recognized_revenue : 0,
            'arr' => $snapshot ? (float) $snapshot->recognized_revenue * 12 : 0,
            'new_mrr' => (float) ($changes['new_subscription'] ?? 0),
            'expansion_mrr' => (float) ($changes['upgrade'] ?? 0),
            'contraction_mrr' => abs((float) ($changes['downgrade'] ?? 0)),
            'churned_mrr' => abs((float) ($changes['cancellation'] ?? 0)),
            'reactivation_mrr' => (float) ($changes['reactivation'] ?? 0),
            'net_mrr_change' => (float) (($changes['new_subscription'] ?? 0) + ($changes['upgrade'] ?? 0)
                + ($changes['downgrade'] ?? 0) + ($changes['cancellation'] ?? 0) + ($changes['reactivation'] ?? 0)),
            'has_snapshot' => $snapshot !== null,
        ];
    }

    public function getDrilldown(int $tenantId, string $yearMonth, ?string $changeType = null, int $perPage = 20)
    {
        $query = MrrChangeDetail::where('tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->with(['customer:id,name,email', 'subscription:id,plan', 'plan:id,name'])
            ->orderByDesc('occurred_at');

        if ($changeType) {
            $query->where('change_type', $changeType);
        }

        return $query->paginate($perPage);
    }

    public function getBreakdownByProduct(int $tenantId, string $yearMonth): array
    {
        return MrrChangeDetail::query()
            ->where('mrr_change_details.tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->leftJoin('pricing_plans', 'mrr_change_details.plan_id', '=', 'pricing_plans.id')
            ->select([
                'mrr_change_details.plan_id',
                DB::raw("COALESCE(pricing_plans.name, '未分类') as label"),
                DB::raw('COUNT(*) as change_count'),
                DB::raw('SUM(mrr_change_details.mrr_impact) as total_impact'),
            ])
            ->groupBy('mrr_change_details.plan_id', 'pricing_plans.name')
            ->orderByDesc('total_impact')
            ->get()
            ->map(fn ($row) => [
                'plan_id' => $row->plan_id,
                'label' => $row->label,
                'change_count' => (int) $row->change_count,
                'total_impact' => round((float) $row->total_impact, 2),
            ])
            ->values()
            ->all();
    }

    public function getBreakdownByRegion(int $tenantId, string $yearMonth): array
    {
        $details = MrrChangeDetail::where('tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->get(['mrr_impact', 'metadata']);

        $grouped = [];
        foreach ($details as $detail) {
            $region = $detail->metadata['region'] ?? $detail->metadata['country'] ?? '未指定';
            if (!isset($grouped[$region])) {
                $grouped[$region] = ['change_count' => 0, 'total_impact' => 0];
            }
            $grouped[$region]['change_count']++;
            $grouped[$region]['total_impact'] += (float) $detail->mrr_impact;
        }

        return collect($grouped)->map(fn ($stats, $region) => [
            'region' => $region,
            'label' => $region,
            'change_count' => $stats['change_count'],
            'total_impact' => round($stats['total_impact'], 2),
        ])->sortByDesc('total_impact')->values()->all();
    }

    /**
     * 按客户分层下钻
     */
    public function getBreakdownByCustomerSegment(int $tenantId, string $yearMonth): array
    {
        $details = MrrChangeDetail::where('mrr_change_details.tenant_id', $tenantId)
            ->where('year_month', $yearMonth)
            ->leftJoin('customers', 'mrr_change_details.customer_id', '=', 'customers.id')
            ->select([
                DB::raw("COALESCE(customers.tier, '未分层') as segment"),
                DB::raw('COUNT(*) as change_count'),
                DB::raw('SUM(mrr_change_details.mrr_impact) as total_impact'),
            ])
            ->groupBy('customers.tier')
            ->orderByDesc('total_impact')
            ->get()
            ->map(fn ($row) => [
                'segment' => $row->segment,
                'label' => $this->getSegmentLabel($row->segment),
                'change_count' => (int) $row->change_count,
                'total_impact' => round((float) $row->total_impact, 2),
            ])
            ->values()
            ->all();

        return $details;
    }

    protected function getSegmentLabel(?string $tier): string
    {
        return match ($tier) {
            'enterprise' => '企业版',
            'professional' => '专业版',
            'standard' => '标准版',
            'free' => '免费版',
            default => $tier ?? '未分层',
        };
    }

    // ═══════════════════════════════════════════
    // MRR 变化记录自动填充
    // ═══════════════════════════════════════════

    /**
     * 记录订阅MRR变化
     */
    public function recordChange(
        int $tenantId,
        string $changeType,
        int $subscriptionId,
        int $customerId,
        ?int $planId,
        float $previousMrr,
        float $newMrr,
        ?string $reason = null,
        array $metadata = [],
    ): MrrChangeDetail {
        $mrrImpact = round($newMrr - $previousMrr, 2);
        $yearMonth = now()->format('Y-m');

        return MrrChangeDetail::create([
            'tenant_id' => $tenantId,
            'year_month' => $yearMonth,
            'change_type' => $changeType,
            'subscription_id' => $subscriptionId,
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'previous_mrr' => $previousMrr,
            'new_mrr' => $newMrr,
            'mrr_impact' => $mrrImpact,
            'currency' => 'CNY',
            'reason' => $reason,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * 扫描所有订阅并生成当月MRR变化记录
     */
    public function scanAndRecordMonthlyChanges(int $tenantId, ?string $yearMonth = null): array
    {
        $yearMonth = $yearMonth ?? now()->format('Y-m');
        $startOfMonth = Carbon::parse($yearMonth . '-01')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $prevMonthEnd = $startOfMonth->copy()->subDay();

        $recorded = 0;

        // 当月新增订阅
        $newSubs = Subscription::where('tenant_id', $tenantId)
            ->whereDate('starts_at', '>=', $startOfMonth)
            ->whereDate('starts_at', '<=', $endOfMonth)
            ->get();

        foreach ($newSubs as $sub) {
            $monthlyMrr = $this->subscriptionToMonthlyMrr($sub);
            $this->recordChange(
                $tenantId, 'new_subscription',
                $sub->id, $sub->customer_id, $sub->plan_id,
                0, $monthlyMrr,
                '新增订阅',
                ['region' => $sub->metadata['region'] ?? null],
            );
            $recorded++;
        }

        // 当月取消的订阅（ends_at 在当月）
        $cancelledSubs = Subscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['cancelled', 'expired'])
            ->whereDate('ends_at', '>=', $startOfMonth)
            ->whereDate('ends_at', '<=', $endOfMonth)
            ->get();

        foreach ($cancelledSubs as $sub) {
            $monthlyMrr = $this->subscriptionToMonthlyMrr($sub);
            $this->recordChange(
                $tenantId, 'cancellation',
                $sub->id, $sub->customer_id, $sub->plan_id,
                $monthlyMrr, 0,
                '订阅取消',
                ['region' => $sub->metadata['region'] ?? null],
            );
            $recorded++;
        }

        return [
            'year_month' => $yearMonth,
            'recorded' => $recorded,
        ];
    }

    /**
     * 将订阅的price转换为月均MRR
     */
    protected function subscriptionToMonthlyMrr(Subscription $sub): float
    {
        return match ($sub->billing_period) {
            'yearly' => round((float) $sub->price / 12, 2),
            'semi_annually' => round((float) $sub->price / 6, 2),
            'quarterly' => round((float) $sub->price / 3, 2),
            default => (float) $sub->price,
        };
    }
}
