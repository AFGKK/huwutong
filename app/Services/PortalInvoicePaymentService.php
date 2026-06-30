<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * 客户门户发票支付（对接 PaymentWebhook 异步回调）
 */
class PortalInvoicePaymentService
{
    public function __construct(
        protected BillingService $billingService,
        protected PaymentManager $paymentManager,
        protected PrepaidBalanceService $prepaidBalanceService,
    ) {}

    /**
     * 发起发票支付
     */
    public function payInvoice(Customer $customer, Invoice $invoice, ?string $paymentMethod = null): array
    {
        $this->assertPayable($customer, $invoice);

        if ($paymentMethod === 'prepaid') {
            $customer->billing_method = 'prepaid';
        }

        $invoice->update([
            'metadata' => array_merge($invoice->metadata ?? [], [
                'payment_initiated_at' => now()->toIso8601String(),
                'payment_method_requested' => $paymentMethod ?? 'auto',
            ]),
        ]);

        $result = $this->billingService->processPayment($invoice->fresh());

        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException($result['error'] ?? '支付失败');
        }

        $invoice->refresh();

        // 同步支付成功（Mock / 余额）
        if ($invoice->status === 'paid') {
            return [
                'status' => 'paid',
                'invoice_id' => $invoice->id,
                'transaction_id' => $result['transaction_id'] ?? null,
                'method' => $result['method'] ?? $this->paymentManager->gatewayName(),
            ];
        }

        // 异步网关：等待 Webhook 回调
        $invoice->update([
            'metadata' => array_merge($invoice->metadata ?? [], [
                'pending_transaction_id' => $result['transaction_id'] ?? null,
                'pending_gateway' => $this->paymentManager->gatewayName(),
            ]),
        ]);

        Log::info('Portal billing: payment initiated', [
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'gateway' => $this->paymentManager->gatewayName(),
            'transaction_id' => $result['transaction_id'] ?? null,
        ]);

        return [
            'status' => 'pending',
            'invoice_id' => $invoice->id,
            'transaction_id' => $result['transaction_id'] ?? null,
            'redirect_url' => $result['redirect_url'] ?? null,
            'client_secret' => $result['client_secret'] ?? null,
            'gateway' => $this->paymentManager->gatewayName(),
            'message' => '支付已发起，完成后将通过 Webhook 自动确认',
        ];
    }

    /**
     * 查询支付状态（轮询用）
     */
    public function getPaymentStatus(Customer $customer, Invoice $invoice): array
    {
        $this->assertOwned($customer, $invoice);

        $pendingTxn = $invoice->metadata['pending_transaction_id'] ?? null;

        if ($invoice->status === 'pending' && $pendingTxn) {
            $query = $this->paymentManager->query($pendingTxn);
            if (($query['status'] ?? '') === 'paid' || ($query['status'] ?? '') === 'succeeded') {
                $this->billingService->markInvoicePaid($invoice, [
                    'transaction_id' => $pendingTxn,
                    'payment_method' => $invoice->metadata['pending_gateway'] ?? $this->paymentManager->gatewayName(),
                ]);
                $invoice->refresh();
            }
        }

        return [
            'invoice_id' => $invoice->id,
            'status' => $invoice->status,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'transaction_id' => $invoice->metadata['charge_id']
                ?? $invoice->metadata['pending_transaction_id']
                ?? null,
        ];
    }

    protected function assertPayable(Customer $customer, Invoice $invoice): void
    {
        $this->assertOwned($customer, $invoice);

        if ($invoice->status !== 'pending') {
            throw new \RuntimeException('该发票当前不可支付');
        }

        if ((float) $invoice->amount <= 0) {
            throw new \RuntimeException('发票金额无效');
        }
    }

    protected function assertOwned(Customer $customer, Invoice $invoice): void
    {
        if ((int) $invoice->customer_id !== (int) $customer->id) {
            throw new \RuntimeException('无权操作该发票');
        }
    }
}
