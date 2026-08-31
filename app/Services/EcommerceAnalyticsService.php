<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 电商数据分析报表
 *
 * 覆盖：
 * 1. 商品销售趋势（按天/月）
 * 2. 客户复购率
 * 3. 客单价分析
 * 4. 支付渠道偏好
 * 5. 热销商品排行榜
 * 6. 销售预测（基于历史趋势）
 * 7. 同比环比
 * 8. CSV/PDF 导出
 */
class EcommerceAnalyticsService
{
    /**
     * 聚合看板
     */
    public function getDashboard(int $tenantId, string $period = '30d'): array
    {
        $days = $this->periodToDays($period);

        return [
            'summary' => $this->getSummary($tenantId, $days),
            'sales_trend' => $this->getSalesTrend($tenantId, $days),
            'product_ranking' => $this->getProductSalesRanking($tenantId, $period),
            'repurchase_rate' => $this->getRepurchaseRate($tenantId, $days),
            'payment_channels' => $this->getPaymentChannelBreakdown($tenantId, $days),
            'customer_metrics' => $this->getCustomerMetrics($tenantId, $days),
            'comparison' => $this->getPeriodComparison($tenantId, $days),
        ];
    }

    /**
     * 概要统计
     */
    public function getSummary(int $tenantId, int $days = 30): array
    {
        $periodStart = Carbon::now()->subDays($days);
        $prevPeriodStart = Carbon::now()->subDays($days * 2);

        // 当前周期
        $current = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $periodStart);

        $currentRevenue = (clone $current)->sum('amount');
        $currentOrders = (clone $current)->count();
        $currentCustomers = (clone $current)->distinct('customer_id')->count('customer_id');

        // 上一周期
        $previous = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $prevPeriodStart)
            ->where('paid_at', '<', $periodStart);

        $prevRevenue = (clone $previous)->sum('amount');
        $prevOrders = (clone $previous)->count();

        $revenueGrowth = $prevRevenue > 0
            ? round((($currentRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;
        $orderGrowth = $prevOrders > 0
            ? round((($currentOrders - $prevOrders) / $prevOrders) * 100, 1) : 0;

        // 平均客单价
        $avgOrderValue = $currentOrders > 0
            ? round($currentRevenue / $currentOrders, 2) : 0;

        // 总客户数
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();

        return [
            'period_days' => $days,
            'total_revenue' => round($currentRevenue, 2),
            'total_orders' => $currentOrders,
            'total_customers' => $totalCustomers,
            'new_customers' => $currentCustomers,
            'avg_order_value' => $avgOrderValue,
            'revenue_growth' => $revenueGrowth,
            'order_growth' => $orderGrowth,
        ];
    }

    /**
     * 销售趋势（按天）
     */
    public function getSalesTrend(int $tenantId, int $days = 30): array
    {
        $periodStart = Carbon::now()->subDays($days);

        $daily = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $periodStart)
            ->selectRaw("DATE(paid_at) as date")
            ->selectRaw("COUNT(*) as order_count")
            ->selectRaw("COALESCE(SUM(amount), 0) as revenue")
            ->selectRaw("COUNT(DISTINCT customer_id) as customer_count")
            ->groupBy(DB::raw("DATE(paid_at)"))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $cursor = $periodStart->copy();
        while ($cursor <= Carbon::now()) {
            $dateStr = $cursor->format('Y-m-d');
            $dayData = $daily->get($dateStr);
            $result[] = [
                'date' => $dateStr,
                'order_count' => (int) ($dayData->order_count ?? 0),
                'revenue' => round((float) ($dayData->revenue ?? 0), 2),
                'customer_count' => (int) ($dayData->customer_count ?? 0),
            ];
            $cursor->addDay();
        }

        return $result;
    }

    /**
     * 热销商品排行榜
     */
    public function getProductSalesRanking(int $tenantId, string $period = '30d'): array
    {
        $days = $this->periodToDays($period);
        $periodStart = Carbon::now()->subDays($days);

        $lineItems = InvoiceLineItem::whereHas('invoice', function ($q) use ($tenantId, $periodStart) {
            $q->where('tenant_id', $tenantId)
              ->where('status', 'paid')
              ->where('paid_at', '>=', $periodStart);
        })
            ->selectRaw('type, description, metric_key')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(amount) as total_revenue')
            ->selectRaw('COUNT(DISTINCT invoice_id) as order_count')
            ->groupBy('type', 'description', 'metric_key')
            ->orderByDesc('total_revenue')
            ->limit(50)
            ->get();

        return $lineItems->map(fn($item) => [
            'type' => $item->type,
            'description' => $item->description,
            'metric_key' => $item->metric_key,
            'total_quantity' => (int) $item->total_quantity,
            'total_revenue' => round((float) $item->total_revenue, 2),
            'order_count' => (int) $item->order_count,
        ])->toArray();
    }

    /**
     * 客户复购率
     */
    public function getRepurchaseRate(int $tenantId, int $days = 90): array
    {
        $periodStart = Carbon::now()->subDays($days);

        // 统计周期内有购买记录的客户及其订单数
        $customerOrders = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $periodStart)
            ->selectRaw('customer_id')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('SUM(amount) as total_spent')
            ->groupBy('customer_id')
            ->get();

        $totalBuyers = $customerOrders->count();
        $repurchaseBuyers = $customerOrders->where('order_count', '>=', 2)->count();
        $multiOrderBuyers = $customerOrders->where('order_count', '>=', 3)->count();

        // 复购次数分布
        $orderDistribution = [
            '1次' => $customerOrders->where('order_count', 1)->count(),
            '2次' => $customerOrders->where('order_count', 2)->count(),
            '3-5次' => $customerOrders->whereBetween('order_count', [3, 5])->count(),
            '6-10次' => $customerOrders->whereBetween('order_count', [6, 10])->count(),
            '10+次' => $customerOrders->where('order_count', '>', 10)->count(),
        ];

        return [
            'total_buyers' => $totalBuyers,
            'repurchase_rate' => $totalBuyers > 0
                ? round(($repurchaseBuyers / $totalBuyers) * 100, 1) : 0,
            'multi_purchase_rate' => $totalBuyers > 0
                ? round(($multiOrderBuyers / $totalBuyers) * 100, 1) : 0,
            'avg_orders_per_buyer' => $totalBuyers > 0
                ? round($customerOrders->sum('order_count') / $totalBuyers, 2) : 0,
            'avg_spent_per_buyer' => $totalBuyers > 0
                ? round($customerOrders->sum('total_spent') / $totalBuyers, 2) : 0,
            'order_distribution' => $orderDistribution,
        ];
    }

    /**
     * 支付渠道分布
     */
    public function getPaymentChannelBreakdown(int $tenantId, int $days = 30): array
    {
        $periodStart = Carbon::now()->subDays($days);

        $channels = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $periodStart)
            ->selectRaw("COALESCE(NULLIF(payment_method, ''), 'unknown') as channel")
            ->selectRaw("COUNT(*) as order_count")
            ->selectRaw("COALESCE(SUM(amount), 0) as total_amount")
            ->groupBy('channel')
            ->orderByDesc('total_amount')
            ->get();

        $totalAmount = $channels->sum('total_amount');

        return $channels->map(fn($c) => [
            'channel' => $c->channel,
            'order_count' => (int) $c->order_count,
            'total_amount' => round((float) $c->total_amount, 2),
            'percentage' => $totalAmount > 0
                ? round(($c->total_amount / $totalAmount) * 100, 1) : 0,
        ])->toArray();
    }

    /**
     * 客户指标
     */
    public function getCustomerMetrics(int $tenantId, int $days = 30): array
    {
        $periodStart = Carbon::now()->subDays($days);

        // 新客户数（首次购买的客户）
        $newCustomerIds = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $periodStart)
            ->whereNotIn('customer_id', function ($q) use ($tenantId, $periodStart) {
                $q->select('customer_id')->from('invoices')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'paid')
                    ->where('paid_at', '<', $periodStart);
            })
            ->distinct('customer_id')
            ->count('customer_id');

        // 活跃客户数
        $activeCustomers = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $periodStart)
            ->distinct('customer_id')
            ->count('customer_id');

        // 客户复购间隔（平均天数）
        $avgInterval = 0;
        $customersWithOrders = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', Carbon::now()->subDays($days * 3))
            ->selectRaw('customer_id, DATE(paid_at) as paid_date')
            ->orderBy('customer_id')
            ->orderBy('paid_at')
            ->get()
            ->groupBy('customer_id');

        $intervals = [];
        foreach ($customersWithOrders as $orders) {
            $prev = null;
            foreach ($orders as $order) {
                if ($prev !== null) {
                    $intervals[] = Carbon::parse($order->paid_date)->diffInDays(Carbon::parse($prev));
                }
                $prev = $order->paid_date;
            }
        }
        $avgInterval = count($intervals) > 0
            ? round(array_sum($intervals) / count($intervals), 1) : 0;

        return [
            'new_customers' => $newCustomerIds,
            'active_customers' => $activeCustomers,
            'avg_purchase_interval_days' => round((float) ($repurchaseIntervals[0]->avg_interval ?? 0), 1),
            'total_customers' => Customer::where('tenant_id', $tenantId)->count(),
        ];
    }

    /**
     * 周期对比（同比/环比）
     */
    public function getPeriodComparison(int $tenantId, int $days = 30): array
    {
        $currentStart = Carbon::now()->subDays($days);
        $prevStart = Carbon::now()->subDays($days * 2);
        $prevEnd = $currentStart->copy();

        $yoYStart = Carbon::now()->subDays($days * 13);
        $yoYEnd = Carbon::now()->subDays($days * 12);

        // 当前 vs 上一周期（环比）
        $current = $this->periodStats($tenantId, $currentStart, Carbon::now());
        $previous = $this->periodStats($tenantId, $prevStart, $prevEnd);

        // 同比（去年同一时段）
        $yearAgo = $this->periodStats($tenantId, $yoYStart, $yoYEnd);

        $chainGrowth = $previous['revenue'] > 0
            ? round((($current['revenue'] - $previous['revenue']) / $previous['revenue']) * 100, 1) : 0;
        $yoyGrowth = $yearAgo['revenue'] > 0
            ? round((($current['revenue'] - $yearAgo['revenue']) / $yearAgo['revenue']) * 100, 1) : 0;

        return [
            'current' => $current,
            'previous_period' => $previous,
            'year_ago' => $yearAgo,
            'chain_growth' => $chainGrowth,
            'yoy_growth' => $yoyGrowth,
        ];
    }

    /**
     * 销售预测（基于线性回归的简单趋势预测）
     */
    public function getSalesForecast(int $tenantId, int $days = 90, int $forecastDays = 30): array
    {
        $trend = $this->getSalesTrend($tenantId, $days);
        $n = count($trend);
        if ($n < 7) {
            return ['forecast' => [], 'confidence' => 'low'];
        }

        // 简单线性回归：y = ax + b
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($trend as $i => $day) {
            $x = $i;
            $y = $day['revenue'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $a = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $b = ($sumY - $a * $sumX) / $n;

        // 生成预测
        $forecast = [];
        $lastTrend = $trend[$n - 1];
        $lastDate = Carbon::parse($lastTrend['date']);

        for ($i = 1; $i <= $forecastDays; $i++) {
            $x = $n + $i - 1;
            $predicted = max(0, $a * $x + $b);
            $forecast[] = [
                'date' => $lastDate->copy()->addDays($i)->format('Y-m-d'),
                'predicted_revenue' => round($predicted, 2),
            ];
        }

        // 相关性（R²）
        $meanY = $sumY / $n;
        $ssRes = 0;
        $ssTot = 0;
        foreach ($trend as $i => $day) {
            $pred = $a * $i + $b;
            $ssRes += ($day['revenue'] - $pred) ** 2;
            $ssTot += ($day['revenue'] - $meanY) ** 2;
        }
        $rSquared = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;

        return [
            'forecast' => $forecast,
            'trend_direction' => $a >= 0 ? 'up' : 'down',
            'daily_trend_rate' => round($a, 2),
            'r_squared' => round($rSquared, 4),
            'confidence' => $rSquared > 0.7 ? 'high' : ($rSquared > 0.3 ? 'medium' : 'low'),
            'total_predicted_revenue' => round(array_sum(array_column($forecast, 'predicted_revenue')), 2),
        ];
    }

    /**
     * 导出报表（CSV格式）
     */
    public function exportCsv(int $tenantId, string $type = 'sales_trend', int $days = 30): string
    {
        return match ($type) {
            'sales_trend' => $this->exportSalesTrend($tenantId, $days),
            'product_ranking' => $this->exportProductRanking($tenantId, $days),
            'payment_channels' => $this->exportPaymentChannels($tenantId, $days),
            default => throw new \InvalidArgumentException(__("app.ecommerce_analytics.msg_73d103cd")),
        };
    }

    protected function exportSalesTrend(int $tenantId, int $days): string
    {
        $trend = $this->getSalesTrend($tenantId, $days);
        $csv = "日期,订单数,收入(元),客户数\n";
        foreach ($trend as $day) {
            $csv .= "{$day['date']},{$day['order_count']},{$day['revenue']},{$day['customer_count']}\n";
        }
        return $csv;
    }

    protected function exportProductRanking(int $tenantId, int $days): string
    {
        $period = $days <= 7 ? '7d' : ($days <= 30 ? '30d' : '90d');
        $ranking = $this->getProductSalesRanking($tenantId, $period);
        $csv = "类型,描述,销量(件),收入(元),订单数\n";
        foreach ($ranking as $item) {
            $csv .= "{$item['type']},{$item['description']},{$item['total_quantity']},{$item['total_revenue']},{$item['order_count']}\n";
        }
        return $csv;
    }

    protected function exportPaymentChannels(int $tenantId, int $days): string
    {
        $channels = $this->getPaymentChannelBreakdown($tenantId, $days);
        $csv = "支付渠道,订单数,金额(元),占比(%)\n";
        foreach ($channels as $c) {
            $csv .= "{$c['channel']},{$c['order_count']},{$c['total_amount']},{$c['percentage']}\n";
        }
        return $csv;
    }

    /**
     * 辅助方法
     */
    protected function periodToDays(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30,
        };
    }

    protected function periodStats(int $tenantId, Carbon $start, Carbon $end): array
    {
        $stats = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $start)
            ->where('paid_at', '<=', $end);

        return [
            'revenue' => round((float) (clone $stats)->sum('amount'), 2),
            'orders' => (clone $stats)->count(),
            'customers' => (clone $stats)->distinct('customer_id')->count('customer_id'),
            'avg_order' => round((float) (clone $stats)->avg('amount') ?? 0, 2),
        ];
    }
}
