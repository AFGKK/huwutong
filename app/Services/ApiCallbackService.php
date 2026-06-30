<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * API 回调送达服务 (M2-147 🛒)
 *
 * 支付成功后，向客户注册的回调 URL 推送交付数据。
 * 支持 HMAC 签名验证、超时重试、失败日志记录。
 */
class ApiCallbackService
{
    /**
     * 向客户系统推送交付回调
     */
    public function sendDeliveryCallback(Order $order, Delivery $delivery): bool
    {
        $callbackUrl = $this->getCallbackUrl($order);
        if (empty($callbackUrl)) {
            return false;
        }

        $payload = $this->buildPayload($order, $delivery);
        $signature = $this->generateSignature($payload, $order->tenant_id);
        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Huwutong-Delivery-Callback/1.0',
                    'X-Hwt-Signature' => $signature,
                    'X-Hwt-Timestamp' => (string) now()->timestamp,
                    'X-Hwt-Event' => 'order.delivered',
                ])
                ->post($callbackUrl, $payload);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $delivery->update([
                    'api_callback_sent' => true,
                    'api_callback_sent_at' => now(),
                ]);

                $this->logCallback($delivery, $order, 'success', $payload, [
                    'status_code' => $response->status(),
                    'body' => $response->body(),
                ], $durationMs);

                return true;
            }

            // 非成功状态码
            $this->logCallback($delivery, $order, 'failed', $payload, [
                'status_code' => $response->status(),
                'body' => $response->body(),
            ], $durationMs);

            Log::warning('API回调返回非成功状态码', [
                'delivery_id' => $delivery->id,
                'order_id' => $order->id,
                'url' => $callbackUrl,
                'status' => $response->status(),
            ]);

            return false;

        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->logCallback($delivery, $order, 'failed', $payload, [
                'error' => $e->getMessage(),
            ], $durationMs);

            Log::error('API回调请求失败', [
                'delivery_id' => $delivery->id,
                'order_id' => $order->id,
                'url' => $callbackUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 获取客户系统的回调 URL
     */
    protected function getCallbackUrl(Order $order): ?string
    {
        // 优先从订单的 meta 中取回调 URL
        $orderMeta = $order->meta ?? [];
        if (!empty($orderMeta['delivery_callback_url'])) {
            return $orderMeta['delivery_callback_url'];
        }

        // 其次从客户记录中取
        $customer = $order->customer;
        if ($customer && !empty($customer->meta['delivery_callback_url'])) {
            return $customer->meta['delivery_callback_url'];
        }

        // 最后从租户配置中取
        $tenant = $order->tenant;
        if ($tenant && !empty($tenant->settings['delivery_callback_url'])) {
            return $tenant->settings['delivery_callback_url'];
        }

        return null;
    }

    /**
     * 构建回调 Payload
     */
    protected function buildPayload(Order $order, Delivery $delivery): array
    {
        $content = json_decode($delivery->content, true) ?: [];

        return [
            'event' => 'order.delivered',
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'status' => $order->status,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'delivery' => [
                'delivery_id' => $delivery->id,
                'delivery_type' => $delivery->delivery_type,
                'delivery_channel' => $delivery->delivery_channel,
                'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                'content' => $content,
            ],
            'customer' => [
                'id' => $order->customer_id,
                'name' => $order->customer?->name,
                'email' => $order->customer?->user?->email,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * 生成 HMAC 签名
     */
    protected function generateSignature(array $payload, int $tenantId): string
    {
        $secret = config('app.callback_secret') ?: config('app.key');
        $data = json_encode($payload) . ':' . $tenantId . ':' . now()->timestamp;
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * 记录回调日志
     */
    protected function logCallback(
        Delivery $delivery,
        Order $order,
        string $status,
        array $payload,
        array $response,
        int $durationMs,
    ): void {
        DeliveryLog::create([
            'delivery_id' => $delivery->id,
            'order_id' => $order->id,
            'channel' => 'api_callback',
            'status' => $status,
            'payload' => json_encode($payload),
            'response' => json_encode($response),
            'duration_ms' => $durationMs,
            'error_message' => $response['error'] ?? ($response['status_code'] ?? null) ? "HTTP {$response['status_code']}" : null,
            'attempt' => DeliveryLog::where('delivery_id', $delivery->id)
                ->where('channel', 'api_callback')->count() + 1,
        ]);
    }
}
