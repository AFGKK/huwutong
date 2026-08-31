<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * 发票支付完成后，联动电商订单等业务对象（D-03）
 */
class InvoicePaymentSettlementService
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * 发票标记已支付后，尝试完成关联订单
     */
    public function settle(Invoice $invoice, array $paymentInfo): void
    {
        $order = $this->resolveOrder($invoice);
        if (! $order) {
            return;
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return;
        }

        try {
            $this->orderService->markPaid(
                $order,
                $paymentInfo['payment_method'] ?? 'gateway',
                $paymentInfo['transaction_id'] ?? $paymentInfo['charge_id'] ?? '',
                ['invoice_id' => $invoice->id],
            );
        } catch (\Throwable $e) {
            Log::error('InvoicePaymentSettlement: order markPaid failed', [
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveOrder(Invoice $invoice): ?Order
    {
        $metadata = $invoice->metadata ?? [];
        $orderId = $metadata['order_id'] ?? null;

        if ($orderId) {
            return Order::find($orderId);
        }

        if (str_starts_with($invoice->invoice_no, 'INV-')) {
            $orderNo = substr($invoice->invoice_no, 4);

            return Order::where('order_no', $orderNo)->first();
        }

        return null;
    }
}
