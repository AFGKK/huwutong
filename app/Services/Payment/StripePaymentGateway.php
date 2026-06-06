<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * Stripe 支付网关集成
 *
 * 需要配置 STRIPE_KEY 和 STRIPE_SECRET。
 */
class StripePaymentGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly array $config = [],
    ) {}

    private function client(): StripeClient
    {
        if ($this->client === null) {
            $secret = $this->config['secret'] ?? env('STRIPE_SECRET');
            $this->client = new StripeClient($secret);
        }
        return $this->client;
    }

    public function charge(Invoice $invoice, array $options = []): array
    {
        $secretKey = $this->config['secret'] ?? env('STRIPE_SECRET');
        if (empty($secretKey)) {
            Log::error('Stripe: missing secret key');
            return ['success' => false, 'error' => 'Stripe 未配置'];
        }

        try {
            $paymentIntent = $this->client()->paymentIntents->create([
                'amount' => (int) ($invoice->amount * 100), // 单位：分
                'currency' => strtolower($invoice->currency ?? 'cny'),
                'metadata' => [
                    'invoice_no' => $invoice->invoice_no,
                    'invoice_id' => $invoice->id,
                ],
                'description' => 'License 订阅 - ' . ($invoice->subscription?->plan ?? '续费'),
            ]);

            Log::info('Stripe: payment intent created', [
                'invoice_id' => $invoice->id,
                'intent_id' => $paymentIntent->id,
            ]);

            return [
                'success' => true,
                'transaction_id' => $paymentIntent->id,
                'redirect_url' => $paymentIntent->next_action?->redirect_to_url?->url
                    ?? $paymentIntent->hosted_invoice_url
                    ?? null,
                'client_secret' => $paymentIntent->client_secret,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe: charge failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Stripe 支付失败: ' . $e->getMessage()];
        }
    }

    public function refund(Invoice $invoice, array $options = []): array
    {
        try {
            $transactionId = $invoice->metadata['transaction_id'] ?? null;
            if (! $transactionId) {
                return ['success' => false, 'error' => '未找到支付交易记录'];
            }

            $refund = $this->client()->refunds->create([
                'payment_intent' => $transactionId,
                'amount' => (int) ($invoice->amount * 100),
            ]);

            return [
                'success' => true,
                'refund_id' => $refund->id,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function query(string $transactionId): array
    {
        try {
            $intent = $this->client()->paymentIntents->retrieve($transactionId);
            return [
                'status' => $intent->status,
                'transaction_id' => $transactionId,
                'paid_at' => $intent->status === 'succeeded' ? now()->toDateTimeString() : null,
            ];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    public function verifyCallback(array $payload): bool
    {
        // Stripe Webhook 签名验证由中间件处理
        return true;
    }

    public function name(): string
    {
        return 'stripe';
    }
}
