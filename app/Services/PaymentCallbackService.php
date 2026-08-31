<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentCallback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 支付回调处理中心 (M2-144 🛒)
 *
 * 统一处理支付成功/失败/退款回调：
 * - 幂等性防重复处理
 * - 订单状态自动更新
 * - 自动发货触发 (M2-142)
 * - 异常告警
 */
class PaymentCallbackService
{
    public function __construct(
        protected AutoDeliveryEngine $deliveryEngine,
    ) {}

    /**
     * 处理支付回调入口
     */
    public function handle(array $payload, string $gateway): array
    {
        // 1. 解析回调
        $parsed = $this->parsePayload($payload, $gateway);
        if (!$parsed) {
            return ['success' => false, 'error' => '无法解析回调数据'];
        }

        // 2. 幂等性检查
        $existing = $this->checkIdempotency($gateway, $parsed['event_id']);
        if ($existing) {
            return [
                'success' => true,
                'duplicate' => true,
                'message' => '重复回调已忽略',
                'callback_id' => $existing->id,
            ];
        }

        // 3. 创建回调记录
        $callback = PaymentCallback::create([
            'order_id' => $parsed['order_id'] ?? null,
            'gateway' => $gateway,
            'event_type' => $parsed['event_type'],
            'transaction_id' => $parsed['transaction_id'] ?? null,
            'merchant_order_no' => $parsed['merchant_order_no'] ?? null,
            'amount' => $parsed['amount'] ?? null,
            'currency' => $parsed['currency'] ?? 'CNY',
            'status' => 'received',
            'raw_payload' => $payload,
            'idempotency_key' => "{$gateway}:{$parsed['event_id']}",
        ]);

        try {
            // 4. 执行业务逻辑
            DB::beginTransaction();

            $result = match ($parsed['event_type']) {
                'payment_success' => $this->handlePaymentSuccess($callback, $parsed),
                'payment_failed' => $this->handlePaymentFailed($callback, $parsed),
                'refund' => $this->handleRefund($callback, $parsed),
                'chargeback' => $this->handleChargeback($callback, $parsed),
default => throw new \RuntimeException(__("app.payment_callback.unknown_event_type", ['type' => $parsed['event_type']])),
            };

            $callback->update([
                'status' => 'completed',
                'response' => is_string($result) ? $result : json_encode($result),
                'processed_at' => now(),
            ]);

            DB::commit();
            return ['success' => true, 'callback_id' => $callback->id, 'result' => $result];

        } catch (\Throwable $e) {
            DB::rollBack();
            $callback->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('支付回调处理失败', [
                'callback_id' => $callback->id,
                'gateway' => $gateway,
                'event' => $parsed['event_type'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'error' => $e->getMessage(), 'callback_id' => $callback->id];
        }
    }

    /**
     * 处理支付成功回调
     */
    protected function handlePaymentSuccess(PaymentCallback $callback, array $parsed): array
    {
        $orderId = $parsed['order_id'];
        if (!$orderId) {
            // 通过商户订单号查找订单
            $order = Order::where('order_no', $parsed['merchant_order_no'])->first();
            if (!$order) {
throw new \RuntimeException(__("app.payment_callback.order_not_found_with_id", ['id' => $parsed['merchant_order_no']]));
            }
            $orderId = $order->id;
            $callback->update(['order_id' => $orderId]);
        }

        $order = Order::with(['items.sku', 'deliveries', 'customer.user'])->findOrFail($orderId);

        // 订单已支付，忽略
        if ($order->status === Order::STATUS_PAID) {
            return ['order_id' => $order->id, 'order_no' => $order->order_no, 'message' => __('app.common.order_already_paid')];
        }

        // 更新订单状态
        $order->update([
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'transaction_id' => $parsed['transaction_id'],
            'payment_channel' => $callback->gateway,
            'payment_transaction_id' => $parsed['transaction_id'],
            'payment_callback_id' => $callback->id,
            'payment_callback_at' => now(),
        ]);

        // 触发自动发货
        $deliveryResult = $this->deliveryEngine->execute($order);

        return [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'status' => 'paid',
            'delivery' => $deliveryResult,
            'transaction_id' => $parsed['transaction_id'],
        ];
    }

    /**
     * 处理支付失败回调
     */
    protected function handlePaymentFailed(PaymentCallback $callback, array $parsed): array
    {
        $order = $this->resolveOrder($parsed);
        if (!$order) {
            throw new \RuntimeException(__("app.payment_callback.order_not_found"));
        }

        if ($order->status === Order::STATUS_PAID) {
            return ['order_id' => $order->id, 'message' => __('app.common.order_paid_ignore_failure')];
        }

        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'payment_callback_id' => $callback->id,
        ]);

        // 库存回滚（如果有库存管理）
        foreach ($order->items as $item) {
            if ($item->sku) {
                $item->sku->increment('stock', $item->quantity);
            }
        }

        return [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'status' => 'cancelled',
            'reason' => $parsed['failure_reason'] ?? '支付失败',
        ];
    }

    /**
     * 处理退款回调
     */
    protected function handleRefund(PaymentCallback $callback, array $parsed): array
    {
        $order = $this->resolveOrder($parsed);
        if (!$order) {
throw new \RuntimeException(__("app.payment_callback.order_not_found"));
        }

        $isFullRefund = $parsed['amount'] && $parsed['amount'] >= $order->final_amount;

        $order->update([
            'status' => $isFullRefund ? Order::STATUS_REFUNDED : Order::STATUS_PARTIAL_REFUND,
            'payment_callback_id' => $callback->id,
        ]);

        // 自动吊销自动发货的 License
        try {
            foreach ($order->deliveries as $delivery) {
                if ($delivery->status === 'delivered' && $delivery->auto_license_id) {
                    $licenseIds = explode(',', $delivery->auto_license_id);
                    foreach ($licenseIds as $lid) {
                        $license = \App\Models\License::find((int) $lid);
                        if ($license && $license->status === 'active') {
                            $license->update([
                                'status' => 'revoked',
                                'metadata' => array_merge($license->metadata ?? [], [
                                    'revoked_reason' => 'order_refund',
                                    'revoked_at' => now()->toIso8601String(),
                                    'payment_callback_id' => $callback->id,
                                ]),
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('退款吊销License失败', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $isFullRefund ? 'fully_refunded' : 'partial_refund',
            'refund_amount' => $parsed['amount'],
        ];
    }

    /**
     * 处理拒付回调
     */
    protected function handleChargeback(PaymentCallback $callback, array $parsed): array
    {
        $order = $this->resolveOrder($parsed);
        if (!$order) {
throw new \RuntimeException(__("app.payment_callback.order_not_found"));
        }

        $order->update([
            'status' => Order::STATUS_REFUNDING,
            'payment_callback_id' => $callback->id,
        ]);

        // 标记关联License为争议中
        foreach ($order->deliveries as $delivery) {
            if ($delivery->auto_license_id) {
                $licenseIds = explode(',', $delivery->auto_license_id);
                \App\Models\License::whereIn('id', $licenseIds)
                    ->where('status', 'active')
                    ->update(['status' => 'suspended']);
            }
        }

        return [
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'status' => 'disputed',
            'message' => '订单被拒付，License已暂停',
        ];
    }

    /**
     * 解析回调数据
     */
    protected function parsePayload(array $payload, string $gateway): ?array
    {
        return match ($gateway) {
            'stripe' => $this->parseStripe($payload),
            'alipay' => $this->parseAlipay($payload),
            'wechat' => $this->parseWechat($payload),
            'paypal' => $this->parsePaypal($payload),
            'mock' => $this->parseMock($payload),
            default => throw new \RuntimeException(__("app.payment_callback.msg_9c72d03f")),
        };
    }

    protected function parseStripe(array $payload): array
    {
        $type = $payload['type'] ?? '';
        $data = $payload['data']['object'] ?? [];

        return [
            'event_id' => $payload['id'] ?? '',
            'event_type' => match ($type) {
                'payment_intent.succeeded' => 'payment_success',
                'payment_intent.payment_failed' => 'payment_failed',
                'charge.refunded' => 'refund',
                'charge.dispute.created' => 'chargeback',
                default => $type,
            },
            'transaction_id' => $data['id'] ?? $data['payment_intent'] ?? '',
            'merchant_order_no' => $data['metadata']['order_no'] ?? $data['metadata']['order_id'] ?? '',
            'order_id' => isset($data['metadata']['order_id']) ? (int) $data['metadata']['order_id'] : null,
            'amount' => isset($data['amount']) ? $data['amount'] / 100 : null,
            'currency' => strtoupper($data['currency'] ?? 'usd'),
            'failure_reason' => $data['failure_message'] ?? $data['last_payment_error']['message'] ?? '',
        ];
    }

    protected function parseAlipay(array $payload): array
    {
        return [
            'event_id' => $payload['trade_no'] ?? $payload['notify_id'] ?? uniqid('alipay_'),
            'event_type' => match ($payload['trade_status'] ?? '') {
                'TRADE_SUCCESS', 'TRADE_FINISHED' => 'payment_success',
                'TRADE_CLOSED' => 'payment_failed',
                'WAIT_BUYER_PAY' => 'payment_pending',
                default => $payload['trade_status'] ?? 'unknown',
            },
            'transaction_id' => $payload['trade_no'] ?? '',
            'merchant_order_no' => $payload['out_trade_no'] ?? '',
            'order_id' => null,
            'amount' => isset($payload['total_amount']) ? (float) $payload['total_amount'] : null,
            'currency' => 'CNY',
            'failure_reason' => $payload['close_reason'] ?? '',
        ];
    }

    protected function parseWechat(array $payload): array
    {
        return [
            'event_id' => $payload['transaction_id'] ?? $payload['out_trade_no'] ?? uniqid('wx_'),
            'event_type' => match ($payload['result_code'] ?? '') {
                'SUCCESS' => 'payment_success',
                'FAIL' => 'payment_failed',
                'REFUND' => 'refund',
                default => $payload['result_code'] ?? 'unknown',
            },
            'transaction_id' => $payload['transaction_id'] ?? '',
            'merchant_order_no' => $payload['out_trade_no'] ?? '',
            'order_id' => null,
            'amount' => isset($payload['total_fee']) ? $payload['total_fee'] / 100 : null,
            'currency' => 'CNY',
            'failure_reason' => $payload['err_code_des'] ?? '',
        ];
    }

    protected function parsePaypal(array $payload): array
    {
        $resource = $payload['resource'] ?? [];

        return [
            'event_id' => $payload['id'] ?? $resource['id'] ?? uniqid('pp_'),
            'event_type' => match ($payload['event_type'] ?? '') {
                'CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED' => 'payment_success',
                'CHECKOUT.ORDER.DECLINED' => 'payment_failed',
                'PAYMENT.CAPTURE.REFUNDED' => 'refund',
                'PAYMENT.CAPTURE.DENIED' => 'chargeback',
                default => $payload['event_type'] ?? 'unknown',
            },
            'transaction_id' => $resource['id'] ?? $resource['capture_id'] ?? '',
            'merchant_order_no' => $resource['invoice_id'] ?? $resource['custom_id'] ?? '',
            'order_id' => null,
            'amount' => isset($resource['amount']['value']) ? (float) $resource['amount']['value'] : null,
            'currency' => $resource['amount']['currency_code'] ?? 'USD',
            'failure_reason' => $resource['status_details']['reason'] ?? '',
        ];
    }

    protected function parseMock(array $payload): array
    {
        return [
            'event_id' => $payload['event_id'] ?? uniqid('mock_'),
            'event_type' => $payload['event_type'] ?? 'payment_success',
            'transaction_id' => $payload['transaction_id'] ?? 'mock_trans_' . uniqid(),
            'merchant_order_no' => $payload['merchant_order_no'] ?? $payload['order_no'] ?? '',
            'order_id' => $payload['order_id'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? 'CNY',
            'failure_reason' => $payload['failure_reason'] ?? '',
        ];
    }

    /**
     * 幂等性检查
     */
    protected function checkIdempotency(string $gateway, string $eventId): ?PaymentCallback
    {
        $key = "{$gateway}:{$eventId}";
        return PaymentCallback::where('idempotency_key', $key)->first();
    }

    /**
     * 解析订单
     */
    protected function resolveOrder(array $parsed): ?Order
    {
        if (!empty($parsed['order_id'])) {
            return Order::find($parsed['order_id']);
        }
        if (!empty($parsed['merchant_order_no'])) {
            return Order::where('order_no', $parsed['merchant_order_no'])->first();
        }
        return null;
    }

    /**
     * 重试失败的回调
     */
    public function retry(int $callbackId): array
    {
        $callback = PaymentCallback::findOrFail($callbackId);
        if ($callback->status !== 'failed') {
            return ['success' => false, 'message' => __('app.common.only_failed_callback_can_retry')];
        }

        $payload = $callback->raw_payload ?: [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        return $this->handle($payload, $callback->gateway);
    }

    /**
     * 获取统计
     */
    public function getStats(): array
    {
        return [
            'total' => PaymentCallback::count(),
            'completed' => PaymentCallback::where('status', 'completed')->count(),
            'failed' => PaymentCallback::where('status', 'failed')->count(),
            'duplicate' => PaymentCallback::where('status', 'duplicate')->count(),
            'pending' => PaymentCallback::whereIn('status', ['received', 'processing'])->count(),
            'today' => PaymentCallback::whereDate('created_at', today())->count(),
            'by_gateway' => PaymentCallback::selectRaw('gateway, COUNT(*) as cnt')
                ->groupBy('gateway')->pluck('cnt', 'gateway')->toArray(),
            'by_event' => PaymentCallback::selectRaw('event_type, COUNT(*) as cnt')
                ->groupBy('event_type')->pluck('cnt', 'event_type')->toArray(),
        ];
    }

    /**
     * 获取回调列表
     */
    public function getCallbacks(array $filters = []): array
    {
        $query = PaymentCallback::with('order:id,order_no,status,final_amount');
        if (!empty($filters['gateway'])) $query->where('gateway', $filters['gateway']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['event_type'])) $query->where('event_type', $filters['event_type']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('merchant_order_no', 'like', "%{$filters['search']}%")
                  ->orWhere('transaction_id', 'like', "%{$filters['search']}%");
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        return $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->toArray();
    }
}
