<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\PricingPlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 高级报表控制器
 *
 * 提供收入趋势、ARR/MRR 分析、订阅指标、客户生命周期价值等高级报表数据。
 */
class ReportController extends Controller
{
    /**
     * 综合财务仪表盘
     */
    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        // 总收入
        $totalRevenue = (float) Invoice::where('status', 'paid')->sum('amount');
        $monthRevenue = (float) Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('amount');
        $yearRevenue = (float) Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $startOfYear)
            ->sum('amount');

        // MRR 计算
        $monthlyMrr = (float) Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'monthly')
            ->sum('price');
        $yearlyMrr = (float) Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'yearly')
            ->sum('price') / 12;
        $quarterlyMrr = (float) Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'quarterly')
            ->sum('price') / 3;
        $semiMrr = (float) Subscription::whereIn('status', ['active', 'grace'])
            ->where('billing_period', 'semi_annually')
            ->sum('price') / 6;

        $totalMrr = round($monthlyMrr + $yearlyMrr + $quarterlyMrr + $semiMrr, 2);
        $arr = round($totalMrr * 12, 2);

        // 订阅统计
        $subCounts = Subscription::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'grace' THEN 1 ELSE 0 END) as grace,
            SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
            SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled
        ")->first();

        // 客户统计
        $customerCount = Customer::count();
        $activeCustomers = Customer::whereHas('subscriptions', function ($q) {
            $q->whereIn('status', ['active', 'grace']);
        })->count();

        // 待处理金额
        $pendingAmount = (float) Invoice::where('status', 'pending')->sum('amount');

        // 统计信息
        $stats = [
            'total_revenue' => $totalRevenue,
            'month_revenue' => $monthRevenue,
            'year_revenue' => $yearRevenue,
            'mrr' => $totalMrr,
            'arr' => $arr,
            'mrr_breakdown' => [
                'monthly' => round($monthlyMrr, 2),
                'quarterly' => round($quarterlyMrr, 2),
                'semi_annually' => round($semiMrr, 2),
                'yearly' => round($yearlyMrr, 2),
            ],
            'subscriptions' => [
                'total' => (int) ($subCounts->total ?? 0),
                'active' => (int) ($subCounts->active ?? 0),
                'grace' => (int) ($subCounts->grace ?? 0),
                'expired' => (int) ($subCounts->expired ?? 0),
                'canceled' => (int) ($subCounts->canceled ?? 0),
            ],
            'customers' => [
                'total' => $customerCount,
                'active' => $activeCustomers,
            ],
            'pending_amount' => $pendingAmount,
            'total_plans' => PricingPlan::active()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * 收入趋势（按月）
     */
    public function revenueTrend(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $months = (int) $request->input('months', 12);
        $period = $request->input('period', 'monthly');

        $query = Invoice::where('status', 'paid')
            ->whereNotNull('paid_at');

        if ($period === 'monthly') {
            $periodExpr = db_date_format('paid_at', '%Y-%m');
            $trend = $query->selectRaw(
                "{$periodExpr} as period,
                 SUM(amount) as revenue,
                 SUM(COALESCE(discount_amount, 0)) as discounts,
                 COUNT(*) as invoice_count"
            )
                ->where('paid_at', '>=', now()->subMonths($months)->startOfMonth())
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        } else {
            $trend = $query->selectRaw(
                "DATE(paid_at) as period,
                 SUM(amount) as revenue,
                 SUM(COALESCE(discount_amount, 0)) as discounts,
                 COUNT(*) as invoice_count"
            )
                ->where('paid_at', '>=', now()->subDays($months > 12 ? 90 : 30))
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        }

        // 计算环比增长
        $data = $trend->values()->toArray();
        for ($i = 1; $i < count($data); $i++) {
            $prev = $data[$i - 1]['revenue'] ?? 0;
            $curr = $data[$i]['revenue'] ?? 0;
            $data[$i]['growth_rate'] = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 2) : 0;
        }
        if (isset($data[0])) {
            $data[0]['growth_rate'] = 0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'trend' => $data,
            ],
        ]);
    }

    /**
     * ARR/MRR 趋势分析
     */
    public function mrrTrend(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $months = (int) $request->input('months', 12);

        // 获取每月活跃订阅数据
        $history = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $endOfMonth = $date->copy()->endOfMonth();

            $activeSubs = Subscription::where('created_at', '<=', $endOfMonth)
                ->where(function ($q) use ($endOfMonth) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', $endOfMonth);
                })
                ->whereIn('status', ['active', 'grace'])
                ->get();

            $monthly = $activeSubs->where('billing_period', 'monthly')->sum('price');
            $yearly = $activeSubs->where('billing_period', 'yearly')->sum('price');
            $quarterly = $activeSubs->where('billing_period', 'quarterly')->sum('price');
            $semi = $activeSubs->where('billing_period', 'semi_annually')->sum('price');

            $mrr = round((float) $monthly + ((float) $yearly / 12) + ((float) $quarterly / 3) + ((float) $semi / 6), 2);
            $arr = round($mrr * 12, 2);

            $history[] = [
                'month' => $date->format('Y-m'),
                'label' => $date->format('Y年n月'),
                'mrr' => $mrr,
                'arr' => $arr,
                'active_subscriptions' => $activeSubs->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * 订阅分析
     */
    public function subscriptionAnalytics(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        // 各状态分布
        $byStatus = Subscription::selectRaw('status, COUNT(*) as count, SUM(price) as total_value')
            ->groupBy('status')
            ->get()
            ->pluck(null, 'status');

        // 按计费周期分布
        $byPeriod = Subscription::selectRaw('billing_period, COUNT(*) as count, SUM(price) as total_value')
            ->groupBy('billing_period')
            ->get();

        // 近期取消趋势
        $cancelMonthExpr = db_date_format('canceled_at', '%Y-%m');
        $cancelTrend = Subscription::whereNotNull('canceled_at')
            ->where('canceled_at', '>=', now()->subMonths(6))
            ->selectRaw("{$cancelMonthExpr} as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 即将到期
        $expiringSoon = Subscription::whereIn('status', ['active', 'grace'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays(30))
            ->where('ends_at', '>', now())
            ->count();

        // 平均订阅时长（活跃订阅）
        $avgLifetime = Subscription::whereIn('status', ['active', 'grace'])
            ->selectRaw('AVG('.db_date_diff('COALESCE(ends_at, NOW())', 'created_at').') as avg_days')
            ->value('avg_days');

        return response()->json([
            'success' => true,
            'data' => [
                'by_status' => $byStatus,
                'by_period' => $byPeriod,
                'cancel_trend' => $cancelTrend,
                'expiring_soon_30d' => $expiringSoon,
                'avg_subscription_days' => round((float) ($avgLifetime ?? 0)),
            ],
        ]);
    }

    /**
     * 定价方案分布
     */
    public function planDistribution(): JsonResponse
    {
        $this->authorize('viewAny', PricingPlan::class);

        $plans = PricingPlan::withCount(['subscriptions' => function ($q) {
            $q->whereIn('status', ['active', 'grace']);
        }])->active()->ordered()->get()->map(function ($plan) {
            return [
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price_monthly' => (float) ($plan->price_monthly ?? 0),
                'subscriber_count' => $plan->subscriptions_count,
                'revenue_monthly' => round((float) ($plan->price_monthly ?? 0) * $plan->subscriptions_count, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * 客户生命周期价值分析
     */
    public function customerLifetimeValue(): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        // 所有有付费记录的客户
        $customers = Customer::whereHas('invoices', function ($q) {
            $q->where('status', 'paid');
        })->withCount(['invoices as paid_invoices' => function ($q) {
            $q->where('status', 'paid');
        }])->withSum(['invoices as total_paid' => function ($q) {
            $q->where('status', 'paid');
        }], 'amount')->get();

        $totalCustomers = $customers->count();
        $totalRevenue = $customers->sum('total_paid');
        $avgLtv = $totalCustomers > 0 ? round($totalRevenue / $totalCustomers, 2) : 0;

        // LTV 分布
        $tiers = [
            'high' => $customers->where('total_paid', '>=', 10000)->count(),
            'medium' => $customers->where('total_paid', '>=', 1000)->where('total_paid', '<', 10000)->count(),
            'low' => $customers->where('total_paid', '>', 0)->where('total_paid', '<', 1000)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $totalCustomers,
                'total_revenue' => round((float) $totalRevenue, 2),
                'average_ltv' => $avgLtv,
                'max_ltv' => round((float) $customers->max('total_paid') ?? 0, 2),
                'median_ltv' => $this->calculateMedian($customers->pluck('total_paid')->toArray()),
                'tiers' => $tiers,
                'top_customers' => $customers->sortByDesc('total_paid')->take(10)->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'email' => $c->user->email ?? null,
                        'total_paid' => round((float) ($c->total_paid ?? 0), 2),
                        'invoice_count' => (int) ($c->paid_invoices ?? 0),
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * 客户流失分析
     */
    public function churnAnalysis(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $now = now();

        // 月度流失率计算
        $churnData = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $now->copy()->subMonths($i)->endOfMonth();

            // 月初活跃订阅数
            $startActive = Subscription::where('created_at', '<=', $monthStart)
                ->where(function ($q) use ($monthStart) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', $monthStart);
                })
                ->whereIn('status', ['active', 'grace'])
                ->count();

            // 本月新增
            $newSubs = Subscription::whereBetween('created_at', [$monthStart, $monthEnd])->count();

            // 本月流失（取消或过期）
            $churned = Subscription::where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('canceled_at', [$monthStart, $monthEnd]);
            })->orWhere(function ($q) use ($monthStart, $monthEnd) {
                $q->where('status', 'expired')
                  ->whereBetween('updated_at', [$monthStart, $monthEnd]);
            })->count();

            $churnRate = $startActive > 0 ? round(($churned / $startActive) * 100, 2) : 0;

            $churnData[] = [
                'month' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('Y年n月'),
                'start_active' => $startActive,
                'new_subscriptions' => $newSubs,
                'churned' => $churned,
                'churn_rate' => $churnRate,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $churnData,
        ]);
    }

    /**
     * 计算中位数
     */
    protected function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        if ($count === 0) return 0;
        $middle = (int) floor($count / 2);
        if ($count % 2 === 0) {
            return round(($values[$middle - 1] + $values[$middle]) / 2, 2);
        }
        return round($values[$middle], 2);
    }
}
