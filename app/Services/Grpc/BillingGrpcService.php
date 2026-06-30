<?php

namespace App\Services\Grpc;

/**
 * Billing gRPC 服务客户端
 */
class BillingGrpcService extends GrpcService
{
    protected string $serviceName = 'billing';

    protected function getConfigKey(): string
    {
        return 'billing_service';
    }

    public function createSubscription(int $customerId, int $productId, string $planType, string $billingCycle, int $amount): array
    {
        return $this->call(__FUNCTION__, [
            'customer_id' => $customerId,
            'product_id' => $productId,
            'plan_type' => $planType,
            'billing_cycle' => $billingCycle,
            'amount' => $amount,
        ]);
    }

    public function getSubscription(int $subscriptionId): array
    {
        return $this->call(__FUNCTION__, ['subscription_id' => $subscriptionId]);
    }

    public function cancelSubscription(int $subscriptionId, string $reason): array
    {
        return $this->call(__FUNCTION__, [
            'subscription_id' => $subscriptionId,
            'reason' => $reason,
        ]);
    }

    public function getInvoice(int $invoiceId): array
    {
        return $this->call(__FUNCTION__, ['invoice_id' => $invoiceId]);
    }

    public function listInvoices(int $customerId, array $filters = []): array
    {
        return $this->call(__FUNCTION__, array_merge(['customer_id' => $customerId], $filters));
    }

    public function recordUsage(int $subscriptionId, string $metricKey, float $quantity): array
    {
        return $this->call(__FUNCTION__, [
            'subscription_id' => $subscriptionId,
            'metric_key' => $metricKey,
            'quantity' => $quantity,
        ]);
    }

    public function getUsage(int $subscriptionId, string $metricKey): array
    {
        return $this->call(__FUNCTION__, [
            'subscription_id' => $subscriptionId,
            'metric_key' => $metricKey,
        ]);
    }

    public function checkQuota(int $subscriptionId, string $metricKey, float $requestedAmount): array
    {
        return $this->call(__FUNCTION__, [
            'subscription_id' => $subscriptionId,
            'metric_key' => $metricKey,
            'requested_amount' => $requestedAmount,
        ]);
    }
}
