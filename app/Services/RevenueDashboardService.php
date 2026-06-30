<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\CommissionSettlement;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\SubscriptionAgent;
use Illuminate\Support\Facades\DB;

/**
 * 平台收益总览 & 渠道 ROI 分析服务
 *
 * M3-73 面向管理员的收益分析仪表盘：
 * - 平台整体收益数据（收入/退款/佣金/净收入）
 * - 按渠道来源（attribution_source）分组的收益与 ROI
 * - 渠道收益趋势和排名
 * - 渠道质量分析（客户流失率、LTV）
 */
class RevenueDashboardService
{
    /**
     * 获取跨数据库兼容的日期格式化 SQL 表达式
     */
    protected function dateFormatSql(string $column, string $format): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: strftime('%Y-%m', column)
            $sqliteFormat = str_replace(['%Y', '%m', '%d'], ['%Y', '%m', '%d'], $format);
            return "strftime('{$sqliteFormat}', {$column})";
        }

        // MySQL / MariaDB
        return "DATE_FORMAT({$column}, '{$format}')";
    }

    /**
     * 获取跨数据库兼容的 DATEDIFF SQL 表达式
     */
    protected function dateDiffSql(string $end, string $start): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "julianday({$end}) - julianday({$start})";
        }

        return "DATEDIFF({$end}, {$start})";
    }
    /**
     * 平台收益总览
     */
    public function platformOverview(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $yearStart = $now->copy()->startOfYear();

        // 总收入
        $totalRevenue = (float) Invoice::where('status', 'paid')->sum('amount');
        $monthRevenue = (float) Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)->sum('amount');
        $yearRevenue = (float) Invoice::where('status', 'paid')
            ->where('paid_at', '>=', $yearStart)->sum('amount');

        // 退款总额
        $totalRefunds = (float) Refund::where('status', 'completed')->sum('amount');
        $monthRefunds = (float) Refund::where('status', 'completed')
            ->where('created_at', '>=', $monthStart)->sum('amount');

        // 佣金支出
        $totalCommissions = (float) CommissionSettlement::whereIn('status', ['released', 'pending'])
            ->sum('commission_amount');
        $monthCommissions = (float) CommissionSettlement::whereIn('status', ['released', 'pending'])
            ->where('period', $now->format('Y-m'))->sum('commission_amount');

        // 已提现金额
        $totalPayouts = (float) CommissionPayout::where('status', 'completed')->sum('net_amount');
        $monthPayouts = (float) CommissionPayout::where('status', 'completed')
            ->where('processed_at', '>=', $monthStart)->sum('net_amount');

        // 净收入 = 总收入 - 退款 - 佣金支出
        $netRevenue = round($totalRevenue - $totalRefunds - $totalCommissions, 2);
        $monthNetRevenue = round($monthRevenue - $monthRefunds - $monthCommissions, 2);

        // MRR 计算
        $mrr = $this->calculateMrr();
        $arr = round($mrr * 12, 2);

        // 订阅统计
        $activeSubscriptions = Subscription::whereIn('status', ['active', 'grace'])->count();
        $newSubscriptions = Subscription::where('created_at', '>=', $monthStart)->count();
        $churnedSubscriptions = Subscription::where('status', 'expired')
            ->where('ends_at', '>=', $monthStart)->count();

        // 活跃代理
        $activeAgents = Agent::where('status', 'active')->count();

        return [
            'revenue' => [
                'total' => $totalRevenue,
                'month' => $monthRevenue,
                'year' => $yearRevenue,
            ],
            'refunds' => [
                'total' => $totalRefunds,
                'month' => $monthRefunds,
                'refund_rate' => $totalRevenue > 0 ? round($totalRefunds / $totalRevenue * 100, 2) : 0,
            ],
            'commissions' => [
                'total' => $totalCommissions,
                'month' => $monthCommissions,
                'commission_rate' => $totalRevenue > 0 ? round($totalCommissions / $totalRevenue * 100, 2) : 0,
            ],
            'payouts' => [
                'total' => $totalPayouts,
                'month' => $monthPayouts,
            ],
            'net_revenue' => [
                'total' => $netRevenue,
                'month' => $monthNetRevenue,
            ],
            'mrr' => $mrr,
            'arr' => $arr,
            'subscriptions' => [
                'active' => $activeSubscriptions,
                'new' => $newSubscriptions,
                'churned' => $churnedSubscriptions,
            ],
            'active_agents' => $activeAgents,
        ];
    }

    /**
     * 渠道 ROI 分析
     *
     * 按 attribution_source 分组计算各渠道的收益和 ROI。
     * attribution_source 取值：link（推广链接）、code（邀请码）、direct（直接注册）、api（API注册）、organic（自然流量）
     */
    public function channelRoi(): array
    {
        $channels = $this->getChannelDefinitions();
        $results = [];

        foreach ($channels as $key => $info) {
            $stats = $this->calculateChannelStats($key);
            $results[] = array_merge($info, $stats);
        }

        // 计算总览汇总行
        $overall = $this->calculateOverallStats($results);

        return [
            'channels' => $results,
            'overall' => $overall,
            'definitions' => $channels,
        ];
    }

    /**
     * 渠道月度趋势
     */
    public function channelTrend(int $months = 12): array
    {
        $monthsData = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $period = now()->subMonths($i)->format('Y-m');
            $monthsData[$period] = [];
        }

        // 按月统计各渠道收入
        $channelRevenue = Invoice::selectRaw(
            $this->dateFormatSql('paid_at', '%Y-%m') . " as period, sa.attribution_source, SUM(i.amount) as revenue"
        )
            ->from('invoices as i')
            ->join('subscriptions as s', 'i.subscription_id', '=', 's.id')
            ->leftJoin('subscription_agents as sa', 's.id', '=', 'sa.subscription_id')
            ->where('i.status', 'paid')
            ->where('i.paid_at', '>=', now()->subMonths($months))
            ->groupBy('period', 'sa.attribution_source')
            ->get();

        $channelCommissions = CommissionSettlement::selectRaw(
            "period, sa.attribution_source, SUM(commission_amount) as commission"
        )
            ->from('commission_settlements as cs')
            ->join('subscription_agents as sa', 'cs.subscription_id', '=', 'sa.subscription_id')
            ->where('period', '>=', now()->subMonths($months)->format('Y-m'))
            ->groupBy('period', 'sa.attribution_source')
            ->get();

        // 按渠道分组的趋势
        $channels = array_keys($this->getChannelDefinitions());
        $channelTrends = [];
        foreach ($channels as $ch) {
            $trend = [];
            foreach ($monthsData as $period => $v) {
                $revenueRow = $channelRevenue->firstWhere(fn($r) => $r->period === $period && ($r->attribution_source ?? 'direct') === $ch);
                $commissionRow = $channelCommissions->firstWhere(fn($r) => $r->period === $period && ($r->attribution_source ?? 'direct') === $ch);

                $rev = (float) ($revenueRow->revenue ?? 0);
                $com = (float) ($commissionRow->commission ?? 0);
                $trend[] = [
                    'period' => $period,
                    'revenue' => $rev,
                    'commission' => $com,
                    'net_revenue' => round($rev - $com, 2),
                ];
            }
            $channelTrends[$ch] = $trend;
        }

        return $channelTrends;
    }

    /**
     * 渠道质量分析
     *
     * 分析各渠道带来的客户流失率、续费率、LTV 等质量指标
     */
    public function channelQuality(): array
    {
        $channels = $this->getChannelDefinitions();
        $results = [];

        foreach ($channels as $key => $info) {
            $agentIds = SubscriptionAgent::where('attribution_source', $key)
                ->pluck('subscription_id');
            
            $subscriptions = Subscription::whereIn('id', $agentIds);
            $totalSubs = (clone $subscriptions)->count();
            $activeSubs = (clone $subscriptions)->whereIn('status', ['active', 'grace'])->count();
            $churnedSubs = (clone $subscriptions)->where('status', 'expired')->count();

            // 客户数
            $customerIds = (clone $subscriptions)->pluck('customer_id')->unique();
            $totalCustomers = $customerIds->count();
            
            // LTV = 平均每位客户已付总额
            $avgLtv = 0;
            if ($totalCustomers > 0) {
                $totalPaid = Invoice::whereIn('subscription_id', $agentIds)
                    ->where('status', 'paid')
                    ->sum('amount');
                $avgLtv = round($totalPaid / $totalCustomers, 2);
            }

            // 流失率
            $churnRate = $totalSubs > 0 ? round($churnedSubs / $totalSubs * 100, 2) : 0;

            // 平均订阅时长（天）
            $avgDays = 0;
            if ($totalSubs > 0) {
                $daysData = (clone $subscriptions)->selectRaw('AVG(' . $this->dateDiffSql('COALESCE(ends_at, CURRENT_TIMESTAMP)', 'starts_at') . ') as avg_days')->first();
                $avgDays = round((float) ($daysData->avg_days ?? 0), 1);
            }

            // 续费率（有续费发票的订阅比例）
            $renewedSubs = (clone $subscriptions)->whereHas('invoices', fn($q) => $q->where('billing_reason', 'renewal'))->count();
            $renewalRate = $totalSubs > 0 ? round($renewedSubs / $totalSubs * 100, 2) : 0;

            $results[] = [
                'channel' => $key,
                'channel_name' => $info['name'],
                'total_subscriptions' => $totalSubs,
                'active_subscriptions' => $activeSubs,
                'churned_subscriptions' => $churnedSubs,
                'total_customers' => $totalCustomers,
                'avg_ltv' => $avgLtv,
                'churn_rate' => $churnRate,
                'avg_subscription_days' => $avgDays,
                'renewal_rate' => $renewalRate,
            ];
        }

        return $results;
    }

    /**
     * 月度收益趋势（近12/24个月）
     */
    public function revenueTrend(int $months = 24): array
    {
        $trend = Invoice::selectRaw(
            $this->dateFormatSql('paid_at', '%Y-%m') . " as period,
             SUM(amount) as revenue,
             COUNT(*) as transaction_count"
        )
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths($months))
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // 补充佣金支出趋势
        $commissionTrend = CommissionSettlement::selectRaw(
            "period, SUM(commission_amount) as commission"
        )
            ->where('period', '>=', now()->subMonths($months)->format('Y-m'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // 补充退款趋势
        $refundTrend = Refund::selectRaw(
            $this->dateFormatSql('created_at', '%Y-%m') . " as period, SUM(amount) as refunds"
        )
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $results = [];
        foreach ($trend as $row) {
            $commission = (float) ($commissionTrend[$row->period]->commission ?? 0);
            $refunds = (float) ($refundTrend[$row->period]->refunds ?? 0);
            $results[] = [
                'period' => $row->period,
                'revenue' => (float) $row->revenue,
                'refunds' => $refunds,
                'commission' => $commission,
                'net_revenue' => round((float) $row->revenue - $refunds - $commission, 2),
                'transaction_count' => (int) $row->transaction_count,
            ];
        }

        return $results;
    }

    /**
     * 渠道月度支付方式分布
     */
    public function paymentMethodDistribution(): array
    {
        $methods = Invoice::selectRaw(
            "payment_method, COUNT(*) as count, SUM(amount) as total"
        )
            ->where('status', 'paid')
            ->groupBy('payment_method')
            ->get();

        $totalAmount = $methods->sum('total');
        $totalCount = $methods->sum('count');

        return [
            'methods' => $methods->map(fn($m) => [
                'method' => $m->payment_method,
                'method_label' => match ($m->payment_method) {
                    'alipay' => '支付宝',
                    'wechat' => '微信支付',
                    'paypal' => 'PayPal',
                    'stripe' => 'Stripe',
                    'bank_transfer' => '银行转账',
                    'balance' => '余额支付',
                    default => $m->payment_method,
                },
                'count' => (int) $m->count,
                'total' => (float) $m->total,
                'percentage' => $totalAmount > 0 ? round($m->total / $totalAmount * 100, 1) : 0,
            ]),
            'total_amount' => $totalAmount,
            'total_count' => $totalCount,
        ];
    }

    /**
     * 代理层级收益分布
     */
    public function agentLevelDistribution(): array
    {
        $levels = ['regular', 'silver', 'gold', 'platinum'];
        $results = [];

        foreach ($levels as $level) {
            $agentIds = Agent::where('level', $level)->pluck('id');
            if ($agentIds->isEmpty()) {
                continue;
            }

            $totalCommission = (float) CommissionSettlement::whereIn('agent_id', $agentIds)
                ->whereIn('status', ['released', 'pending'])
                ->sum('commission_amount');
            $totalPayout = (float) CommissionPayout::whereIn('agent_id', $agentIds)
                ->where('status', 'completed')
                ->sum('net_amount');
            $agentCount = $agentIds->count();

            $results[] = [
                'level' => $level,
                'level_label' => match ($level) {
                    'regular' => '普通',
                    'silver' => '银牌',
                    'gold' => '金牌',
                    'platinum' => '铂金',
                },
                'agent_count' => $agentCount,
                'total_commission' => $totalCommission,
                'total_payout' => $totalPayout,
                'avg_commission_per_agent' => $agentCount > 0 ? round($totalCommission / $agentCount, 2) : 0,
            ];
        }

        return $results;
    }

    // ── Protected Helpers ──

    protected function calculateMrr(): float
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

    protected function getChannelDefinitions(): array
    {
        return [
            'link' => [
                'name' => '推广链接',
                'icon' => 'Link',
                'color' => '#409eff',
                'description' => '通过推广链接注册的客户',
            ],
            'code' => [
                'name' => '邀请码',
                'icon' => 'Key',
                'color' => '#67c23a',
                'description' => '通过邀请码注册的客户',
            ],
            'direct' => [
                'name' => '直接注册',
                'icon' => 'User',
                'color' => '#909399',
                'description' => '直接访问注册的客户',
            ],
            'api' => [
                'name' => 'API注册',
                'icon' => 'Connection',
                'color' => '#e6a23c',
                'description' => '通过API注册的客户',
            ],
            'organic' => [
                'name' => '自然流量',
                'icon' => 'TrendCharts',
                'color' => '#b37feb',
                'description' => '自然搜索/口碑传播',
            ],
        ];
    }

    protected function calculateChannelStats(string $source): array
    {
        $subscriptionIds = SubscriptionAgent::where('attribution_source', $source)
            ->pluck('subscription_id');

        // 收入
        $revenue = (float) Invoice::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'paid')
            ->sum('amount');

        // 退款
        $refundAmount = (float) Refund::whereIn('invoice_id', function ($q) use ($subscriptionIds) {
            $q->select('id')->from('invoices')->whereIn('subscription_id', $subscriptionIds);
        })->where('status', 'completed')->sum('amount');

        // 佣金支出
        $commission = (float) CommissionSettlement::whereIn('subscription_id', $subscriptionIds)
            ->whereIn('status', ['released', 'pending'])
            ->sum('commission_amount');

        // 净收入
        $netRevenue = round($revenue - $refundAmount - $commission, 2);

        // 代理数量
        $agentCount = SubscriptionAgent::where('attribution_source', $source)
            ->distinct('agent_id')->count('agent_id');

        // 订阅数量
        $subscriptionCount = count($subscriptionIds);
        $activeSubscriptionCount = Subscription::whereIn('id', $subscriptionIds)
            ->whereIn('status', ['active', 'grace'])->count();

        // ROI = (净收入 - 佣金) / 佣金 * 100
        $roi = $commission > 0 ? round(($netRevenue - $commission) / $commission * 100, 2) : ($netRevenue > 0 ? 999.99 : 0);

        // 平均客户价值 (ARPU)
        $customerCount = Invoice::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'paid')
            ->distinct('customer_id')->count('customer_id');
        $arpu = $customerCount > 0 ? round($revenue / $customerCount, 2) : 0;

        return [
            'revenue' => $revenue,
            'refunds' => $refundAmount,
            'commission' => $commission,
            'net_revenue' => $netRevenue,
            'roi' => $roi,
            'agent_count' => $agentCount,
            'subscription_count' => $subscriptionCount,
            'active_subscription_count' => $activeSubscriptionCount,
            'arpu' => $arpu,
        ];
    }

    protected function calculateOverallStats(array $channelStats): array
    {
        $totalRevenue = array_sum(array_column($channelStats, 'revenue'));
        $totalRefunds = array_sum(array_column($channelStats, 'refunds'));
        $totalCommission = array_sum(array_column($channelStats, 'commission'));
        $totalNetRevenue = round($totalRevenue - $totalRefunds - $totalCommission, 2);
        $totalAgentCount = array_sum(array_column($channelStats, 'agent_count'));
        $totalSubscriptionCount = array_sum(array_column($channelStats, 'subscription_count'));

        $overallRoi = $totalCommission > 0
            ? round(($totalNetRevenue - $totalCommission) / $totalCommission * 100, 2)
            : 0;

        return [
            'revenue' => $totalRevenue,
            'refunds' => $totalRefunds,
            'commission' => $totalCommission,
            'net_revenue' => $totalNetRevenue,
            'roi' => $overallRoi,
            'agent_count' => $totalAgentCount,
            'subscription_count' => $totalSubscriptionCount,
        ];
    }

    // ─── M3-73 补充功能 ───

    /**
     * 代理商收益排行榜
     */
    public function agentLeaderboard(int $limit = 20): array
    {
        return Agent::with('user')
            ->where('status', 'active')
            ->orderBy('total_earned', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($a, $i) => [
                'rank' => $i + 1,
                'agent_id' => $a->id,
                'agent_code' => $a->agent_code,
                'name' => $a->contact_name ?: $a->user?->name ?? 'N/A',
                'level' => $a->level,
                'level_label' => match ($a->level) {
                    'regular' => '普通',
                    'silver' => '银牌',
                    'gold' => '金牌',
                    'platinum' => '铂金',
                    default => $a->level,
                },
                'total_earned' => (float) $a->total_earned,
                'total_withdrawn' => (float) $a->total_withdrawn,
                'available_balance' => round((float) $a->total_earned - (float) $a->total_withdrawn, 2),
                'downline_count' => (int) $a->downline_count,
                'revenue_share' => $a->commission_rate,
            ])
            ->toArray();
    }

    /**
     * 月度结算报表
     */
    public function monthlySettlementReport(string $yearMonth): array
    {
        // 收入
        $revenue = (float) Invoice::where('status', 'paid')
            ->whereRaw($this->dateFormatSql('paid_at', '%Y-%m') . " = ?", [$yearMonth])
            ->sum('amount');

        // 退款
        $refunds = (float) Refund::where('status', 'completed')
            ->whereRaw($this->dateFormatSql('created_at', '%Y-%m') . " = ?", [$yearMonth])
            ->sum('amount');

        // 佣金支出
        $commissions = (float) CommissionSettlement::where('period', $yearMonth)
            ->sum('commission_amount');

        // 已提现
        $payouts = (float) CommissionPayout::where('status', 'completed')
            ->whereRaw($this->dateFormatSql('processed_at', '%Y-%m') . " = ?", [$yearMonth])
            ->sum('net_amount');

        // 按渠道拆分
        $channelBreakdown = $this->channelRoi();

        // 新增订阅
        $newSubscriptions = Subscription::whereRaw($this->dateFormatSql('created_at', '%Y-%m') . " = ?", [$yearMonth])->count();

        // 活跃订阅
        $activeSubscriptions = Subscription::whereIn('status', ['active', 'grace'])->count();

        // 月环比增长率
        $prevMonth = date('Y-m', strtotime($yearMonth . '-01 -1 month'));
        $prevRevenue = (float) Invoice::where('status', 'paid')
            ->whereRaw($this->dateFormatSql('paid_at', '%Y-%m') . " = ?", [$prevMonth])
            ->sum('amount');
        $growthRate = $prevRevenue > 0 ? round(($revenue - $prevRevenue) / $prevRevenue * 100, 2) : 0;

        return [
            'year_month' => $yearMonth,
            'revenue' => $revenue,
            'refunds' => $refunds,
            'commissions' => $commissions,
            'payouts' => $payouts,
            'net_revenue' => round($revenue - $refunds - $commissions, 2),
            'growth_rate' => $growthRate,
            'new_subscriptions' => $newSubscriptions,
            'active_subscriptions' => $activeSubscriptions,
            'channel_breakdown' => $channelBreakdown['channels'] ?? [],
        ];
    }
}
