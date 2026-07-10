<?php

namespace App\Services;

use App\Models\License;
use App\Models\Order;
use App\Models\Refund;
use App\Support\DbSql;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 退款售后工作流 (M2-155 🛒)
 *
 * 客户发起退款申请 → 审核 → 原路退款 → 自动吊销License → 售后统计
 */
class RefundWorkflowService
{
    public function __construct(
        protected PaymentSecurityGuard $securityGuard,
    ) {}

    /**
     * 客户发起退款申请
     */
    public function requestRefund(int $customerId, int $orderId, array $data): Refund
    {
        $order = Order::with('items')->findOrFail($orderId);

        if ($order->status !== Order::STATUS_PAID) {
            throw new \RuntimeException('只有已支付的订单可以申请退款');
        }

        // 退款防刷检查
        $abuseCheck = $this->securityGuard->checkRefundAbuse($customerId, $orderId);
        if (!$abuseCheck['passed']) {
            throw new \RuntimeException($abuseCheck['message']);
        }

        return DB::transaction(function () use ($order, $data) {
            $refund = Refund::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'refund_no' => 'RF' . date('Ymd') . Str::upper(Str::random(8)),
                'amount' => $order->final_amount,
                'currency' => $order->currency ?? 'CNY',
                'reason' => $data['reason'] ?? '',
                'customer_notes' => $data['notes'] ?? '',
                'attachments' => $data['attachments'] ?? null,
                'status' => 'pending',
                'refund_type' => $data['refund_type'] ?? 'full',
                'customer_requested_at' => now(),
            ]);

            // 更新订单状态
            $order->update(['status' => Order::STATUS_REFUNDING]);

            return $refund;
        });
    }

    /**
     * 审核退款
     */
    public function review(int $refundId, string $action, array $data = []): Refund
    {
        $refund = Refund::with('order')->findOrFail($refundId);

        if ($refund->status !== 'pending') {
            throw new \RuntimeException("退款已处理，当前状态: {$refund->status}");
        }

        if ($action === 'approve') {
            return $this->approve($refund, $data);
        }

        if ($action === 'reject') {
            return $this->reject($refund, $data);
        }

        throw new \RuntimeException("未知操作: {$action}");
    }

    /**
     * 批准退款
     */
    protected function approve(Refund $refund, array $data): Refund
    {
        DB::transaction(function () use ($refund, $data) {
            $refund->update([
                'status' => 'approved',
                'approved_by' => $data['operator_id'] ?? auth()->id(),
                'approved_at' => now(),
                'metadata' => array_merge($refund->metadata ?? [], [
                    'review_notes' => $data['notes'] ?? '',
                    'approved_by_name' => $data['operator_name'] ?? '',
                ]),
            ]);

            // 执行原路退款（调用支付网关）
            $this->processRefundPayment($refund);

            // 自动吊销关联License
            $this->revokeLicenses($refund);

            // 更新订单
            $order = $refund->order;
            if ($order) {
                $order->update(['status' => Order::STATUS_REFUNDED]);
            }

            $refund->update(['status' => 'completed', 'completed_at' => now()]);
        });

        return $refund->fresh();
    }

    /**
     * 拒绝退款
     */
    protected function reject(Refund $refund, array $data): Refund
    {
        $refund->update([
            'status' => 'rejected',
            'reject_reason' => $data['reason'] ?? '未通过审核',
            'approved_by' => $data['operator_id'] ?? auth()->id(),
            'approved_at' => now(),
            'metadata' => array_merge($refund->metadata ?? [], [
                'reject_notes' => $data['notes'] ?? '',
            ]),
        ]);

        // 恢复订单状态为已支付
        $order = $refund->order;
        if ($order && $order->status === Order::STATUS_REFUNDING) {
            $order->update(['status' => Order::STATUS_PAID]);
        }

        return $refund->fresh();
    }

    /**
     * 执行退款支付
     */
    protected function processRefundPayment(Refund $refund): void
    {
        // 调用 PaymentManager 执行退款
        try {
            $paymentManager = app(PaymentManager::class);
            $invoice = $refund->invoice;
            if ($invoice) {
                $paymentManager->refund($invoice, [
                    'amount' => $refund->amount,
                    'reason' => $refund->reason,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('退款支付失败', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
            // 不阻断流程，标记为需要手动处理
            $refund->update([
                'failure_reason' => '退款支付失败: ' . $e->getMessage(),
                'metadata' => array_merge($refund->metadata ?? [], ['payment_refund_failed' => true]),
            ]);
        }
    }

    /**
     * 吊销关联的License
     */
    protected function revokeLicenses(Refund $refund): void
    {
        $order = $refund->order;
        if (!$order) return;

        foreach ($order->deliveries as $delivery) {
            if ($delivery->status === 'delivered' && $delivery->auto_license_id) {
                $licenseIds = explode(',', $delivery->auto_license_id);
                License::whereIn('id', $licenseIds)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'revoked',
                        'metadata' => DB::raw(DbSql::jsonMerge('metadata', [
                            'revoked_reason' => 'order_refund',
                            'refund_id' => $refund->id,
                            'revoked_at' => now()->toIso8601String(),
                        ])),
                    ]);
            }
        }
    }

    /**
     * 获取退款统计
     */
    public function getStats(int $tenantId): array
    {
        $base = Refund::byTenant($tenantId);
        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'total_amount' => (clone $base)->where('status', 'completed')->sum('amount'),
            'today_requests' => (clone $base)->whereDate('created_at', today())->count(),
            'avg_refund_time_hours' => (clone $base)->whereNotNull('completed_at')
                ->selectRaw('AVG('.DbSql::timestampDiff('HOUR', 'created_at', 'completed_at').') as avg')
                ->value('avg') ?: 0,
        ];
    }

    /**
     * 获取退款列表
     */
    public function getRefunds(int $tenantId, array $filters = []): array
    {
        $query = Refund::byTenant($tenantId)
            ->with(['order:id,order_no', 'customer.user:id,name,email', 'approver:id,name']);

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('refund_no', 'like', "%{$filters['search']}%")
                  ->orWhereHas('order', fn($o) => $o->where('order_no', 'like', "%{$filters['search']}%"));
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        return $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->toArray();
    }
}
