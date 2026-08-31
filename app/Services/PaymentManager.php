<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use App\Services\Payment\AlipayPaymentGateway;
use App\Services\Payment\MockPaymentGateway;
use App\Services\Payment\PaypalPaymentGateway;
use App\Services\Payment\StripePaymentGateway;
use App\Services\Payment\WechatPaymentGateway;
use App\Services\Payment\YipayPaymentGateway;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * 支付网关管理器
 *
 * 根据配置选择实际支付网关，开发环境默认使用 Mock。
 * 支持的网关: mock, alipay, stripe
 */
class PaymentManager
{
    private ?PaymentGateway $gateway = null;

    /**
     * 获取当前支付网关
     */
    public function gateway(): PaymentGateway
    {
        if ($this->gateway === null) {
            $this->gateway = $this->resolveGateway();
        }
        return $this->gateway;
    }

    /**
     * 设置支付网关（用于测试或动态切换）
     */
    public function setGateway(PaymentGateway $gateway): void
    {
        $this->gateway = $gateway;
    }

    /**
     * 按驱动名切换网关（订单/checkout 指定 alipay|stripe 等）
     */
    public function useDriver(string $driver): void
    {
        $this->gateway = match ($driver) {
            'alipay' => App::make(AlipayPaymentGateway::class),
            'wechat' => App::make(WechatPaymentGateway::class),
            'stripe' => App::make(StripePaymentGateway::class),
            'paypal' => App::make(PaypalPaymentGateway::class),
            'yipay' => App::make(YipayPaymentGateway::class),
            'mock' => App::make(MockPaymentGateway::class),
            default => $this->resolveGateway(),
        };
    }

    /**
     * 发起支付
     */
    public function charge(Invoice $invoice, array $options = []): array
    {
        $result = $this->gateway()->charge($invoice, $options);

        Log::info('PaymentManager.charge', [
            'gateway' => $this->gateway()->name(),
            'invoice_id' => $invoice->id,
            'success' => $result['success'] ?? false,
        ]);

        return $result;
    }

    /**
     * 退款
     */
    public function refund(Invoice $invoice, array $options = []): array
    {
        return $this->gateway()->refund($invoice, $options);
    }

    /**
     * 查询支付状态
     */
    public function query(string $transactionId): array
    {
        return $this->gateway()->query($transactionId);
    }

    /**
     * 验证回调
     */
    public function verifyCallback(array $payload): bool
    {
        return $this->gateway()->verifyCallback($payload);
    }

    /**
     * 获取当前网关名称
     */
    public function gatewayName(): string
    {
        return $this->gateway()->name();
    }

    /**
     * 是否为异步确认网关（需 Webhook / 轮询确认，不能 charge 后立即标记已支付）
     */
    public function isAsyncGateway(?string $name = null): bool
    {
        $name ??= $this->gatewayName();

        return in_array($name, ['alipay', 'stripe', 'wechat', 'paypal', 'yipay'], true);
    }

    /**
     * charge 成功后是否可立即标记发票/订单为已支付
     */
    public function settlesSynchronously(?string $name = null): bool
    {
        return ! $this->isAsyncGateway($name);
    }

    /**
     * 解析网关实例
     */
    private function resolveGateway(): PaymentGateway
    {
        $driver = config('payment.driver', env('PAYMENT_DRIVER', 'mock'));

        return match ($driver) {
            'alipay' => App::make(AlipayPaymentGateway::class),
            'wechat' => App::make(WechatPaymentGateway::class),
            'stripe' => App::make(StripePaymentGateway::class),
            'paypal' => App::make(PaypalPaymentGateway::class),
            'yipay' => App::make(YipayPaymentGateway::class),
            default  => App::make(MockPaymentGateway::class),
        };
    }
}
