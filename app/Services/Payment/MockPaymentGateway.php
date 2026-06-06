<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 模拟支付网关 —— 开发/测试环境使用
 *
 * 不产生真实交易，直接标记支付成功。
 * 可配置 success_rate 来模拟支付失败场景。
 */
class MockPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly float $successRate = 1.0,
    ) {}

    public function charge(Invoice $invoice, array $options = []): array
    {
        $shouldSucceed = (float) mt_rand() / mt_getrandmax() < $this->successRate;

        if (! $shouldSucceed) {
            Log::warning('MockPaymentGateway: payment declined', [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
            ]);
            return ['success' => false, 'error' => '模拟支付失败（可配置的成功率）'];
        }

        $transactionId = 'mock_txn_' . Str::random(16);

        Log::info('MockPaymentGateway: payment succeeded', [
            'invoice_id' => $invoice->id,
            'transaction_id' => $transactionId,
        ]);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
        ];
    }

    public function refund(Invoice $invoice, array $options = []): array
    {
        return [
            'success' => true,
            'refund_id' => 'mock_refund_' . Str::random(16),
        ];
    }

    public function query(string $transactionId): array
    {
        return [
            'status' => 'paid',
            'transaction_id' => $transactionId,
            'paid_at' => now()->toDateTimeString(),
        ];
    }

    public function verifyCallback(array $payload): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'mock';
    }
}
