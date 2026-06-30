<?php

namespace App\Workflows\Steps;

use App\Models\Subscription;
use App\Models\WorkflowInstance;
use App\Services\BillingService;
use App\Workflows\BaseWorkflowStep;
use Illuminate\Support\Facades\Log;

/**
 * 步骤1: 创建续费发票
 */
class CreateRenewalInvoice extends BaseWorkflowStep
{
    public function __construct(protected BillingService $billingService) {}

    public function name(): string
    {
        return 'create_invoice';
    }

    public function description(): string
    {
        return '创建续费账单';
    }

    public function execute(WorkflowInstance $instance, array &$context, array $input = []): array
    {
        /** @var Subscription $subscription */
        $subscription = $instance->workflowable;

        if (!$subscription) {
            throw new \RuntimeException('工作流未关联 Subscription');
        }

        $this->log('info', '创建续费发票', ['subscription' => $subscription->id]);

        $invoice = $this->billingService->createInvoice($subscription, 'subscription_renew');
        $context['invoice_id'] = $invoice->id;
        $context['amount'] = $invoice->total ?? $subscription->price;

        return [
            'invoice_id' => $invoice->id,
            'amount' => $context['amount'],
        ];
    }

    public function compensate(WorkflowInstance $instance, array &$context, array $input, array $output): void
    {
        if (isset($output['invoice_id'])) {
            $invoice = \App\Models\Invoice::find($output['invoice_id']);
            if ($invoice && $invoice->status === 'pending') {
                $invoice->update(['status' => 'cancelled', 'notes' => '续费工作流失败，已取消']);
                $this->log('info', '发票已取消(补偿)', ['invoice_id' => $output['invoice_id']]);
            }
        }
    }

    public function maxRetries(): int
    {
        return 3;
    }

    public function retryDelay(): array|int
    {
        return [30, 60, 120];
    }
}
