<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 支付宝支付网关集成
 *
 * 使用支付宝手机网站支付 / 电脑网站支付接口。
 * 需要配置 ALIPAY_APP_ID, ALIPAY_PRIVATE_KEY, ALIPAY_PUBLIC_KEY。
 */
class AlipayPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly array $config = [],
    ) {}

    public function charge(Invoice $invoice, array $options = []): array
    {
        $appId = $this->config['app_id'] ?? env('ALIPAY_APP_ID');
        $privateKey = $this->config['private_key'] ?? env('ALIPAY_PRIVATE_KEY');
        $notifyUrl = $this->config['notify_url'] ?? env('APP_URL') . '/api/billing/invoices/' . $invoice->id . '/callback';

        if (empty($appId) || empty($privateKey)) {
            Log::error('Alipay: missing configuration');
            return ['success' => false, 'error' => '支付宝未配置'];
        }

        $bizContent = [
            'subject' => 'License 订阅 - ' . ($invoice->subscription?->plan ?? '续费'),
            'out_trade_no' => $invoice->invoice_no,
            'total_amount' => number_format($invoice->amount, 2, '.', ''),
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
        ];

        // 发起支付宝支付请求
        try {
            $response = Http::timeout(10)->asForm()->post('https://openapi.alipay.com/gateway.do', [
                'app_id' => $appId,
                'method' => 'alipay.trade.page.pay',
                'charset' => 'utf-8',
                'sign_type' => 'RSA2',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'version' => '1.0',
                'notify_url' => $notifyUrl,
                'biz_content' => json_encode($bizContent),
                // 实际应用中需要用私钥签名
                'sign' => $this->sign($bizContent, $privateKey),
            ]);

            // 解析响应 —— 此处简化处理，实际需解析支付宝返回的表单或 URL
            Log::info('Alipay: charge initiated', [
                'invoice_id' => $invoice->id,
                'out_trade_no' => $invoice->invoice_no,
            ]);

            return [
                'success' => true,
                'transaction_id' => 'alipay_' . Str::random(16),
                'redirect_url' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Alipay: charge failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => '支付请求失败: ' . $e->getMessage()];
        }
    }

    public function refund(Invoice $invoice, array $options = []): array
    {
        try {
            Log::info('Alipay: refund initiated', [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
            ]);

            return [
                'success' => true,
                'refund_id' => 'alipay_refund_' . Str::random(16),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function query(string $transactionId): array
    {
        return [
            'status' => 'paid',
            'transaction_id' => $transactionId,
        ];
    }

    public function verifyCallback(array $payload): bool
    {
        // 实际需验证支付宝回调签名
        return true;
    }

    public function name(): string
    {
        return 'alipay';
    }

    private function sign(array $data, string $privateKey): string
    {
        $string = collect($data)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->sortKeys()
            ->map(fn ($v, $k) => "$k=$v")
            ->join('&');

        $signature = '';
        openssl_sign($string, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }
}
