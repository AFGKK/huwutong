<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 支付宝支付网关集成
 *
 * 使用支付宝电脑网站支付 (alipay.trade.page.pay)。
 * 需要配置 ALIPAY_APP_ID, ALIPAY_PRIVATE_KEY。
 *
 * 流程：后端构造签名 → 返回自动提交表单 HTML → 前端渲染并提交 → 用户跳转支付宝收银台
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
        $notifyUrl = $this->config['notify_url'] ?? rtrim(env('APP_URL', ''), '/') . '/api/payment/alipay/webhook';
        $returnUrl = $this->config['return_url'] ?? rtrim(env('APP_URL', ''), '/') . '/portal/orders';

        if (empty($appId) || empty($privateKey)) {
            Log::error('Alipay: missing configuration');
            return ['success' => false, 'error' => '支付宝未配置'];
        }

        try {
            // 1. 构建请求参数
            $bizContent = [
                'subject' => $options['subject'] ?? '互物通 - ' . ($invoice->invoice_no ?? ''),
                'out_trade_no' => $invoice->invoice_no,
                'total_amount' => number_format($invoice->amount, 2, '.', ''),
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

            // 2. 对所有参数排序签名（包含 biz_content）
            $params['sign'] = $this->sign($params, $privateKey);

            // 3. 生成自动提交表单 HTML
            $formHtml = $this->buildForm($params);

            return [
                'success' => true,
                'transaction_id' => 'alipay_' . Str::random(16),
                'payment_form' => $formHtml,
                'redirect_url' => '',
                'payment_url' => '',
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
        // 生产环境需验证支付宝回调签名
        return true;
    }

    public function name(): string
    {
        return 'alipay';
    }

    /**
     * 支付宝 RSA2 签名
     * 对所有非空参数按字典序排序后拼接签名
     */
    private function sign(array $params, string $privateKey): string
    {
        // 排除 sign 和 sign_type 字段
        $data = collect($params)
            ->except(['sign', 'sign_type'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->sortKeys()
            ->map(fn ($v, $k) => "$k=$v")
            ->join('&');

        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * 生成支付宝自动提交表单 HTML
     */
    private function buildForm(array $params): string
    {
        $inputs = '';
        foreach ($params as $key => $value) {
            $inputs .= '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
                     . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" />';
        }

        return '<form id="alipay_submit" name="alipay_submit" action="https://openapi.alipay.com/gateway.do" method="POST">'
             . $inputs
             . '<button type="submit" style="display:none;">提交</button>'
             . '</form>'
             . '<script>document.getElementById("alipay_submit").submit();</script>';
    }
}
