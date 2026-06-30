<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\License;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderConfirmationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryService
{
    /**
     * 处理订单自动发货
     */
    public function processOrderDeliveries(Order $order): void
    {
        $order->load(['items.sku.product', 'deliveries']);

        foreach ($order->items as $item) {
            $delivery = $order->deliveries()
                ->where('order_item_id', $item->id)
                ->first();

            if (!$delivery || $delivery->status !== 'pending') {
                continue;
            }

            try {
                $this->deliverItem($order, $item, $delivery);
            } catch (\Exception $e) {
                Log::error('自动发货失败', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
                $delivery->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        // 发送订单确认邮件
        $this->sendOrderConfirmation($order);
    }

    /**
     * 发送订单确认邮件（含 License Key）
     */
    protected function sendOrderConfirmation(Order $order): void
    {
        // 优先使用订单联系信息中的邮箱
        $email = $order->billing_address['contact']['email'] ?? null;

        // 其次使用用户邮箱
        if (!$email && $order->user) {
            $email = $order->user->email;
        }

        if (!$email) {
            Log::warning('订单确认邮件未发送：缺少收件邮箱', ['order_id' => $order->id]);
            return;
        }

        try {
            $user = $order->user ?? new User(['email' => $email, 'name' => $order->billing_address['contact']['name'] ?? '用户']);
            $user->notify(new OrderConfirmationNotification($order, $email));
            Log::info('订单确认邮件已发送', ['order_id' => $order->id, 'email' => $email]);
        } catch (\Exception $e) {
            Log::error('订单确认邮件发送失败', [
                'order_id' => $order->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 交付单个订单项
     */
    protected function deliverItem(Order $order, $item, Delivery $delivery): void
    {
        $delivery->update(['status' => 'sent', 'sent_at' => now()]);

        match ($delivery->delivery_type) {
            'license_key' => $this->deliverLicense($order, $item, $delivery),
            'service_activation' => $this->deliverActivation($order, $item, $delivery),
            'api_key' => $this->deliverApiKey($order, $item, $delivery),
            default => throw new \RuntimeException("未知交付类型: {$delivery->delivery_type}"),
        };

        $delivery->update(['status' => 'delivered', 'delivered_at' => now()]);
    }

    /**
     * 发放 License Key
     */
    protected function deliverLicense(Order $order, $item, Delivery $delivery): void
    {
        $sku = $item->sku;
        $productId = $sku?->product_id ?? $item->product_id;
        $quantity = $item->quantity;

        for ($i = 0; $i < $quantity; $i++) {
            $license = License::create([
                'tenant_id' => $order->tenant_id,
                'customer_id' => $order->customer_id,
                'product_id' => $productId,
                'license_key' => $this->generateLicenseKey(),
                'status' => 'active',
                'activated_at' => now(),
                'expires_at' => $this->calculateExpiry($sku?->billing_cycle),
                'metadata' => [
                    'source' => 'auto_delivery',
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'sku_id' => $sku?->id,
                ],
            ]);

            // 记录交付内容（含 SKU 交付物）
            $deliveryContent = json_decode($delivery->content ?? '[]', true) ?: [];
            $deliveryEntry = [
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'delivered_at' => now()->toIso8601String(),
            ];

            // 附带 SKU 交付物信息
            if ($sku && !empty($sku->deliverables)) {
                $deliveryEntry['deliverables'] = $sku->deliverables;
            }

            $deliveryContent[] = $deliveryEntry;
            $delivery->update(['content' => json_encode($deliveryContent)]);
        }

        // 更新SKU销量
        if ($sku) {
            $sku->increment('sold_count', $quantity);
        }
    }

    /**
     * 服务激活交付
     */
    protected function deliverActivation(Order $order, $item, Delivery $delivery): void
    {
        // 服务激活类交付 - 创建订阅或激活记录
        $delivery->update([
            'content' => json_encode([
                'activated' => true,
                'activated_at' => now()->toIso8601String(),
                'order_id' => $order->id,
            ]),
        ]);
    }

    /**
     * 生成License Key
     */
    protected function generateLicenseKey(): string
    {
        return 'HWT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 20));
    }

    /**
     * 根据计费周期计算到期时间
     */
    protected function calculateExpiry(?string $billingCycle): ?\Carbon\Carbon
    {
        return match ($billingCycle) {
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'yearly' => now()->addYear(),
            default => null, // one-time 或未设置 = 永久
        };
    }

    /**
     * 外部 API 服务自动交付（如 DeepSeek API）
     *
     * SKU specs 中需配置 delivery_type=api_key
     */
    protected function deliverApiKey(Order $order, $item, Delivery $delivery): void
    {
        $sku = $item->sku;
        $specs = $sku?->specs ?? [];

        $apiName = $specs['api_name'] ?? 'API 服务';
        $apiEndpoint = $specs['api_endpoint'] ?? null;
        $masterApiKey = $specs['master_api_key'] ?? null;

        // 生成唯一的 API 访问凭证
        $accessKey = 'sk-' . \Illuminate\Support\Str::random(32);
        $provisioned = false;
        $provisionResult = null;

        // 如果配置了外部 API 端点，尝试自动注册
        if ($apiEndpoint && $masterApiKey) {
            try {
                $path = $specs['provision_path'] ?? '/v1/api-keys';
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $masterApiKey,
                    'Content-Type' => 'application/json',
                ])->post(rtrim($apiEndpoint, '/') . $path, [
                    'name' => 'order_' . $order->order_no,
                ]);

                if ($response->successful()) {
                    $provisioned = true;
                    $provisionResult = $response->json();
                    if (isset($provisionResult['key'])) {
                        $accessKey = $provisionResult['key'];
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('外部API注册失败', ['error' => $e->getMessage()]);
            }
        }

        $deliveryContent = [
            'api_name' => $apiName,
            'type' => 'api_key',
            'provisioned' => $provisioned,
            'credentials' => [
                'api_key' => $accessKey,
                'base_url' => $specs['api_base_url'] ?? ($apiEndpoint ?: ''),
            ],
            'usage_guide' => [
                'auth_header' => "Authorization: Bearer {$accessKey}",
                'docs_url' => $specs['docs_url'] ?? '',
            ],
            'provisioned_at' => now()->toIso8601String(),
            'order_id' => $order->id,
            'order_no' => $order->order_no,
        ];

        if ($provisionResult) {
            $deliveryContent['provision_response'] = $provisionResult;
        }

        $delivery->update(['content' => json_encode($deliveryContent, JSON_UNESCAPED_UNICODE)]);

        if ($sku) {
            $sku->increment('sold_count', $item->quantity ?? 1);
        }
    }
}
