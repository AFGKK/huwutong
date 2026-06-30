<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PayPal 支付网关集成
 *
 * 使用 PayPal REST API (Orders v2) 处理支付。
 * 需要配置 PAYPAL_CLIENT_ID, PAYPAL_CLIENT_SECRET。
 */
class PaypalPaymentGateway implements PaymentGateway
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly array $config = [],
    ) {}

    /**
     * 获取 API 基础 URL（沙箱/生产）
     */
    private function baseUrl(): string
    {
        $sandbox = $this->config['sandbox'] ?? env('PAYPAL_SANDBOX', true);
        return $sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * 获取 OAuth 2.0 Access Token
     */
    private function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $clientId = $this->config['client_id'] ?? env('PAYPAL_CLIENT_ID');
        $clientSecret = $this->config['client_secret'] ?? env('PAYPAL_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            Log::error('PayPal: missing client_id or client_secret');
            return null;
        }

        try {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->timeout(10)
                ->post($this->baseUrl() . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                Log::error('PayPal: failed to get access token', [
                    'error' => $response->body(),
                ]);
                return null;
            }

            $body = $response->json();
            $this->accessToken = $body['access_token'] ?? null;
            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error('PayPal: getAccessToken exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function charge(Invoice $invoice, array $options = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'PayPal 未配置或认证失败'];
        }

        $amount = number_format($invoice->amount, 2, '.', '');
        $currency = $invoice->currency ?? 'USD';
        $returnUrl = $options['return_url'] ?? rtrim(env('APP_URL', ''), '/') . '/payment/success';
        $cancelUrl = $options['cancel_url'] ?? rtrim(env('APP_URL', ''), '/') . '/payment/cancel';

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post($this->baseUrl() . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => (string) $invoice->id,
                            'invoice_id' => $invoice->invoice_no,
                            'amount' => [
                                'currency_code' => strtoupper($currency),
                                'value' => $amount,
                                'breakdown' => [
                                    'item_total' => [
                                        'currency_code' => strtoupper($currency),
                                        'value' => $amount,
                                    ],
                                ],
                            ],
                            'description' => 'License 订阅 - ' . ($invoice->subscription?->plan ?? '续费'),
                        ],
                    ],
                    'payment_source' => [
                        'paypal' => [
                            'experience_context' => [
                                'return_url' => $returnUrl,
                                'cancel_url' => $cancelUrl,
                                'landing_page' => 'LOGIN',
                                'user_action' => 'PAY_NOW',
                            ],
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                $errMsg = $response->json()['message'] ?? 'PayPal 创建订单失败';
                Log::error('PayPal: create order failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $errMsg,
                ]);
                return ['success' => false, 'error' => $errMsg];
            }

            $body = $response->json();
            $orderId = $body['id'] ?? '';
            $approvalUrl = '';

            // 从 links 中查找 approval_url
            foreach ($body['links'] ?? [] as $link) {
                if (($link['rel'] ?? '') === 'payer-action') {
                    $approvalUrl = $link['href'] ?? '';
                    break;
                }
            }

            Log::info('PayPal: order created', [
                'invoice_id' => $invoice->id,
                'order_id' => $orderId,
            ]);

            return [
                'success' => true,
                'transaction_id' => $orderId,
                'redirect_url' => $approvalUrl,
                'order_id' => $orderId,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal: charge failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'PayPal 支付请求失败: ' . $e->getMessage()];
        }
    }

    /**
     * 捕获已批准的 PayPal 订单（完成支付）
     */
    public function capture(string $orderId): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'PayPal 认证失败'];
        }

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(10)
                ->post($this->baseUrl() . "/v2/checkout/orders/{$orderId}/capture");

            if (!$response->successful()) {
                $errMsg = $response->json()['message'] ?? 'PayPal 捕获订单失败';
                Log::error('PayPal: capture failed', ['order_id' => $orderId, 'error' => $errMsg]);
                return ['success' => false, 'error' => $errMsg];
            }

            $body = $response->json();
            $captureId = $body['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';

            return [
                'success' => true,
                'capture_id' => $captureId,
                'transaction_id' => $orderId,
                'status' => $body['status'] ?? 'COMPLETED',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function refund(Invoice $invoice, array $options = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'PayPal 认证失败'];
        }

        $captureId = $invoice->metadata['capture_id'] ?? '';
        if (empty($captureId)) {
            return ['success' => false, 'error' => '未找到 PayPal 捕获 ID'];
        }

        try {
            $amount = number_format($invoice->amount, 2, '.', '');
            $currency = $invoice->currency ?? 'USD';

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(10)
                ->post($this->baseUrl() . "/v2/payments/captures/{$captureId}/refund", [
                    'amount' => [
                        'value' => $amount,
                        'currency_code' => strtoupper($currency),
                    ],
                ]);

            if (!$response->successful()) {
                $errMsg = $response->json()['message'] ?? 'PayPal 退款失败';
                Log::error('PayPal: refund failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $errMsg,
                ]);
                return ['success' => false, 'error' => $errMsg];
            }

            $body = $response->json();
            $refundId = $body['id'] ?? '';

            Log::info('PayPal: refund processed', [
                'invoice_id' => $invoice->id,
                'refund_id' => $refundId,
            ]);

            return [
                'success' => true,
                'refund_id' => $refundId,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function query(string $transactionId): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['status' => 'unknown', 'transaction_id' => $transactionId];
        }

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(10)
                ->get($this->baseUrl() . "/v2/checkout/orders/{$transactionId}");

            if ($response->successful()) {
                $body = $response->json();
                return [
                    'status' => $body['status'] ?? 'unknown',
                    'transaction_id' => $transactionId,
                    'paid_at' => $body['update_time'] ?? null,
                ];
            }

            return ['status' => 'unknown', 'transaction_id' => $transactionId];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'transaction_id' => $transactionId, 'error' => $e->getMessage()];
        }
    }

    public function verifyCallback(array $payload): bool
    {
        // PayPal 使用 webhook ID 验证，需在面板配置 webhook URL
        return true;
    }

    public function name(): string
    {
        return 'paypal';
    }
}
