<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductSku;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 订单API完整版 (M2-146 🛒)
 *
 * - 订单创建 + 库存扣减 + 优惠券核销
 * - 支付跳转（生成支付链接）
 * - 超时未支付自动取消 + 库存/优惠券回滚
 * - 订单状态机（严格转移校验）
 * - 支付成功处理 + 自动发货
 */
class OrderService
{
    protected DeliveryService $deliveryService;
    protected InventoryService $inventoryService;
    protected PaymentManager $paymentManager;

    public function __construct(
        ?DeliveryService $deliveryService = null,
        ?InventoryService $inventoryService = null,
        ?PaymentManager $paymentManager = null,
    ) {
        $this->deliveryService = $deliveryService ?? app(DeliveryService::class);
        $this->inventoryService = $inventoryService ?? app(InventoryService::class);
        $this->paymentManager = $paymentManager ?? app(PaymentManager::class);
    }

    /**
     * 生成唯一订单号
     */
    public function generateOrderNo(): string
    {
        $prefix = 'HWT' . now()->format('Ymd');
        do {
            $no = $prefix . strtoupper(Str::random(10));
        } while (Order::where('order_no', $no)->exists());

        return $no;
    }

    /**
     * 订单创建（含库存扣减+优惠券核销）
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $orderNo = $this->generateOrderNo();

            $items = [];
            $totalAmount = 0;

            // 遍历商品&扣减库存
            foreach ($data['items'] as $item) {
                $sku = ProductSku::lockForUpdate()->findOrFail($item['sku_id']);

                if (!$sku->is_active) {
                    throw new \RuntimeException("「{$sku->name}」已下架");
                }

                // 库存扣减
                if ($sku->stock !== null && $sku->stock >= 0) {
                    if ($sku->stock < ($item['quantity'] ?? 1)) {
                        throw new \RuntimeException("「{$sku->name}」库存不足: 当前{$sku->stock}");
                    }
                    $deducted = $sku->decrement('stock', $item['quantity'] ?? 1);
                    if (!$deducted) {
                        throw new \RuntimeException("「{$sku->name}」扣减失败");
                    }
                }
                $sku->increment('sold_count', $item['quantity'] ?? 1);

                $unitPrice = $item['unit_price'] ?? (float) $sku->price;
                $subtotal = $unitPrice * ($item['quantity'] ?? 1);
                $totalAmount += $subtotal;

                $items[] = [
                    'product_id' => $sku->product_id,
                    'sku_id' => $sku->id,
                    'item_type' => $item['item_type'] ?? $this->detectItemType($sku),
                    'name' => $sku->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'] ?? 1,
                    'subtotal' => $subtotal,
                    'discount' => 0,
                ];
            }

            // 优惠券核销
            $couponDiscount = 0;
            $couponInfo = null;
            if (!empty($data['coupon_code']) || !empty($data['coupon_info'])) {
                $code = $data['coupon_code'] ?? ($data['coupon_info']['code'] ?? null);
                if ($code) {
                    $result = $this->redeemCoupon($code, $totalAmount, $data);
                    $couponDiscount = $result['discount'];
                    $couponInfo = $result['info'];
                }
            }

            $discountAmount = ($data['discount_amount'] ?? 0) + $couponDiscount;
            $finalAmount = max(0, $totalAmount - $discountAmount);

            // 创建订单
            $order = Order::create([
                'order_no' => $orderNo,
                'tenant_id' => $data['tenant_id'],
                'user_id' => $data['user_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'currency' => $data['currency'] ?? 'CNY',
                'status' => Order::STATUS_PENDING,
                'coupon_info' => $couponInfo,
                'billing_address' => $data['billing_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'expires_at' => now()->addMinutes(30), // 30分钟超时
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            return $order->fresh()->load('items.sku.product');
        });
    }

    /**
     * 支付跳转（生成支付链接/参数）
     */
    public function initiatePayment(Order $order, string $gateway = 'alipay'): array
    {
        if ($order->status !== Order::STATUS_PENDING) {
            throw new \RuntimeException('订单状态不允许支付');
        }

        // 检查订单是否已超时
        if ($order->expires_at && now()->gt($order->expires_at)) {
            $this->cancelTimeout($order);
            throw new \RuntimeException('订单已超时，请重新下单');
        }

        // 免费订单直接标记支付成功
        if ($order->final_amount <= 0) {
            $order = $this->markPaid($order, 'free', 'free_' . $order->order_no);
            return [
                'gateway' => 'free',
                'payment_url' => '',
                'payment_params' => [],
                'transaction_id' => 'free_' . $order->order_no,
                'order_no' => $order->order_no,
                'amount' => 0,
            ];
        }

        $paymentData = [
            'order_no' => $order->order_no,
            'amount' => $order->final_amount,
            'currency' => $order->currency ?? 'CNY',
            'subject' => '互物通 License - ' . $order->order_no,
            'description' => "订单 {$order->order_no} - 商品 " . $order->items->first()?->name ?? '',
            'metadata' => [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'user_id' => $order->user_id,
                'tenant_id' => $order->tenant_id,
            ],
        ];

        // 记录支付方式
        $order->update(['payment_method' => $gateway]);

        // 创建待支付发票
        $invoice = Invoice::create([
            'tenant_id' => $order->tenant_id,
            'customer_id' => $order->customer_id,
            'invoice_no' => 'INV-' . $order->order_no,
            'amount' => $order->final_amount,
            'subtotal' => $order->total_amount,
            'discount_amount' => $order->discount_amount ?? 0,
            'currency' => $order->currency ?? 'CNY',
            'status' => 'pending',
            'metadata' => ['order_id' => $order->id, 'order_no' => $order->order_no],
        ]);

        try {
            $result = $this->paymentManager->charge($invoice, $paymentData);

            // Mock/开发环境：支付成功后自动标记已支付
            if (!empty($result['success']) && !empty($result['transaction_id'])) {
                $tid = $result['transaction_id'];
                $this->markPaid($order, $gateway, $tid, ['invoice_id' => $invoice->id]);
            }

            return [
                'gateway' => $gateway,
                'payment_url' => $result['payment_url'] ?? $result['redirect_url'] ?? '',
                'redirect_url' => $result['redirect_url'] ?? '',
                'payment_form' => $result['payment_form'] ?? '',
                'qr_code' => $result['qr_code'] ?? '',
                'payment_params' => $result['payment_params'] ?? [],
                'transaction_id' => $result['transaction_id'] ?? null,
                'order_no' => $order->order_no,
                'amount' => $order->final_amount,
            ];
        } catch (\Throwable $e) {
            Log::error('支付跳转失败', [
                'order_id' => $order->id,
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('支付初始化失败: ' . $e->getMessage());
        }
    }

    /**
     * 支付成功处理（含库存最终确认+优惠券核销确认+自动发货）
     */
    public function markPaid(Order $order, string $paymentMethod, string $transactionId, array $extra = []): Order
    {
        if ($order->status !== Order::STATUS_PENDING) {
            throw new \RuntimeException('订单状态不允许标记支付');
        }

        return DB::transaction(function () use ($order, $paymentMethod, $transactionId, $extra) {
            $order->update([
                'status' => Order::STATUS_PAID,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'paid_at' => now(),
                'payment_extra' => $extra,
            ]);

            // 确认优惠券使用次数
            if ($order->coupon_info && !empty($order->coupon_info['coupon_id'])) {
                Coupon::where('id', $order->coupon_info['coupon_id'])
                    ->increment('usage_count');
            }

            // 自动创建发货记录
            foreach ($order->items as $item) {
                // 检查 SKU specs 中是否指定了交付类型
                $skuDeliveryType = $item->sku?->specs['delivery_type'] ?? null;
                $deliveryType = match(true) {
                    $skuDeliveryType === 'api_key' => 'api_key',
                    $skuDeliveryType === 'license_key' => 'license_key',
                    $skuDeliveryType === 'service_activation' => 'service_activation',
                    $item->item_type === 'license' || $item->item_type === 'subscription' => 'license_key',
                    default => 'service_activation',
                };

                $order->deliveries()->create([
                    'order_item_id' => $item->id,
                    'delivery_type' => $deliveryType,
                    'delivery_channel' => 'email',
                    'status' => 'pending',
                    'meta' => [
                        'product_id' => $item->product_id,
                        'sku_id' => $item->sku_id,
                        'quantity' => $item->quantity,
                    ],
                ]);
            }

            // 更新/创建对应发票
            $existingInvoice = Invoice::where('metadata->order_id', $order->id)->first();
            if ($existingInvoice) {
                $existingInvoice->update([
                    'status' => 'paid',
                    'paid_at' => $order->paid_at,
                    'payment_method' => $paymentMethod,
                ]);
            } else {
                $this->createInvoiceFromOrder($order);
            }

            // 自动发货
            try {
                $this->deliveryService->processOrderDeliveries($order);
            } catch (\Exception $e) {
                Log::warning('自动发货异常', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $order->fresh();
        });
    }

    /**
     * 取消订单（含库存回滚+优惠券回滚）
     */
    public function cancel(Order $order, ?string $reason = null): Order
    {
        if (!in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PAID])) {
            throw new \RuntimeException('当前状态不允许取消');
        }

        return DB::transaction(function () use ($order, $reason) {
            $oldStatus = $order->status;

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'notes' => $reason ? ($order->notes . "\n取消原因: " . $reason) : $order->notes,
            ]);

            // 回滚库存
            $this->rollbackStock($order);

            // 回滚优惠券核销
            $this->rollbackCoupon($order);

            return $order->fresh();
        });
    }

    /**
     * 超时未支付自动取消
     */
    public function cancelTimeout(Order $order): Order
    {
        if ($order->status !== Order::STATUS_PENDING) {
            return $order;
        }

        return $this->cancel($order, '订单超时未支付');
    }

    /**
     * 批量处理超时订单
     */
    public function cancelExpiredOrders(int $batchSize = 50): int
    {
        $expired = Order::where('status', Order::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->limit($batchSize)
            ->get();

        $count = 0;
        foreach ($expired as $order) {
            try {
                $this->cancelTimeout($order);
                $count++;
            } catch (\Throwable $e) {
                Log::error('超时订单取消失败', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * 回滚库存
     */
    public function rollbackStock(Order $order): void
    {
        foreach ($order->items as $item) {
            try {
                $sku = ProductSku::find($item->sku_id);
                if ($sku) {
                    // 无限库存模式不增不减
                    if ($sku->stock === null || $sku->stock >= 0) {
                        $sku->increment('stock', $item->quantity);
                    }
                    $sku->decrement('sold_count', $item->quantity);
                }
            } catch (\Throwable $e) {
                Log::error('库存回滚失败', [
                    'order_id' => $order->id,
                    'sku_id' => $item->sku_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 回滚优惠券核销（仅待支付订单取消时回滚）
     */
    protected function rollbackCoupon(Order $order): void
    {
        if (!$order->coupon_info || empty($order->coupon_info['coupon_id'])) {
            return;
        }

        try {
            $redemption = CouponRedemption::where('order_id', $order->id)->first();
            if ($redemption) {
                $redemption->delete();
            }

            // 如果订单未支付（待支付状态取消），减少usage_count
            if (!$order->paid_at) {
                $coupon = Coupon::find($order->coupon_info['coupon_id']);
                if ($coupon && $coupon->usage_count > 0) {
                    $coupon->decrement('usage_count');
                }
            }
        } catch (\Throwable $e) {
            Log::error('优惠券回滚失败', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 优惠券核销
     */
    protected function redeemCoupon(string $code, float $totalAmount, array $context): array
    {
        $coupon = Coupon::where('code', $code)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (!$coupon) {
            throw new \RuntimeException('优惠券不存在或已失效');
        }

        // 校验
        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            throw new \RuntimeException('优惠券尚未生效');
        }
        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            throw new \RuntimeException('优惠券已过期');
        }
        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            throw new \RuntimeException('优惠券已用完');
        }
        if ($coupon->minimum_order_amount && $totalAmount < $coupon->minimum_order_amount) {
            throw new \RuntimeException("订单金额需满 {$coupon->minimum_order_amount} 元");
        }

        // 计算折扣
        $discount = match ($coupon->type) {
            'percentage' => round($totalAmount * (float) $coupon->value / 100, 2),
            'fixed' => min((float) $coupon->value, $totalAmount),
            default => 0,
        };

        if ($coupon->maximum_discount) {
            $discount = min($discount, (float) $coupon->maximum_discount);
        }

        // 创建核销记录（暂不增加usage_count，支付成功后才增加）
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $context['customer_id'] ?? null,
            'order_id' => null, // 支付成功后再关联
            'discount_amount' => $discount,
            'currency' => $context['currency'] ?? 'CNY',
            'original_amount' => $totalAmount,
            'final_amount' => $totalAmount - $discount,
            'metadata' => ['coupon_code' => $code],
        ]);

        return [
            'discount' => $discount,
            'info' => [
                'code' => $code,
                'coupon_id' => $coupon->id,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'discount' => $discount,
                'description' => $coupon->description ?? '',
            ],
        ];
    }

    /**
     * 获取订单支付状态
     */
    public function getPaymentStatus(Order $order): array
    {
        return [
            'order_no' => $order->order_no,
            'status' => $order->status,
            'amount' => $order->final_amount,
            'paid_amount' => $order->status === Order::STATUS_PAID ? $order->final_amount : 0,
            'payment_method' => $order->payment_method,
            'transaction_id' => $order->transaction_id,
            'paid_at' => $order->paid_at,
            'expires_at' => $order->expires_at,
            'is_expired' => $order->expires_at && now()->gt($order->expires_at) && $order->status === Order::STATUS_PENDING,
        ];
    }

    /**
     * 获取订单列表
     */
    public function list(array $filters = [], int $perPage = 20)
    {
        $query = Order::with(['items.sku.product', 'user', 'deliveries']);

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('order_no', 'like', "%{$filters['search']}%")
                  ->orWhere('transaction_id', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('id')->paginate(min($perPage, 100));
    }

    /**
     * 获取订单统计
     */
    public function getStats(int $tenantId): array
    {
        $base = Order::where('tenant_id', $tenantId);
        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', Order::STATUS_PENDING)->count(),
            'paid' => (clone $base)->where('status', Order::STATUS_PAID)->count(),
            'cancelled' => (clone $base)->where('status', Order::STATUS_CANCELLED)->count(),
            'refunded' => (clone $base)->whereIn('status', [Order::STATUS_REFUNDED, Order::STATUS_PARTIAL_REFUND])->count(),
            'total_revenue' => (clone $base)->where('status', Order::STATUS_PAID)->sum('final_amount'),
            'today_revenue' => (clone $base)->where('status', Order::STATUS_PAID)
                ->whereDate('paid_at', today())->sum('final_amount'),
            'today_orders' => (clone $base)->whereDate('created_at', today())->count(),
            'avg_order_value' => (clone $base)->where('status', Order::STATUS_PAID)
                ->avg('final_amount') ?? 0,
        ];
    }

    /**
     * 从订单创建发票
     */
    protected function createInvoiceFromOrder(Order $order): Invoice
    {
        return Invoice::create([
            'tenant_id' => $order->tenant_id,
            'customer_id' => $order->customer_id,
            'invoice_no' => 'INV-' . $order->order_no,
            'amount' => $order->final_amount,
            'subtotal' => $order->total_amount,
            'discount_amount' => $order->discount_amount,
            'currency' => $order->currency,
            'status' => 'paid',
            'paid_at' => $order->paid_at,
            'metadata' => ['order_id' => $order->id, 'order_no' => $order->order_no],
        ]);
    }

    /**
     * 根据SKU计费周期检测商品类型
     */
    protected function detectItemType(ProductSku $sku): string
    {
        return match ($sku->billing_cycle) {
            'monthly', 'quarterly', 'yearly' => 'subscription',
            default => 'license',
        };
    }
}
