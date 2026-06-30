<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PreSaleOrder;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 预售/众筹支付与退款（M3-87）
 *
 * 对接 PaymentManager / 预付余额，通过 Invoice 记录交易链路。
 */
class PreSalePaymentService
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected PrepaidBalanceService $prepaidBalanceService,
    ) {}

    /**
     * 收取定金
     */
    public function chargeDeposit(PreSaleOrder $order, ?string $paymentMethod = null): array
    {
        $amount = $this->calculateDepositAmount($order);
        if ($amount <= 0) {
            throw new \RuntimeException('定金金额无效');
        }

        return $this->charge($order, 'deposit', $amount, $paymentMethod);
    }

    /**
     * 收取尾款
     */
    public function chargeFinal(PreSaleOrder $order, ?string $paymentMethod = null): array
    {
        $amount = max(0, (float) $order->total_amount - (float) $order->deposit_paid);
        if ($amount <= 0) {
            throw new \RuntimeException('尾款金额无效');
        }

        return $this->charge($order, 'final', $amount, $paymentMethod);
    }

    /**
     * 退款（取消活动 / 众筹失败）
     */
    public function refundOrder(PreSaleOrder $order, string $reason = '活动取消或众筹未达标'): array
    {
        if ($order->payment_status === 'refunded') {
            return ['success' => true, 'amount' => 0, 'message' => '已退款'];
        }

        $refundAmount = (float) $order->deposit_paid + (float) $order->final_paid;
        if ($refundAmount <= 0) {
            $order->update(['payment_status' => 'refunded']);
            return ['success' => true, 'amount' => 0, 'message' => '无需退款'];
        }

        return DB::transaction(function () use ($order, $reason, $refundAmount) {
            $meta = $order->payment_meta ?? [];
            $refundRecords = [];
            $remaining = $refundAmount;

            foreach (['deposit', 'final'] as $phase) {
                $invoiceId = $meta["{$phase}_invoice_id"] ?? null;
                if (! $invoiceId) {
                    continue;
                }

                $invoice = Invoice::find($invoiceId);
                if (! $invoice || $invoice->status !== 'paid') {
                    continue;
                }

                $phaseAmount = $phase === 'deposit'
                    ? (float) $order->deposit_paid
                    : (float) $order->final_paid;

                if ($phaseAmount <= 0) {
                    continue;
                }

                $method = $meta["{$phase}_payment_method"] ?? $order->payment_method ?? 'gateway';
                $refundResult = $this->processRefundForInvoice($invoice, $phaseAmount, $method, $order, $reason);

                if (! ($refundResult['success'] ?? false)) {
                    throw new \RuntimeException($refundResult['error'] ?? '退款失败');
                }

                $refundRecords[] = $refundResult;
                $remaining -= $phaseAmount;
            }

            // 无发票记录时，尝试退到余额或走网关占位
            if (empty($refundRecords) && $remaining > 0) {
                $customer = $this->resolveCustomer($order);
                if ($customer) {
                    $this->prepaidBalanceService->refund(
                        $customer,
                        $remaining,
                        $order->currency,
                        null,
                        "预售订单 {$order->order_no} 退款: {$reason}"
                    );
                    $refundRecords[] = ['success' => true, 'method' => 'prepaid', 'amount' => $remaining];
                }
            }

            $order->update([
                'payment_status' => 'refunded',
                'payment_meta' => array_merge($meta, [
                    'refunds' => array_merge($meta['refunds'] ?? [], $refundRecords),
                    'refund_reason' => $reason,
                    'refunded_at' => now()->toIso8601String(),
                ]),
            ]);

            $order->campaign?->decrement('raised_amount', $refundAmount);

            Log::info('PreSale: order refunded', [
                'order_id' => $order->id,
                'amount' => $refundAmount,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'amount' => $refundAmount,
                'records' => $refundRecords,
            ];
        });
    }

    protected function charge(PreSaleOrder $order, string $phase, float $amount, ?string $paymentMethod): array
    {
        $customer = $this->resolveCustomer($order);
        $method = $paymentMethod ?: ($customer?->billing_method === 'prepaid' ? 'prepaid' : 'gateway');

        $invoice = $this->createInvoice($order, $phase, $amount);
        $paymentResult = $this->processPayment($invoice, $customer, $method, $order, $phase);

        if (! ($paymentResult['success'] ?? false)) {
            $invoice->update(['status' => 'failed']);
            throw new \RuntimeException($paymentResult['error'] ?? '支付失败');
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentResult['method'] ?? $method,
            'metadata' => array_merge($invoice->metadata ?? [], [
                'transaction_id' => $paymentResult['transaction_id'] ?? null,
            ]),
        ]);

        $meta = array_merge($order->payment_meta ?? [], [
            "{$phase}_invoice_id" => $invoice->id,
            "{$phase}_transaction_id" => $paymentResult['transaction_id'] ?? null,
            "{$phase}_payment_method" => $paymentResult['method'] ?? $method,
        ]);

        $order->update([
            'payment_method' => $paymentResult['method'] ?? $method,
            'payment_meta' => $meta,
        ]);

        return array_merge($paymentResult, [
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'phase' => $phase,
        ]);
    }

    protected function processPayment(
        Invoice $invoice,
        ?Customer $customer,
        string $method,
        PreSaleOrder $order,
        string $phase,
    ): array {
        if ($method === 'prepaid' && $customer) {
            $result = $this->prepaidBalanceService->consume(
                $customer,
                (float) $invoice->amount,
                $invoice->currency,
                $invoice,
                "预售订单 {$order->order_no} {$phase} 支付"
            );

            if ($result['success']) {
                return [
                    'success' => true,
                    'method' => 'prepaid',
                    'transaction_id' => 'prepaid_' . ($result['transaction']->id ?? $invoice->id),
                ];
            }

            // 余额不足时回退网关
            Log::info('PreSale: prepaid insufficient, fallback to gateway', [
                'order_id' => $order->id,
                'error' => $result['error'] ?? '',
            ]);
        }

        $gatewayResult = $this->paymentManager->charge($invoice);

        if ($gatewayResult['success'] ?? false) {
            return array_merge($gatewayResult, ['method' => $this->paymentManager->gatewayName()]);
        }

        return $gatewayResult;
    }

    protected function processRefundForInvoice(
        Invoice $invoice,
        float $amount,
        string $method,
        PreSaleOrder $order,
        string $reason,
    ): array {
        $customer = $this->resolveCustomer($order);

        if ($method === 'prepaid' && $customer) {
            $txn = $this->prepaidBalanceService->refund(
                $customer,
                $amount,
                $invoice->currency,
                $invoice,
                "预售订单 {$order->order_no} 退款: {$reason}"
            );

            $this->createRefundRecord($order, $invoice, $amount, 'prepaid', $txn->id ?? null, $reason);

            return ['success' => true, 'method' => 'prepaid', 'amount' => $amount, 'refund_id' => $txn->id ?? null];
        }

        $gatewayResult = $this->paymentManager->refund($invoice, ['amount' => $amount]);

        if ($gatewayResult['success'] ?? false) {
            $invoice->update(['refunded_at' => now()]);
            $this->createRefundRecord(
                $order,
                $invoice,
                $amount,
                $this->paymentManager->gatewayName(),
                $gatewayResult['refund_id'] ?? null,
                $reason
            );

            return array_merge($gatewayResult, ['method' => 'gateway', 'amount' => $amount]);
        }

        return $gatewayResult;
    }

    protected function createRefundRecord(
        PreSaleOrder $order,
        Invoice $invoice,
        float $amount,
        string $method,
        ?string $paymentRefundId,
        string $reason,
    ): Refund {
        return Refund::create([
            'tenant_id' => $order->tenant_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $order->customer_id,
            'refund_no' => 'RF-PS-' . Str::upper(Str::random(12)),
            'amount' => $amount,
            'currency' => $order->currency,
            'reason' => $reason,
            'status' => 'completed',
            'refund_type' => 'full',
            'payment_method' => $method,
            'payment_refund_id' => $paymentRefundId,
            'metadata' => [
                'pre_sale_order_id' => $order->id,
                'order_no' => $order->order_no,
            ],
            'completed_at' => now(),
        ]);
    }

    protected function createInvoice(PreSaleOrder $order, string $phase, float $amount): Invoice
    {
        $suffix = $phase === 'deposit' ? 'D' : 'F';

        return Invoice::create([
            'tenant_id' => $order->tenant_id,
            'customer_id' => $order->customer_id,
            'invoice_no' => 'INV-PS-' . $order->order_no . '-' . $suffix,
            'amount' => $amount,
            'subtotal' => $amount,
            'currency' => $order->currency,
            'status' => 'pending',
            'billing_reason' => 'pre_sale_' . $phase,
            'due_at' => now()->addDays(1),
            'metadata' => [
                'pre_sale_order_id' => $order->id,
                'order_no' => $order->order_no,
                'campaign_id' => $order->campaign_id,
                'phase' => $phase,
            ],
        ]);
    }

    public function calculateDepositAmount(PreSaleOrder $order): float
    {
        $campaign = $order->campaign;

        if ($campaign->deposit_amount > 0) {
            return (float) $campaign->deposit_amount * $order->quantity;
        }

        return round((float) $order->total_amount * ((float) $campaign->deposit_rate / 100), 2);
    }

    protected function resolveCustomer(PreSaleOrder $order): ?Customer
    {
        if ($order->customer_id) {
            return Customer::find($order->customer_id);
        }

        return Customer::where('user_id', $order->user_id)->first();
    }
}
