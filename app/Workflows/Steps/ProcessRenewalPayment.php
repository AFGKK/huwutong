<?php

namespace App\Workflows\Steps;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\WorkflowInstance;
use App\Services\BillingService;
use App\Services\PaymentManager;
use App\Workflows\BaseWorkflowStep;
use Illuminate\Support\Facades\Log;

/**
 * 步骤2: 处理支付
 */
class ProcessRenewalPayment extends BaseWorkflowStep
{
    public function __construct(
        protected BillingService $billingService,
        protected PaymentManager $paymentManager,
    ) {}

    public function name(): string
    {
        return 'process_payment';
    }

    public function description(): string
    {
        return '处理续费支付';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        $invoiceId = $context['invoice_id'] ?? null;
        if (!$invoiceId) {
            throw new \RuntimeException(__('app.common.missing_invoice_id'));
        }

        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            throw new \RuntimeException(__('app.common.invoice_not_found', ['id' => $invoiceId]));
        }

        $this->log('info', '处理支付', ['invoice' => $invoiceId, 'amount' => $invoice->total]);

        // 尝试用默认支付方式扣款
        $subscription = $instance->workflowable;
        $customer = $subscription?->customer;

        // 找默认支付方式
        $paymentMethod = null;
        if ($customer) {
            $paymentMethod = $customer->defaultPaymentMethod()
                ?? $customer->paymentMethods()->first();
        }

        if (!$paymentMethod) {
            throw new \RuntimeException(__('app.common.no_payment_method_available'));
        }

        $result = $this->paymentManager->charge([
            'amount' => (float) ($invoice->total ?? $invoice->subtotal ?? $context['amount'] ?? 0),
            'currency' => $invoice->currency ?? 'cny',
            'payment_method_id' => $paymentMethod->gateway_method_id,
            'customer_id' => $customer?->id,
            'description' => "续费发票 #{$invoice->id}",
            'metadata' => [
                'invoice_id' => $invoice->id,
                'subscription_id' => $subscription?->id,
                'workflow_instance_id' => $instance->id,
            ],
        ]);

        if ($result['success'] ?? false) {
            // 标记发票已支付
            $this->billingService->markInvoiceAsPaid($invoice, $result['transaction_id'] ?? 'wf_' . $instance->id);

            $context['payment_success'] = true;
            $context['transaction_id'] = $result['transaction_id'] ?? null;
            $context['payment_method_used'] = $paymentMethod->id;

            return [
                'success' => true,
                'transaction_id' => $context['transaction_id'],
            ];
        }

        throw new \RuntimeException($result['error'] ?? __('app.common.payment_failed'));
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        if (($output['success'] ?? false) && isset($output['transaction_id'])) {
            // 尝试退款（如果支付网关支持）
            try {
                $this->paymentManager->refund($output['transaction_id'], '工作流失败补偿退款');
                $this->log('info', '支付已退款(补偿)', ['transaction' => $output['transaction_id']]);
            } catch (\Throwable $e) {
                Log::warning('RenewalWorkflow: 退款失败', [
                    'transaction' => $output['transaction_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function maxRetries(): int
    {
        return 3;
    }

    public function retryDelay(): array|int
    {
        return [60, 300, 600]; // 1min, 5min, 10min
    }
}
