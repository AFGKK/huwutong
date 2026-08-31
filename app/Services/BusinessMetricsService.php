<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 业务指标看板服务 (M2-121)
 *
 * 提供与技术指标分离的纯业务看板数据：
 * MRR/ARR/Churn Rate/LTV/CAC/激活转化率/续费率 + 同比环比趋势
 */
class BusinessMetricsService
{
    /**
     * 看板总览 — 一次性返回所有核心业务指标
     */
    public function overview(): array
    {
        return [
            'mrr' => $this->currentMrr(),
            'arr' => $this->currentArr(),
            'churn_rate' => $this->churnRate(),
            'ltv' => $this->averageLtv(),
            'cac' => $this->customerAcquisitionCost(),
            'ltv_cac_ratio' => $this->ltvCacRatio(),
            'renewal_rate' => $this->renewalRate(),
            'activation_rate' => $this->activationRate(),
            'trial_conversion_rate' => $this->trialConversionRate(),
            'active_customers' => $this->activeCustomers(),
            'total_subscriptions' => $this->totalSubscriptions(),
            'total_revenue' => $this->totalRevenue(),
        ];
    }

    /**
     * 当前 MRR
     */
    public function currentMrr(): float
    {
        $monthly = (float) Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'monthly')->sum('price');
        $yearly = (float) Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'yearly')->sum('price') / 12;
        $quarterly = (float) Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'quarterly')->sum('price') / 3;
        $semi = (float) Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'semi_annually')->sum('price') / 6;

        return round($monthly + $yearly + $quarterly + $semi, 2);
    }

    /**
     * 当前 ARR = MRR * 12
     */
    public function currentArr(): float
    {
        return round($this->currentMrr() * 12, 2);
    }

    /**
     * 综合流失率（月度）
     */
    public function churnRate(int $months = 3): float
    {
        $end = now()->endOfMonth();
        $start = $end->copy()->subMonths($months)->startOfMonth();

        $beginActive = Subscription::whereIn('status', ['active', 'grace'])
            ->where('created_at', '<=', $start)
            ->where(function ($q) use ($start) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $start);
            })->count();

        $churned = Subscription::where('status', 'expired')
            ->whereBetween('ends_at', [$start, $end])
            ->count();

        if ($beginActive <= 0) return 0;

        return round(($churned / $beginActive) / $months * 100, 2);
    }

    /**
     * 平均客户生命周期价值 (LTV)
     */
    public function averageLtv(): float
    {
        $customers = Customer::whereHas('invoices', function ($q) {
            $q->where('status', 'paid');
        })->withSum(['invoices as total_paid' => function ($q) {
            $q->where('status', 'paid');
        }], 'amount')->get();

        if ($customers->isEmpty()) return 0;

        return round($customers->avg('total_paid') ?? 0, 2);
    }

    /**
     * 客户获取成本 (CAC)
     */
    public function customerAcquisitionCost(): float
    {
        // 过去 3 个月的营销/佣金支出 ÷ 同期新增客户数
        $start = now()->subMonths(3)->startOfMonth();

        $marketingCost = (float) DB::table('commission_settlements')
            ->where('created_at', '>=', $start)
            ->whereIn('status', ['released', 'pending'])
            ->sum('commission_amount');

        $newCustomers = Customer::where('created_at', '>=', $start)->count();

        if ($newCustomers <= 0) return 0;

        return round($marketingCost / $newCustomers, 2);
    }

    /**
     * LTV / CAC 比率
     */
    public function ltvCacRatio(): float
    {
        $ltv = $this->averageLtv();
        $cac = $this->customerAcquisitionCost();

        if ($cac <= 0) return $ltv > 0 ? 0 : 0;

        return round($ltv / $cac, 2);
    }

    /**
     * 续费率（按账单周期）
     */
    public function renewalRate(): float
    {
        $start = now()->subMonths(3)->startOfMonth();

        $dueForRenewal = Subscription::whereIn('status', ['active', 'grace', 'expired'])
            ->whereBetween('ends_at', [$start, now()])
            ->count();

        $renewed = Subscription::whereIn('status', ['active', 'grace'])
            ->whereBetween('updated_at', [$start, now()])
            ->where('ends_at', '>', now())
            ->count();

        if ($dueForRenewal <= 0) return 0;

        return round(($renewed / $dueForRenewal) * 100, 2);
    }

    /**
     * License 激活率（已发放 License 中被激活的比例）
     */
    public function activationRate(): float
    {
        $total = License::count();
        $activated = License::where('status', 'active')->count();

        if ($total <= 0) return 0;

        return round(($activated / $total) * 100, 2);
    }

    /**
     * 试用转付费转化率
     */
    public function trialConversionRate(): float
    {
        $totalTrials = Subscription::whereNotNull('trial_ends_at')->count();
        $converted = Subscription::whereNotNull('trial_ends_at')
            ->whereIn('status', ['active', 'grace'])
            ->where('trial_ends_at', '<', now())
            ->count();

        if ($totalTrials <= 0) {
            return 0;
        }

        return round(($converted / $totalTrials) * 100, 2);
    }

    /**
     * 活跃客户数
     */
    public function activeCustomers(): int
    {
        return Customer::whereHas('subscriptions', function ($q) {
            $q->whereIn('status', ['active', 'grace']);
        })->count();
    }

    /**
     * 总订阅数（活跃+宽限）
     */
    public function totalSubscriptions(): int
    {
        return Subscription::whereIn('status', ['active', 'grace'])->count();
    }

    /**
     * 历史总收入
     */
    public function totalRevenue(): float
    {
        return (float) Invoice::where('status', 'paid')->sum('amount');
    }

    /**
     * MRR 月度趋势（同比环比）
     */
    public function mrrTrend(int $months = 12): array
    {
        $result = [];
        $now = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $result[] = [
                'period' => $date->format('Y-m'),
                'label' => $date->format(__('app.business_metrics.business_metrics_5b68601674')),
                'mrr' => $this->estimateMrrForMonth($date),
            ];
        }

        // 计算同比环比
        for ($i = 0; $i < count($result); $i++) {
            // 环比 (MoM)
            if ($i > 0 && $result[$i - 1]['mrr'] > 0) {
                $result[$i]['mom_change'] = round(
                    (($result[$i]['mrr'] - $result[$i - 1]['mrr']) / $result[$i - 1]['mrr']) * 100, 2
                );
            } else {
                $result[$i]['mom_change'] = 0;
            }

            // 同比 (YoY)
            if ($i >= 12 && $result[$i - 12]['mrr'] > 0) {
                $result[$i]['yoy_change'] = round(
                    (($result[$i]['mrr'] - $result[$i - 12]['mrr']) / $result[$i - 12]['mrr']) * 100, 2
                );
            } else {
                $result[$i]['yoy_change'] = null;
            }
        }

        return $result;
    }

    /**
     * 关键指标趋势（同比环比）
     */
    public function metricTrends(int $months = 12): array
    {
        return [
            'mrr' => $this->mrrTrend($months),
            'churn_rate' => $this->churnRateTrend($months),
            'new_customers' => $this->newCustomerTrend($months),
            'revenue' => $this->revenueTrend($months),
        ];
    }

    /**
     * 流失率月度趋势
     */
    public function churnRateTrend(int $months = 12): array
    {
        $result = [];
        $now = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $end = $now->copy()->subMonths($i)->endOfMonth();
            $start = $end->copy()->startOfMonth();

            $beginActive = Subscription::whereIn('status', ['active', 'grace'])
                ->where('created_at', '<=', $start)
                ->where(function ($q) use ($start) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $start);
                })->count();

            $churned = Subscription::where('status', 'expired')
                ->whereBetween('ends_at', [$start, $end])
                ->count();

            $result[] = [
                'period' => $end->format('Y-m'),
                'label' => $end->format(__('app.business_metrics.business_metrics_5b68601674')),
                'churn_rate' => $beginActive > 0 ? round(($churned / $beginActive) * 100, 2) : 0,
                'churned_count' => $churned,
                'active_begin' => $beginActive,
            ];
        }

        return $result;
    }

    /**
     * 新增客户月度趋势
     */
    public function newCustomerTrend(int $months = 12): array
    {
        $result = [];
        $now = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $now->copy()->subMonths($i)->endOfMonth();

            $count = Customer::whereBetween('created_at', [$start, $end])->count();

            $result[] = [
                'period' => $start->format('Y-m'),
                'label' => $start->format(__('app.business_metrics.business_metrics_5b68601674')),
                'count' => $count,
            ];
        }

        return $result;
    }

    /**
     * 月度收入趋势
     */
    public function revenueTrend(int $months = 12): array
    {
        $result = [];
        $now = now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $now->copy()->subMonths($i)->endOfMonth();

            $revenue = (float) Invoice::where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');

            $result[] = [
                'period' => $start->format('Y-m'),
                'label' => $start->format(__('app.business_metrics.business_metrics_5b68601674')),
                'revenue' => $revenue,
            ];
        }

        return $result;
    }

    /**
     * 同期群分析（Cohort Analysis）
     */
    public function cohortAnalysis(): array
    {
        $cohortMonths = config('business-metrics.retention.cohort_months', 12);
        $cohorts = [];
        $now = now();

        for ($i = $cohortMonths - 1; $i >= 0; $i--) {
            $cohortStart = $now->copy()->subMonths($i)->startOfMonth();
            $cohortEnd = $cohortStart->copy()->endOfMonth();

            $cohortCustomers = Customer::whereBetween('created_at', [$cohortStart, $cohortEnd])
                ->pluck('id');

            if ($cohortCustomers->isEmpty()) continue;

            $retention = [];
            for ($m = 0; $m <= min(11, $cohortMonths - $i - 1); $m++) {
                $periodStart = $cohortStart->copy()->addMonths($m);
                $periodEnd = $periodStart->copy()->endOfMonth();

                $active = DB::table('subscriptions')
                    ->whereIn('customer_id', $cohortCustomers)
                    ->whereIn('status', ['active', 'grace'])
                    ->where('created_at', '<=', $periodEnd)
                    ->where(function ($q) use ($periodEnd) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', $periodStart);
                    })
                    ->distinct()
                    ->count('customer_id');

                $retention[] = [
                    'month' => $m,
                    'active_customers' => $active,
                    'retention_rate' => $cohortCustomers->count() > 0
                        ? round(($active / $cohortCustomers->count()) * 100, 1)
                        : 0,
                ];
            }

            $cohorts[] = [
                'cohort' => $cohortStart->format('Y-m'),
                'label' => $cohortStart->format(__('app.business_metrics.business_metrics_5b68601674')),
                'total_customers' => $cohortCustomers->count(),
                'retention' => $retention,
            ];
        }

        return $cohorts;
    }

    /**
     * 导出报表数据
     */
    public function exportData(string $format = 'csv', int $months = 12): array
    {
        $data = [
            'overview' => $this->overview(),
            'mrr_trend' => $this->mrrTrend($months),
            'revenue_trend' => $this->revenueTrend($months),
            'churn_trend' => $this->churnRateTrend($months),
            'new_customers' => $this->newCustomerTrend($months),
            'cohorts' => $this->cohortAnalysis(),
        ];

        if ($format === 'csv') {
            return $this->toCsvRows($data);
        }

        return $data;
    }

    /**
     * 转换为 CSV 行数据
     */
    protected function toCsvRows(array $data): array
    {
        $rows = [];

        // 总览
        $rows[] = ['section' => __('app.business_metrics.business_metrics_9e75621ec5'), __('app.business_metrics.business_metrics_7e687515fc'), '值', '', ''];
        $rows[] = ['', 'MRR', $data['overview']['mrr'] ?? 0, '', ''];
        $rows[] = ['', 'ARR', $data['overview']['arr'] ?? 0, '', ''];
        $rows[] = ['', __('app.business_metrics.business_metrics_2d0fa6ca93'), $data['overview']['churn_rate'] ?? 0, '', ''];
        $rows[] = ['', 'LTV', $data['overview']['ltv'] ?? 0, '', ''];
        $rows[] = ['', 'CAC', $data['overview']['cac'] ?? 0, '', ''];
        $rows[] = ['', 'LTV/CAC', $data['overview']['ltv_cac_ratio'] ?? 0, '', ''];
        $rows[] = ['', __('app.business_metrics.business_metrics_edec7d561d'), $data['overview']['renewal_rate'] ?? 0, '', ''];
        $rows[] = ['', __('app.business_metrics.business_metrics_2b9d3fb593'), $data['overview']['activation_rate'] ?? 0, '', ''];
        $rows[] = ['', __('app.business_metrics.business_metrics_4ca2684fc9'), $data['overview']['trial_conversion_rate'] ?? 0, '', ''];
        $rows[] = [];

        // MRR 趋势
        $rows[] = ['section' => __('app.business_metrics.business_metrics_319db1e3ed'), __('app.business_metrics.business_metrics_8190915888'), 'MRR', __('app.business_metrics.business_metrics_00241994fe'), __('app.business_metrics.business_metrics_1168840de2')];
        foreach ($data['mrr_trend'] as $item) {
            $rows[] = ['', $item['label'], $item['mrr'], $item['mom_change'] ?? 0, $item['yoy_change'] ?? 'N/A'];
        }
        $rows[] = [];

        // 收入趋势
        $rows[] = ['section' => __('app.business_metrics.business_metrics_d9ba6b8624'), __('app.business_metrics.business_metrics_8190915888'), __('app.business_metrics.business_metrics_6c2fb35be5'), '', ''];
        foreach ($data['revenue_trend'] as $item) {
            $rows[] = ['', $item['label'], $item['revenue'], '', ''];
        }
        $rows[] = [];

        // 流失趋势
        $rows[] = ['section' => __('app.business_metrics.business_metrics_ec3b691f2f'), __('app.business_metrics.business_metrics_8190915888'), __('app.business_metrics.business_metrics_2d0fa6ca93'), __('app.business_metrics.business_metrics_7f0c2eee5d'), __('app.business_metrics.business_metrics_4e6dc3a3ef')];
        foreach ($data['churn_trend'] as $item) {
            $rows[] = ['', $item['label'], $item['churn_rate'], $item['churned_count'], $item['active_begin']];
        }

        return $rows;
    }

    /**
     * 估算指定月份的 MRR（基于当月有效订阅）
     */
    protected function estimateMrrForMonth(Carbon $date): float
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $monthly = (float) Subscription::where('billing_period', 'monthly')
            ->where('created_at', '<=', $endOfMonth)
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $startOfMonth);
            })
            ->whereIn('status', ['active', 'grace'])
            ->sum('price');

        $yearly = (float) Subscription::where('billing_period', 'yearly')
            ->where('created_at', '<=', $endOfMonth)
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $startOfMonth);
            })
            ->whereIn('status', ['active', 'grace'])
            ->sum('price') / 12;

        $quarterly = (float) Subscription::where('billing_period', 'quarterly')
            ->where('created_at', '<=', $endOfMonth)
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $startOfMonth);
            })
            ->whereIn('status', ['active', 'grace'])
            ->sum('price') / 3;

        $semi = (float) Subscription::where('billing_period', 'semi_annually')
            ->where('created_at', '<=', $endOfMonth)
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $startOfMonth);
            })
            ->whereIn('status', ['active', 'grace'])
            ->sum('price') / 6;

        return round($monthly + $yearly + $quarterly + $semi, 2);
    }
}
