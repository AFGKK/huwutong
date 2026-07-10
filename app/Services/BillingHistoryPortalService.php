<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * M2-131 发票/账单历史完整查询服务
 *
 * 客户门户的账单历史模块：
 * - 完整账单列表 + 按时间段筛选
 * - 下载 PDF 发票
 * - 已付/待付/退款状态标签
 * - 自动续费扣款记录
 * - 支付失败记录及原因
 */
class BillingHistoryPortalService
{
    /**
     * 获取账单列表（分页）
     *
     * @param Tenant $tenant
     * @param Customer $customer
     * @param array $filters [status, date_from, date_to, subscription_id, payment_method, sort]
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getInvoices(
        Tenant $tenant,
        Customer $customer,
        array $filters = [],
        int $perPage = 20,
    ) {
        $query = Invoice::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->with(['subscription:id,plan,status', 'taxLines']);

        // 状态筛选
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 日期范围
        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // 订阅筛选
        if (! empty($filters['subscription_id'])) {
            $query->where('subscription_id', $filters['subscription_id']);
        }

        // 支付方式筛选
        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // 计费原因筛选
        if (! empty($filters['billing_reason'])) {
            $query->where('billing_reason', $filters['billing_reason']);
        }

        // 排序
        $sortField = $filters['sort'] ?? '-created_at';
        if (str_starts_with($sortField, '-')) {
            $query->orderBy(substr($sortField, 1), 'desc');
        } else {
            $query->orderBy($sortField, 'asc');
        }

        return $query->paginate(min($perPage, 100));
    }

    /**
     * 获取账单详情
     */
    public function getInvoiceDetail(Tenant $tenant, Customer $customer, int $invoiceId): ?Invoice
    {
        return Invoice::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->with([
                'subscription:id,plan,status,price,currency,billing_period,starts_at,ends_at',
                'taxLines',
                'coupon:id,code,type,discount_percent,discount_amount',
            ])
            ->find($invoiceId);
    }

    /**
     * 获取账单统计概览
     */
    public function getStats(Tenant $tenant, Customer $customer): array
    {
        $query = Invoice::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id);

        // 总收入
        $totalRevenue = (clone $query)->where('status', 'paid')->sum('amount');

        // 待付金额
        $pendingAmount = (clone $query)->where('status', 'pending')->sum('amount');

        // 本月收入
        $thisMonthRevenue = (clone $query)
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        // 各状态统计
        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // 最近 12 个月月度收入趋势
        $dbDriver = DB::connection()->getDriverName();
        $monthExpr = db_date_format('created_at', '%Y-%m').' as month';

        $monthlyRevenue = (clone $query)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("{$monthExpr}, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        // 过去 30 天每日收入
        $dailyRevenue = (clone $query)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        // 支付方式使用分布
        $byPaymentMethod = (clone $query)
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->toArray();

        // 计费原因统计
        $byBillingReason = (clone $query)
            ->whereNotNull('billing_reason')
            ->selectRaw('billing_reason, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('billing_reason')
            ->get()
            ->toArray();

        // 退款总额
        $totalRefunded = (clone $query)
            ->where('status', 'refunded')
            ->sum('amount');

        return [
            'total_invoices' => (clone $query)->count(),
            'total_revenue' => round((float) $totalRevenue, 2),
            'pending_amount' => round((float) $pendingAmount, 2),
            'this_month_revenue' => round((float) $thisMonthRevenue, 2),
            'total_refunded' => round((float) $totalRefunded, 2),
            'by_status' => $byStatus,
            'monthly_revenue' => $monthlyRevenue,
            'daily_revenue' => $dailyRevenue,
            'by_payment_method' => $byPaymentMethod,
            'by_billing_reason' => $byBillingReason,
        ];
    }

    /**
     * 获取客户的活跃订阅列表（用于筛选）
     */
    public function getSubscriptions(Tenant $tenant, Customer $customer): array
    {
        return Subscription::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->select(['id', 'plan', 'status', 'price', 'currency', 'billing_period', 'starts_at', 'ends_at'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 获取支付失败记录
     *
     * 查找 status=pending 但已过 due_at 的未支付发票
     */
    public function getFailedPayments(Tenant $tenant, Customer $customer): array
    {
        // 已逾期但未支付的账单
        $overdueInvoices = Invoice::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->where('due_at', '<=', now())
            ->with('subscription:id,plan')
            ->orderBy('due_at', 'desc')
            ->get()
            ->toArray();

        // 最近的自动续费失败记录（查询 billing_reason=renewal 且 status=pending）
        $failedRenewals = Invoice::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('billing_reason', 'renewal')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDays(30))
            ->with('subscription:id,plan')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        // 退款记录
        $refunds = Refund::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->with(['invoice:id,invoice_no,amount', 'processor:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        return [
            'overdue_invoices' => $overdueInvoices,
            'failed_renewals' => $failedRenewals,
            'refunds' => $refunds,
        ];
    }

    /**
     * 获取自动续费扣款记录
     */
    public function getAutoRenewalRecords(Tenant $tenant, Customer $customer): array
    {
        $invoices = Invoice::where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('billing_reason', 'renewal')
            ->with('subscription:id,plan')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return [
            'records' => $invoices->items(),
            'total' => $invoices->total(),
            'success_count' => $invoices->where('status', 'paid')->count(),
            'failed_count' => $invoices->where('status', 'pending')->count(),
            'total_amount' => round((float) $invoices->sum('amount'), 2),
        ];
    }

    /**
     * 获取账单可用的筛选选项
     */
    public function getFilterOptions(): array
    {
        return [
            'statuses' => [
                'pending' => '待支付',
                'paid' => '已支付',
                'refunded' => '已退款',
                'canceled' => '已取消',
            ],
            'billing_reasons' => [
                'subscription_create' => '订阅创建',
                'renewal' => '自动续费',
                'manual_renewal' => '手动续费',
                'plan_change' => '方案变更',
                'upgrade' => '升级',
                'downgrade' => '降级',
            ],
            'payment_methods' => [
                'alipay' => '支付宝',
                'wechat' => '微信支付',
                'stripe' => 'Stripe',
                'bank_transfer' => '银行转账',
                'manual' => '手动',
                'balance' => '余额',
            ],
            'sort_options' => [
                '-created_at' => '时间（新→旧）',
                'created_at' => '时间（旧→新）',
                '-amount' => '金额（高→低）',
                'amount' => '金额（低→高）',
            ],
        ];
    }

    /**
     * 获取状态标签信息（用于前端展示）
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => '已支付',
            'pending' => '待支付',
            'refunded' => '已退款',
            'canceled' => '已取消',
            default => $status,
        };
    }

    public static function getStatusType(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'pending' => 'warning',
            'refunded' => 'danger',
            'canceled' => 'info',
            default => '',
        };
    }

    public static function getBillingReasonLabel(string $reason): string
    {
        return match ($reason) {
            'subscription_create' => '订阅创建',
            'renewal' => '自动续费',
            'manual_renewal' => '手动续费',
            'plan_change' => '方案变更',
            'upgrade' => '升级',
            'downgrade' => '降级',
            default => $reason,
        };
    }
}
