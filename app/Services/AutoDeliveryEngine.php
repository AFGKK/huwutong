<?php

namespace App\Services;

use App\Mail\OrderDeliveryMail;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\License;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 自动发货引擎 (M2-142 🛒)
 *
 * 支付成功后全自动3秒内完成：
 * 1. 创建 License
 * 2. 记录交付内容
 * 3. 发送邮件通知
 * 4. 推送 Webhook 事件
 */
class AutoDeliveryEngine
{
    public function __construct(
        protected DeliveryService $deliveryService,
        protected WebhookService $webhookService,
        protected ApiCallbackService $apiCallbackService,
    ) {}

    /**
     * 执行订单自动发货（入口方法）
     */
    public function execute(Order $order): array
    {
        $startTime = microtime(true);
        $results = [];

        DB::beginTransaction();
        try {
            $order->load(['items.sku', 'deliveries', 'customer.user']);

            foreach ($order->items as $item) {
                $delivery = $order->deliveries()
                    ->where('order_item_id', $item->id)
                    ->first();

                if (!$delivery) {
                    $delivery = $this->createPendingDelivery($order, $item);
                }

                if ($delivery->status === 'delivered') {
                    $results[] = ['item_id' => $item->id, 'status' => 'already_delivered'];
                    continue;
                }

                $result = $this->processItem($order, $item, $delivery);
                $results[] = $result;
            }

            DB::commit();

            // 异步推送 Webhook & 邮件（事务外）
            foreach ($results as $r) {
                if (($r['status'] ?? '') === 'delivered' && !empty($r['delivery'])) {
                    $this->sendNotifications($order, $r['delivery'], $r);
                }
            }

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('自动发货失败', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];
        }

        $totalTime = (int) ((microtime(true) - $startTime) * 1000);

        return [
            'success' => true,
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'results' => $results,
            'total_items' => count($results),
            'duration_ms' => $totalTime,
        ];
    }

    /**
     * 处理单个订单项
     */
    protected function processItem(Order $order, $item, Delivery $delivery): array
    {
        $delivery->update(['status' => 'sent', 'sent_at' => now()]);

        return match ($delivery->delivery_type) {
            'license_key' => $this->deliverLicenseKey($order, $item, $delivery),
            'service_activation' => $this->deliverServiceActivation($order, $item, $delivery),
            default => $this->deliverGeneric($order, $item, $delivery),
        };
    }

    /**
     * 交付 License Key
     */
    protected function deliverLicenseKey(Order $order, $item, Delivery $delivery): array
    {
        $sku = $item->sku;
        $quantity = max((int) $item->quantity, 1);
        $licenses = [];
        $productId = $sku?->product_id ?? $item->product_id;

        for ($i = 0; $i < $quantity; $i++) {
            $expiresAt = $this->calculateExpiry($sku?->billing_cycle);
            $licenseKey = $this->generateLicenseKey();

            $license = License::create([
                'tenant_id' => $order->tenant_id,
                'customer_id' => $order->customer_id,
                'product_id' => $productId,
                'sku_id' => $sku?->id,
                'license_key' => $licenseKey,
                'type' => $sku?->item_type ?? 'standard',
                'status' => 'active',
                'activated_at' => now(),
                'expires_at' => $expiresAt,
                'seats' => $sku?->seats ?? 1,
                'metadata' => [
                    'source' => 'auto_delivery',
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'sku_id' => $sku?->id,
                    'sku_name' => $item->name,
                ],
            ]);

            $licenses[] = $license->toArray();
        }

        // 更新交付内容
        $delivery->update([
            'auto_license_id' => implode(',', array_column($licenses, 'id')),
            'status' => 'delivered',
            'delivered_at' => now(),
            'content' => json_encode([
                'licenses' => $licenses,
                'sku_name' => $item->name,
                'quantity' => $quantity,
            ]),
        ]);

        // 更新SKU销量
        if ($sku) {
            $sku->increment('sold_count', $quantity);
        }

        // 记录日志
        $this->logDelivery($delivery, $order, 'auto_license', [
            'licenses_created' => $quantity,
            'license_ids' => array_column($licenses, 'id'),
        ]);

        return [
            'item_id' => $item->id,
            'sku_id' => $sku?->id,
            'status' => 'delivered',
            'delivery_type' => 'license_key',
            'licenses_created' => $quantity,
            'delivery' => $delivery->fresh(),
            'licenses' => $licenses,
        ];
    }

    /**
     * 服务激活交付
     */
    protected function deliverServiceActivation(Order $order, $item, Delivery $delivery): array
    {
        $delivery->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'content' => json_encode([
                'activated' => true,
                'activated_at' => now()->toIso8601String(),
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'service_name' => $item->name,
            ]),
        ]);

        $this->logDelivery($delivery, $order, 'auto_license', [
            'activation' => true,
            'service' => $item->name,
        ]);

        return [
            'item_id' => $item->id,
            'status' => 'delivered',
            'delivery_type' => 'service_activation',
            'delivery' => $delivery->fresh(),
        ];
    }

    /**
     * 通用交付
     */
    protected function deliverGeneric(Order $order, $item, Delivery $delivery): array
    {
        $delivery->update(['status' => 'delivered', 'delivered_at' => now()]);

        $this->logDelivery($delivery, $order, 'auto_license', [
            'type' => $delivery->delivery_type,
            'content' => $delivery->content,
        ]);

        return [
            'item_id' => $item->id,
            'status' => 'delivered',
            'delivery_type' => $delivery->delivery_type,
            'delivery' => $delivery->fresh(),
        ];
    }

    /**
     * 发送通知（Webhook + 邮件 + API 回调）
     */
    protected function sendNotifications(Order $order, Delivery $delivery, array $result): void
    {
        // 推送 Webhook
        try {
            $this->webhookService->dispatchEvent('order.delivered', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'delivery_id' => $delivery->id,
                'delivery_type' => $delivery->delivery_type,
                'status' => 'delivered',
                'items' => $result['licenses'] ?? [],
                'delivered_at' => now()->toIso8601String(),
            ]);

            $delivery->update(['webhook_pushed' => true, 'webhook_pushed_at' => now()]);
            $this->logDelivery($delivery, $order, 'webhook', ['pushed' => true]);
        } catch (\Throwable $e) {
            Log::warning('发货Webhook推送失败', [
                'order_id' => $order->id,
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            $this->logDelivery($delivery, $order, 'webhook', [
                'pushed' => false,
                'error' => $e->getMessage(),
            ]);
        }

        // 发送邮件
        try {
            $customerEmail = $order->customer?->user?->email;
            if ($customerEmail) {
                Mail::to($customerEmail)->send(new OrderDeliveryMail($order, $delivery));
                $delivery->update(['email_sent' => true, 'email_sent_at' => now()]);
                $this->logDelivery($delivery, $order, 'email', ['sent_to' => $customerEmail]);
            }
        } catch (\Throwable $e) {
            Log::warning('发货邮件发送失败', [
                'order_id' => $order->id,
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            $this->logDelivery($delivery, $order, 'email', [
                'sent' => false,
                'error' => $e->getMessage(),
            ]);
        }

        // API 回调
        try {
            $this->apiCallbackService->sendDeliveryCallback($order, $delivery);
        } catch (\Throwable $e) {
            Log::warning('API回调发送失败', [
                'order_id' => $order->id,
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 重试失败的发货
     */
    public function retryDelivery(int $deliveryId): array
    {
        $delivery = Delivery::with('order.items.sku')->findOrFail($deliveryId);

        if ($delivery->status === 'delivered') {
            return ['success' => true, 'message' => '该订单项已交付'];
        }

        $order = $delivery->order;
        $item = $order->items->firstWhere('id', $delivery->order_item_id);

        if (!$item) {
            return ['success' => false, 'error' => '订单项不存在'];
        }

        try {
            return $this->processItem($order, $item, $delivery);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 手动补发
     */
    public function resendDelivery(int $deliveryId, string $channel = 'email'): array
    {
        $delivery = Delivery::with('order.customer.user')->findOrFail($deliveryId);
        $order = $delivery->order;

        if ($channel === 'webhook') {
            try {
                $this->webhookService->dispatchEvent('order.delivered', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'delivery_id' => $delivery->id,
                    'delivery_type' => $delivery->delivery_type,
                    'status' => 'delivered',
                    'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                ]);
                $delivery->update(['webhook_pushed' => true, 'webhook_pushed_at' => now()]);
                return ['success' => true, 'message' => 'Webhook 已重新推送'];
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        if ($channel === 'email') {
            $email = $order->customer?->user?->email;
            if (!$email) return ['success' => false, 'error' => '客户无邮箱'];
            try {
                Mail::to($email)->send(new OrderDeliveryMail($order, $delivery));
                $delivery->update(['email_sent' => true, 'email_sent_at' => now()]);
                return ['success' => true, 'message' => '邮件已重新发送'];
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        if ($channel === 'api_callback') {
            try {
                $sent = $this->apiCallbackService->sendDeliveryCallback($order, $delivery);
                if ($sent) {
                    return ['success' => true, 'message' => 'API 回调已重新推送'];
                }
                return ['success' => false, 'error' => 'API 回调发送失败，请检查回调 URL 配置'];
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return ['success' => false, 'error' => "不支持的通知渠道: {$channel}"];
    }

    /**
     * 创建待处理的交付记录
     */
    protected function createPendingDelivery(Order $order, $item): Delivery
    {
        $sku = $item->sku;
        $deliveryType = $sku?->delivery_type ?? 'license_key';

        return Delivery::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'delivery_type' => $deliveryType,
            'delivery_channel' => 'auto',
            'status' => 'pending',
        ]);
    }

    /**
     * 记录发货日志
     */
    protected function logDelivery(Delivery $delivery, Order $order, string $channel, array $data): void
    {
        DeliveryLog::create([
            'delivery_id' => $delivery->id,
            'order_id' => $order->id,
            'channel' => $channel,
            'status' => !empty($data['error']) ? 'failed' : 'sent',
            'payload' => json_encode($data),
            'error_message' => $data['error'] ?? null,
        ]);
    }

    /**
     * 生成 License Key
     */
    protected function generateLicenseKey(): string
    {
        return 'HWT-' . strtoupper(substr(md5(uniqid(mt_rand(), true) . microtime()), 0, 20));
    }

    /**
     * 计算到期时间
     */
    protected function calculateExpiry(?string $billingCycle): ?\Carbon\Carbon
    {
        return match ($billingCycle) {
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
            'lifetime' => now()->addYears(100),
            default => null,
        };
    }

    /**
     * 获取发货统计
     */
    public function getStats(int $tenantId): array
    {
        $base = Delivery::whereHas('order', fn($q) => $q->where('tenant_id', $tenantId));

        return [
            'total_deliveries' => (clone $base)->count(),
            'delivered' => (clone $base)->where('status', 'delivered')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'today_delivered' => (clone $base)->where('status', 'delivered')
                ->whereDate('delivered_at', today())->count(),
            'webhook_pushed' => (clone $base)->where('webhook_pushed', true)->count(),
            'email_sent' => (clone $base)->where('email_sent', true)->count(),
            'avg_delivery_time_ms' => (int) DeliveryLog::where('channel', 'auto_license')
                ->where('created_at', '>=', now()->subDays(7))
                ->avg('duration_ms') ?: 0,
        ];
    }

    /**
     * 获取发货列表
     */
    public function getDeliveries(int $tenantId, array $filters = []): array
    {
        $query = Delivery::with(['order:id,order_no,status,final_amount,paid_at', 'orderItem:id,name'])
            ->whereHas('order', fn($q) => $q->where('tenant_id', $tenantId));

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['delivery_type'])) {
            $query->where('delivery_type', $filters['delivery_type']);
        }
        if (!empty($filters['search'])) {
            $query->whereHas('order', fn($q) => $q->where('order_no', 'like', "%{$filters['search']}%"));
        }

        $perPage = $filters['per_page'] ?? 20;
        $page = $filters['page'] ?? 1;

        return $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->toArray();
    }
}
