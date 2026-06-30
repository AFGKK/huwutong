<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * 电商数据看板 (M2-154 🛒)
 *
 * 轻量级今日看板：
 * - 今日销售额/订单量
 * - 商品销量排行
 * - 支付成功率
 * - 退款率
 * - 趋势图表
 */
class EcommerceDashboardService
{
    /**
     * 获取看板全部数据
     */
    public function getDashboard(int $tenantId): array
    {
        return [
            'today' => $this->getTodayStats($tenantId),
            'product_ranking' => $this->getProductSalesRanking($tenantId),
            'payment_success_rate' => $this->getPaymentSuccessRate($tenantId),
            'refund_rate' => $this->getRefundRate($tenantId),
            'trend' => $this->getTrend($tenantId, 7),
        ];
    }

    /**
     * 今日统计
     */
    public function getTodayStats(int $tenantId): array
    {
        $base = Order::where('tenant_id', $tenantId);

        // 今日数据
        $today = clone $base;
        $todayRevenue = (clone $today)->where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', today())->sum('final_amount');
        $todayOrders = (clone $today)->whereDate('created_at', today())->count();
        $todayPaid = (clone $today)->where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', today())->count();
        $todayPending = (clone $today)->where('status', Order::STATUS_PENDING)
            ->whereDate('created_at', today())->count();
        $todayCancelled = (clone $today)->where('status', Order::STATUS_CANCELLED)
            ->whereDate('created_at', today())->count();

        // 昨日对比
        $yesterdayRevenue = (clone $base)->where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', today()->subDay())->sum('final_amount');
        $yesterdayOrders = (clone $base)->whereDate('created_at', today()->subDay())->count();

        // 本月
        $monthRevenue = (clone $base)->where('status', Order::STATUS_PAID)
            ->whereMonth('paid_at', now()->month)->sum('final_amount');
        $monthOrders = (clone $base)->whereDate('created_at', '>=', now()->startOfMonth())->count();

        // 累计
        $totalRevenue = (clone $base)->where('status', Order::STATUS_PAID)->sum('final_amount');
        $totalOrders = (clone $base)->count();

        // 环比
        $revenueGrowth = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) : 0;
        $orderGrowth = $yesterdayOrders > 0
            ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1) : 0;

        return [
            'today_revenue' => $todayRevenue,
            'today_orders' => $todayOrders,
            'today_paid' => $todayPaid,
            'today_pending' => $todayPending,
            'today_cancelled' => $todayCancelled,
            'yesterday_revenue' => $yesterdayRevenue,
            'yesterday_orders' => $yesterdayOrders,
            'month_revenue' => $monthRevenue,
            'month_orders' => $monthOrders,
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'revenue_growth' => $revenueGrowth,
            'order_growth' => $orderGrowth,
        ];
    }

    /**
     * 商品销量排行
     */
    public function getProductSalesRanking(int $tenantId, int $limit = 10): array
    {
        return OrderItem::selectRaw(
                'order_items.sku_id, product_skus.name as sku_name, product_skus.sku_code,
                 SUM(order_items.quantity) as total_qty,
                 SUM(order_items.subtotal) as total_revenue,
                 COUNT(DISTINCT orders.id) as order_count'
            )
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('product_skus', 'order_items.sku_id', '=', 'product_skus.id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.status', Order::STATUS_PAID)
            ->groupBy('order_items.sku_id', 'product_skus.name', 'product_skus.sku_code')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 支付成功率
     */
    public function getPaymentSuccessRate(int $tenantId): array
    {
        $total = Order::where('tenant_id', $tenantId)->count();
        $paid = Order::where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_PAID)->count();
        $cancelled = Order::where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_CANCELLED)->count();
        $refunded = Order::where('tenant_id', $tenantId)
            ->whereIn('status', [Order::STATUS_REFUNDED, Order::STATUS_PARTIAL_REFUND])->count();

        return [
            'total' => $total,
            'paid' => $paid,
            'cancelled' => $cancelled,
            'refunded' => $refunded,
            'pending' => $total - $paid - $cancelled - $refunded,
            'success_rate' => $total > 0 ? round(($paid / $total) * 100, 1) : 0,
        ];
    }

    /**
     * 退款率统计
     */
    public function getRefundRate(int $tenantId): array
    {
        $totalPaid = Order::where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_PAID)->count();
        $refunded = Order::where('tenant_id', $tenantId)
            ->whereIn('status', [Order::STATUS_REFUNDED, Order::STATUS_PARTIAL_REFUND])->count();
        $totalRevenue = Order::where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_PAID)->sum('final_amount');
        $refundedAmount = Order::where('tenant_id', $tenantId)
            ->whereIn('status', [Order::STATUS_REFUNDED, Order::STATUS_PARTIAL_REFUND])
            ->sum('final_amount');

        return [
            'total_paid_orders' => $totalPaid,
            'refunded_orders' => $refunded,
            'refund_rate' => $totalPaid > 0 ? round(($refunded / $totalPaid) * 100, 2) : 0,
            'total_revenue' => $totalRevenue,
            'refunded_amount' => $refundedAmount,
            'refund_amount_rate' => $totalRevenue > 0 ? round(($refundedAmount / $totalRevenue) * 100, 2) : 0,
        ];
    }

    /**
     * 趋势数据（近N天）
     */
    public function getTrend(int $tenantId, int $days = 7): array
    {
        $results = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i);
            // 使用别名避免冲突
            $dayOrders = Order::where('tenant_id', $tenantId)
                ->whereDate('created_at', $date);
            $dayRevenue = (clone $dayOrders)->where('status', Order::STATUS_PAID)
                ->whereDate('paid_at', $date)->sum('final_amount');
            $dayCount = (clone $dayOrders)->count();
            $dayPaid = (clone $dayOrders)->where('status', Order::STATUS_PAID)
                ->whereDate('paid_at', $date)->count();
            $dayCancelled = (clone $dayOrders)->where('status', Order::STATUS_CANCELLED)
                ->whereDate('created_at', $date)->count();

            $results[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('m/d'),
                'revenue' => $dayRevenue,
                'orders' => $dayCount,
                'paid' => $dayPaid,
                'cancelled' => $dayCancelled,
            ];
        }
        return $results;
    }
}
