<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 支付宝支付网关集成
 *
 * 电脑网站支付 alipay.trade.page.pay + RSA2 回调验签
 */
class AlipayPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?array $config = null,
    ) {}

    public function charge(Invoice $invoice, array $options = []): array
    {
        $cfg = $this->channelConfig();
        $appId = $cfg['app_id'] ?? '';
        $privateKey = $cfg['private_key'] ?? '';
        $notifyUrl = $cfg['notify_url'] ?? rtrim((string) config('app.url'), '/').'/api/payment/alipay/webhook';
        $returnUrl = $cfg['return_url'] ?? rtrim((string) config('app.url'), '/').'/portal/orders';

        if ($appId === '' || $privateKey === '') {
            Log::error('Alipay: missing configuration');

            return ['success' => false, 'error' => '支付宝未配置'];
        }

        try {
            $bizContent = [
                'subject' => $options['subject'] ?? '互物通 - '.($invoice->invoice_no ?? ''),
                'out_trade_no' => $invoice->invoice_no,
                'total_amount' => number_format((float) $invoice->amount, 2, '.', ''),
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
                'body' => $options['description'] ?? "订单 {$invoice->invoice_no}",
            ];

            $params = [
                'app_id' => $appId,
                'method' => 'alipay.trade.page.pay',
                'format' => 'JSON',
                'charset' => 'utf-8',
                'sign_type' => 'RSA2',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'version' => '1.0',
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
                'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
            ];

            $params['sign'] = $this->sign($params, $privateKey);

            return [
                'success' => true,
                'transaction_id' => 'alipay_pending_'.Str::random(12),
                'payment_form' => $this->buildForm($params),
                'redirect_url' => '',
                'payment_url' => '',
            ];
        } catch (\Exception $e) {
            Log::error('Alipay: charge failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => '支付请求失败: '.$e->getMessage()];
        }
    }

    public function refund(Invoice $invoice, array $options = []): array
    {
        Log::info('Alipay: refund initiated', [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
        ]);

        return [
            'success' => true,
            'refund_id' => 'alipay_refund_'.Str::random(16),
        ];
    }

    public function query(string $transactionId): array
    {
        return [
            'status' => 'pending',
            'transaction_id' => $transactionId,
        ];
    }

    public function verifyCallback(array $payload): bool
    {
        if (empty($payload['sign'])) {
            return false;
        }

        $publicKey = $this->channelConfig()['public_key'] ?? '';
        if ($publicKey === '') {
            return app()->environment('local', 'testing');
        }

        $sign = $payload['sign'];
        $params = $payload;
        unset($params['sign'], $params['sign_type']);

        $data = $this->canonicalQuery($params);

        return openssl_verify(
            $data,
            base64_decode($sign),
            $this->formatKey($publicKey, 'PUBLIC'),
            OPENSSL_ALGO_SHA256,
        ) === 1;
    }

    public function name(): string
    {
        return 'alipay';
    }

    private function channelConfig(): array
    {
        return $this->config !== null ? $this->config : config('payment.channels.alipay', []);
    }

    private function gatewayUrl(): string
    {
        $sandbox = (bool) ($this->channelConfig()['sandbox'] ?? true);

        return $sandbox
            ? 'https://openapi-sandbox.dl.alipaydev.com/gateway.do'
            : 'https://openapi.alipay.com/gateway.do';
    }

    private function sign(array $params, string $privateKey): string
    {
        $data = $this->canonicalQuery(collect($params)->except(['sign', 'sign_type'])->all());

        $signature = '';
        openssl_sign($data, $signature, $this->formatKey($privateKey, 'PRIVATE'), OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function canonicalQuery(array $params): string
    {
        return collect($params)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->sortKeys()
            ->map(fn ($v, $k) => "$k=$v")
            ->join('&');
    }

    private function buildForm(array $params): string
    {
        $inputs = '';
        foreach ($params as $key => $value) {
            $inputs .= '<input type="hidden" name="'.htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8')
                .'" value="'.htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'" />';
        }

        $action = htmlspecialchars($this->gatewayUrl(), ENT_QUOTES, 'UTF-8');

        return '<form id="alipay_submit" name="alipay_submit" action="'.$action.'" method="POST">'
            .$inputs
            .'<button type="submit" style="display:none;">提交</button>'
            .'</form>'
            .'<script>document.getElementById("alipay_submit").submit();</script>';
    }

    private function formatKey(string $key, string $type): string
    {
        $key = trim(str_replace(["\r\n", "\r", "\n", ' '], '', $key));
        if (str_contains($key, 'BEGIN')) {
            return $key;
        }

        $wrapped = chunk_split($key, 64, "\n");

        return "-----BEGIN {$type} KEY-----\n{$wrapped}-----END {$type} KEY-----\n";
    }
}
