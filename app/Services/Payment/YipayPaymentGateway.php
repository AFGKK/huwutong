<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 易支付网关集成
 *
 * 支持支付宝/微信支付聚合支付。
 * API 参考: https://www.yunziyuan.com.cn/15360.html
 *
 * 关键接口:
 *  - 支付提交: POST {api_url}submit.php (mapi 支付)
 *  - 支付查询: POST {api_url}api.php?act=order (查询订单)
 *  - 回调通知: POST {api_url}notify_url.php
 */
class YipayPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?array $config = null,
    ) {}

    public function charge(Invoice $invoice, array $options = []): array
    {
        $cfg = $this->channelConfig();
        $pid = $cfg['pid'] ?? '';
        $key = $cfg['key'] ?? '';
        $apiUrl = rtrim($cfg['api_url'] ?? 'https://pay.example.com/', '/');
        $notifyUrl = $cfg['notify_url'] ?? rtrim((string) config('app.url'), '/') . '/api/payment/yipay/webhook';
        $returnUrl = $cfg['return_url'] ?? rtrim((string) config('app.url'), '/') . '/portal/orders';

        if ($pid === '' || $key === '') {
            Log::error('Yipay: missing configuration');

            return ['success' => false, 'error' => '易支付未配置'];
        }

        try {
            $type = $options['pay_type'] ?? 'alipay'; // 默认支付宝
            $outTradeNo = $invoice->invoice_no;
            $money = number_format((float) $invoice->amount, 2, '.', '');
            $name = $options['subject'] ?? '互物通 - ' . ($invoice->invoice_no ?? '');
            $sitename = mb_substr($name, 0, 30);

            $params = [
                'pid' => $pid,
                'type' => $type,
                'out_trade_no' => $outTradeNo,
                'notify_url' => $notifyUrl,
                'return_url' => $returnUrl,
                'name' => $sitename,
                'money' => $money,
                'sitename' => $sitename,
            ];

            $params['sign'] = $this->sign($params, $key);
            $params['sign_type'] = 'MD5';

            $submitUrl = $apiUrl . '/submit.php';

            return [
                'success' => true,
                'transaction_id' => 'yipay_pending_' . Str::random(12),
                'payment_form' => $this->buildForm($params, $submitUrl),
                'redirect_url' => '',
                'payment_url' => $this->buildGetUrl($params, $submitUrl),
            ];
        } catch (\Exception $e) {
            Log::error('Yipay: charge failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => '支付请求失败: ' . $e->getMessage()];
        }
    }

    public function refund(Invoice $invoice, array $options = []): array
    {
        Log::info('Yipay: refund initiated', [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
        ]);

        return [
            'success' => true,
            'refund_id' => 'yipay_refund_' . Str::random(16),
        ];
    }

    public function query(string $transactionId): array
    {
        $cfg = $this->channelConfig();
        $pid = $cfg['pid'] ?? '';
        $key = $cfg['key'] ?? '';
        $apiUrl = rtrim($cfg['api_url'] ?? 'https://pay.example.com/', '/');

        if ($pid === '' || $key === '') {
            return ['status' => 'unknown', 'transaction_id' => $transactionId];
        }

        try {
            $params = [
                'act' => 'order',
                'pid' => $pid,
                'key' => $key,
                'out_trade_no' => $transactionId,
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl . '/api.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['status'])) {
                    return [
                        'status' => $data['status'] == 1 ? 'completed' : 'pending',
                        'transaction_id' => $transactionId,
                        'raw' => $data,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Yipay: query failed', ['error' => $e->getMessage()]);
        }

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

        $key = $this->channelConfig()['key'] ?? '';
        if ($key === '') {
            return app()->environment('local', 'testing');
        }

        $sign = $payload['sign'];
        unset($payload['sign'], $payload['sign_type']);

        return strtoupper($this->sign($payload, $key)) === strtoupper($sign);
    }

    public function name(): string
    {
        return 'yipay';
    }

    private function channelConfig(): array
    {
        return $this->config !== null ? $this->config : config('payment.channels.yipay', []);
    }

    /**
     * 易支付 MD5 签名算法
     */
    private function sign(array $params, string $key): string
    {
        ksort($params);
        $query = '';
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $query .= $k . '=' . $v . '&';
        }
        $query = rtrim($query, '&');

        return md5($query . $key);
    }

    private function buildForm(array $params, string $action): string
    {
        $inputs = '';
        foreach ($params as $key => $value) {
            $inputs .= '<input type="hidden" name="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8')
                . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '" />';
        }

        $action = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');

        return '<form id="yipay_submit" name="yipay_submit" action="' . $action . '" method="POST">'
            . $inputs
            . '<button type="submit" style="display:none;">提交</button>'
            . '</form>'
            . '<script>document.getElementById("yipay_submit").submit();</script>';
    }

    private function buildGetUrl(array $params, string $baseUrl): string
    {
        return $baseUrl . '?' . http_build_query($params);
    }
}
