<?php

namespace Tests\Support;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;

/** 模拟异步网关：charge 成功但不代表已到账 */
class AsyncPaymentGateway implements PaymentGateway
{
    public function charge(Invoice $invoice, array $options = []): array
    {
        return [
            'success' => true,
            'transaction_id' => 'async_txn_test_001',
            'redirect_url' => 'https://pay.example.com/checkout',
        ];
    }

    public function refund(Invoice $invoice, array $options = []): array
    {
        return ['success' => true, 'refund_id' => 'async_refund_001'];
    }

    public function query(string $transactionId): array
    {
        return ['status' => 'pending', 'transaction_id' => $transactionId];
    }

    public function verifyCallback(array $payload): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'stripe';
    }
}
