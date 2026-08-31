<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 微信支付网关集成
 *
 * 使用微信支付 Native / JSAPI / H5 支付接口。
 * 需要配置 WECHAT_APP_ID, WECHAT_MCH_ID, WECHAT_PAY_KEY。
 */
class WechatPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly array $config = [],
    ) {}

    public function charge(Invoice $invoice, array $options = []): array
    {
        $appId = $this->config['app_id'] ?? config('payment.channels.wechat.app_id', '');
        $mchId = $this->config['mch_id'] ?? config('payment.channels.wechat.mch_id', '');
        $key = $this->config['key'] ?? config('payment.channels.wechat.key', '');
        $notifyUrl = $this->config['notify_url'] ?? config('payment.channels.wechat.notify_url', config('app.url', '') . '/api/payment/wechat/webhook');

        if (empty($appId) || empty($mchId) || empty($key)) {
            Log::error('WeChat Pay: missing configuration');
            return ['success' => false, 'error' => '微信支付未配置'];
        }

        $amount = (int) ($invoice->amount * 100); // 单位：分
        $outTradeNo = $invoice->invoice_no;
        $description = 'License 订阅 - ' . ($invoice->subscription?->plan ?? '续费');

        // 构建统一下单请求
        $params = [
            'appid' => $appId,
            'mchid' => $mchId,
            'description' => $description,
            'out_trade_no' => $outTradeNo,
            'notify_url' => $notifyUrl,
            'amount' => [
                'total' => $amount,
                'currency' => 'CNY',
            ],
        ];

        // 根据支付场景选择 trade_type
        $tradeType = $options['trade_type'] ?? 'NATIVE';
        if ($tradeType === 'JSAPI' && !empty($options['openid'])) {
            $params['payer']['openid'] = $options['openid'];
        }

        try {
            // 微信支付 V3 API
            $response = Http::withHeaders([
                'Authorization' => $this->buildAuthHeader($params, $key),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Huwutong/1.0',
            ])->timeout(10)->post('https://api.mch.weixin.qq.com/v3/pay/transactions/' . strtolower($tradeType), $params);

            $body = $response->json();

            if (!$response->successful()) {
                $errMsg = $body['message'] ?? '微信支付下单失败';
                Log::error('WeChat Pay: unified order failed', [
                    'out_trade_no' => $outTradeNo,
                    'error' => $errMsg,
                ]);
                return ['success' => false, 'error' => $errMsg];
            }

            $prepayId = $body['prepay_id'] ?? '';

            Log::info('WeChat Pay: order created', [
                'invoice_id' => $invoice->id,
                'out_trade_no' => $outTradeNo,
                'prepay_id' => $prepayId,
            ]);

            $result = [
                'success' => true,
                'transaction_id' => 'wx_' . Str::random(16),
                'prepay_id' => $prepayId,
            ];

            // Native 支付返回 code_url（二维码链接）
            if ($tradeType === 'NATIVE' && !empty($body['code_url'])) {
                $result['redirect_url'] = $body['code_url'];
                $result['qr_code'] = $body['code_url'];
            }

            // JSAPI 支付返回调起支付的参数
            if ($tradeType === 'JSAPI' && !empty($prepayId)) {
                $result['payment_params'] = $this->buildJsapiParams($appId, $prepayId, $key);
            }

            // H5 支付返回跳转 URL
            if ($tradeType === 'H5' && !empty($body['h5_url'])) {
                $result['redirect_url'] = $body['h5_url'];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('WeChat Pay: charge failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => '微信支付请求失败: ' . $e->getMessage()];
        }
    }

    public function refund(Invoice $invoice, array $options = []): array
    {
        $mchId = $this->config['mch_id'] ?? config('payment.channels.wechat.mch_id', '');
        $key = $this->config['key'] ?? config('payment.channels.wechat.key', '');

        try {
            $transactionId = $invoice->metadata['transaction_id'] ?? '';
            $outTradeNo = $invoice->invoice_no;

            $params = [
                'transaction_id' => $transactionId,
                'out_trade_no' => $outTradeNo,
                'out_refund_no' => 'refund_' . $outTradeNo,
                'amount' => [
                    'refund' => (int) ($invoice->amount * 100),
                    'total' => (int) ($invoice->amount * 100),
                    'currency' => 'CNY',
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => $this->buildRefundAuthHeader($params, $key),
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.mch.weixin.qq.com/v3/refund/domestic/refunds', $params);

            if (!$response->successful()) {
                $errMsg = $response->json()['message'] ?? '微信退款失败';
                Log::error('WeChat Pay: refund failed', [
                    'out_trade_no' => $outTradeNo,
                    'error' => $errMsg,
                ]);
                return ['success' => false, 'error' => $errMsg];
            }

            Log::info('WeChat Pay: refund initiated', [
                'invoice_id' => $invoice->id,
            ]);

            return [
                'success' => true,
                'refund_id' => 'wx_refund_' . Str::random(16),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function query(string $transactionId): array
    {
        $mchId = $this->config['mch_id'] ?? config('payment.channels.wechat.mch_id', '');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'WECHAT_PAY',
                'Accept' => 'application/json',
            ])->timeout(10)->get("https://api.mch.weixin.qq.com/v3/pay/transactions/id/{$transactionId}?mchid={$mchId}");

            if ($response->successful()) {
                $body = $response->json();
                return [
                    'status' => $body['trade_state'] ?? 'unknown',
                    'transaction_id' => $transactionId,
                    'paid_at' => $body['success_time'] ?? null,
                ];
            }

            return ['status' => 'unknown', 'transaction_id' => $transactionId];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'transaction_id' => $transactionId, 'error' => $e->getMessage()];
        }
    }

    public function verifyCallback(array $payload): bool
    {
        // 实际需验证微信回调签名
        // 微信支付 V3 使用 Wechatpay-Signature header 验签
        return true;
    }

    public function name(): string
    {
        return 'wechat';
    }

    /**
     * 构建微信支付 V3 认证头
     *
     * 微信支付 V3 API 使用基于 RSA-SHA256 的 Authorization 签名方案：
     * Authorization: WECHATPAY2-SHA256-RSA2048
     *   mchid="...",nonce_str="...",timestamp="...",serial_no="...",signature="..."
     *
     * @param array  $body    请求体
     * @param string $key     商户 API v3 密钥
     * @param string $method  HTTP 方法 (GET/POST/PATCH)
     * @param string $urlPath API 路径（如 /v3/pay/transactions/native）
     */
    private function buildAuthHeader(array $body, string $key, string $method = 'POST', string $urlPath = '/v3/pay/transactions/native'): string
    {
        $serialNo = config('payment.channels.wechat.serial_no', env('WECHAT_PAY_SERIAL_NO', ''));
        $privateKey = config('payment.channels.wechat.private_key', env('WECHAT_PAY_PRIVATE_KEY', ''));

        if (empty($serialNo) || empty($privateKey)) {
            Log::warning('WeChat Pay V3: missing SERIAL_NO or PRIVATE_KEY, using fallback');
            return 'WECHAT_PAY';
        }

        $timestamp = (string) time();
        $nonce = Str::random(32);

        $bodyJson = empty($body) ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $message = "{$method}\n{$urlPath}\n{$timestamp}\n{$nonce}\n{$bodyJson}\n";

        $signature = '';
        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $mchId = config('payment.channels.wechat.mch_id', env('WECHAT_MCH_ID', ''));

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%s",serial_no="%s",signature="%s"',
            $mchId,
            $timestamp,
            $serialNo,
            base64_encode($signature)
        );
    }

    /**
     * 构建用于 REFUND API 的认证头（路径不同）
     */
    private function buildRefundAuthHeader(array $body, string $key): string
    {
        return $this->buildAuthHeader($body, $key, 'POST', '/v3/refund/domestic/refunds');
    }

    /**
     * 构建 JSAPI 调起支付参数
     */
    private function buildJsapiParams(string $appId, string $prepayId, string $key): array
    {
        $timeStamp = (string) time();
        $nonceStr = Str::random(32);
        $package = "prepay_id={$prepayId}";
        $signType = 'RSA';

        return [
            'appId' => $appId,
            'timeStamp' => $timeStamp,
            'nonceStr' => $nonceStr,
            'package' => $package,
            'signType' => $signType,
        ];
    }
}
