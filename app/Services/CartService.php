<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\ProductSku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 购物车API完整版 (M2-145 🛒)
 *
 * - 添加/移除/更新数量（含库存实时校验）
 * - 优惠券应用/移除
 * - 购物车合并（匿名Session→登录用户）
 * - 价格计算（快照锁定+变更检测）
 * - 并发控制（Redis锁）
 */
class CartService
{
    const CART_LOCK_PREFIX = 'cart:lock:';
    const LOCK_TTL = 10;

    protected InventoryService $inventory;

    public function __construct(?InventoryService $inventory = null)
    {
        $this->inventory = $inventory ?? app(InventoryService::class);
    }

    /**
     * 获取或创建购物车（按 user_id 或 session_id）
     */
    public function getOrCreateCart(?int $userId = null, ?int $tenantId = null, ?string $sessionId = null): Cart
    {
        if ($userId) {
            return Cart::firstOrCreate(
                ['user_id' => $userId],
                ['tenant_id' => $tenantId, 'user_id' => $userId]
            );
        }

        if ($sessionId) {
            return Cart::firstOrCreate(
                ['session_id' => $sessionId],
                ['tenant_id' => $tenantId, 'session_id' => $sessionId]
            );
        }

        throw new \RuntimeException('需要 user_id 或 session_id');
    }

    /**
     * 添加商品到购物车（含库存实时校验+价格快照）
     */
    public function addItem(Cart $cart, int $skuId, int $quantity = 1, ?array $meta = null): CartItem
    {
        $lockKey = self::CART_LOCK_PREFIX . $cart->id;
        $lock = Cache::lock($lockKey, self::LOCK_TTL);

        try {
            if (!$lock->get()) {
                throw new \RuntimeException('购物车操作繁忙，请重试');
            }

            $sku = ProductSku::with('product')->findOrFail($skuId);

            // 商品上架校验
            if (!$sku->is_active) {
                throw new \RuntimeException("「{$sku->name}」已下架");
            }

            // 库存实时校验
            $this->validateItemStock($sku, $quantity);

            // 价格快照
            $unitPrice = (float) $sku->price;
            $originalPrice = (float) ($sku->compare_at_price ?? $sku->price);
            $subtotal = $unitPrice * $quantity;

            // 如果已存在同SKU，合并数量
            $existing = CartItem::where('cart_id', $cart->id)
                ->where('sku_id', $skuId)
                ->first();

            if ($existing) {
                $newQty = $existing->quantity + $quantity;
                // 合并后重新校验库存
                $this->validateItemStock($sku, $newQty);
                $existing->update([
                    'quantity' => $newQty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice * $newQty,
                    'meta' => $meta,
                ]);
                // 清空已有优惠券（价格变动）
                $this->clearCoupon($cart);
                return $existing->fresh();
            }

            $item = CartItem::create([
                'cart_id' => $cart->id,
                'sku_id' => $skuId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'original_price' => $originalPrice,
                'subtotal' => $subtotal,
                'meta' => $meta,
            ]);

            // 清空已有优惠券（商品变动）
            $this->clearCoupon($cart);

            return $item->load('sku.product');
        } finally {
            $lock?->release();
        }
    }

    /**
     * 更新商品数量
     */
    public function updateQuantity(Cart $cart, int $skuId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($cart, $skuId);
            return;
        }

        $lockKey = self::CART_LOCK_PREFIX . $cart->id;
        $lock = Cache::lock($lockKey, self::LOCK_TTL);

        try {
            if (!$lock->get()) {
                throw new \RuntimeException('购物车操作繁忙，请重试');
            }

            $item = CartItem::where('cart_id', $cart->id)
                ->where('sku_id', $skuId)
                ->firstOrFail();

            $sku = ProductSku::findOrFail($skuId);
            $this->validateItemStock($sku, $quantity);

            $unitPrice = (float) $sku->price;
            $item->update([
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $unitPrice * $quantity,
            ]);

            // 清空已有优惠券（数量变动）
            $this->clearCoupon($cart);
        } finally {
            $lock?->release();
        }
    }

    /**
     * 移出商品
     */
    public function removeItem(Cart $cart, int $skuId): void
    {
        CartItem::where('cart_id', $cart->id)
            ->where('sku_id', $skuId)
            ->delete();

        $this->clearCoupon($cart);
    }

    /**
     * 清空购物车
     */
    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $this->clearCoupon($cart);
    }

    /**
     * 获取购物车详情（含SKU/Product信息+价格计算）
     */
    public function getCartDetails(Cart $cart): Cart
    {
        $cart->load(['items.sku.product']);

        // 刷新价格并标记变更
        $cart->items->each(function ($item) {
            $sku = $item->sku;
            if ($sku) {
                $currentPrice = (float) $sku->price;
                if ($currentPrice !== (float) $item->unit_price) {
                    $item->price_changed = true;
                    $item->current_price = $currentPrice;
                }
            }
        });

        return $cart;
    }

    /**
     * 获取购物车汇总（含完整价格计算）
     */
    public function getCartSummary(Cart $cart): array
    {
        $cart = $this->getCartDetails($cart);
        $items = [];

        foreach ($cart->items as $item) {
            $sku = $item->sku;
            $items[] = [
                'id' => $item->id,
                'sku_id' => $item->sku_id,
                'sku_code' => $sku?->sku_code,
                'product_name' => $sku?->product?->name ?? $sku?->name,
                'image_url' => $sku?->image_url,
                'billing_cycle' => $sku?->billing_cycle,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'original_price' => (float) $item->original_price,
                'subtotal' => (float) $item->subtotal,
                'price_changed' => $item->price_changed ?? false,
                'current_price' => $item->current_price ?? null,
                'specs' => $sku?->specs,
                'stock' => $sku?->stock,
            ];
        }

        return [
            'items' => $items,
            'item_count' => count($items),
            'total_quantity' => $cart->count,
            'subtotal' => $cart->subtotal,
            'coupon_code' => $cart->coupon_code,
            'coupon_discount' => $cart->coupon_discount_amount,
            'final_amount' => $cart->final_amount,
            'price_changed' => collect($items)->contains('price_changed', true),
        ];
    }

    /**
     * 应用优惠券
     */
    public function applyCoupon(Cart $cart, string $code): array
    {
        $cart->load(['items.sku']);

        if ($cart->items->isEmpty()) {
            throw new \RuntimeException('购物车为空，无法使用优惠券');
        }

        $coupon = Coupon::where('code', $code)
            ->where('status', 'active')
            ->first();

        if (!$coupon) {
            throw new \RuntimeException('优惠券不存在或已失效');
        }

        // 校验有效期
        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            throw new \RuntimeException('优惠券尚未生效');
        }
        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            throw new \RuntimeException('优惠券已过期');
        }

        // 校验使用次数
        if ($coupon->usage_limit && $coupon->usage_count >= $coupon->usage_limit) {
            throw new \RuntimeException('优惠券已用完');
        }

        // 校验最低订单金额
        $subtotal = $cart->subtotal;
        if ($coupon->minimum_order_amount && $subtotal < $coupon->minimum_order_amount) {
            throw new \RuntimeException("订单金额需满 {$coupon->minimum_order_amount} 元才能使用");
        }

        // 计算折扣
        $discount = $this->calculateDiscount($coupon, $subtotal);

        // 优惠券上限
        if ($coupon->maximum_discount) {
            $discount = min($discount, (float) $coupon->maximum_discount);
        }

        // 预算检查
        if ($coupon->budget && ($coupon->budget_spent + $discount) > $coupon->budget) {
            throw new \RuntimeException('该优惠券预算已用完');
        }

        $cart->update([
            'coupon_code' => $code,
            'coupon_id' => $coupon->id,
            'coupon_discount' => $discount,
        ]);

        return [
            'code' => $code,
            'discount' => $discount,
            'description' => $coupon->description ?? '',
            'subtotal' => $cart->fresh()->subtotal,
            'final_amount' => $cart->fresh()->final_amount,
        ];
    }

    /**
     * 移除优惠券
     */
    public function removeCoupon(Cart $cart): void
    {
        $this->clearCoupon($cart);
    }

    /**
     * 合并匿名购物车到用户购物车
     */
    public function mergeGuestCart(int $userId, int $tenantId, string $sessionId): Cart
    {
        $guestCart = Cart::where('session_id', $sessionId)
            ->with('items')
            ->first();

        $userCart = $this->getOrCreateCart($userId, $tenantId);

        if (!$guestCart || $guestCart->items->isEmpty()) {
            return $userCart->load('items.sku.product');
        }

        return DB::transaction(function () use ($userCart, $guestCart) {
            foreach ($guestCart->items as $item) {
                try {
                    $this->addItem($userCart, $item->sku_id, $item->quantity, $item->meta);
                } catch (\RuntimeException $e) {
                    Log::warning('购物车合并跳过商品', [
                        'sku_id' => $item->sku_id,
                        'reason' => $e->getMessage(),
                    ]);
                }
            }

            // 清除匿名购物车
            $guestCart->items()->delete();
            $guestCart->delete();

            return $userCart->load('items.sku.product');
        });
    }

    /**
     * 购物车下单前校验（库存+价格一致性）
     */
    public function validateForCheckout(Cart $cart): array
    {
        $cart->load(['items.sku']);
        $errors = [];
        $warnings = [];

        foreach ($cart->items as $item) {
            $sku = $item->sku;

            if (!$sku || !$sku->is_active) {
                $errors[] = "「{$item->sku?->name}」已下架，请移除";
                continue;
            }

            // 库存校验
            if ($sku->stock !== null && $sku->stock >= 0 && $sku->stock < $item->quantity) {
                $errors[] = "「{$sku->name}」库存不足（可购: {$sku->stock}）";
            }

            // 价格变动检测
            $currentPrice = (float) $sku->price;
            if ($currentPrice !== (float) $item->unit_price) {
                $warnings[] = "「{$sku->name}」价格已从 ¥{$item->unit_price} 变更为 ¥{$currentPrice}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * 校验单件商品库存
     */
    protected function validateItemStock(ProductSku $sku, int $quantity): void
    {
        // 无限库存模式（stock=-1 或 null）
        if ($sku->stock === null || $sku->stock < 0) {
            return;
        }

        if ($sku->stock < $quantity) {
            throw new \RuntimeException(
                "「{$sku->name}」库存不足: 当前{$sku->stock}, 需要{$quantity}"
            );
        }
    }

    /**
     * 计算优惠券折扣金额
     */
    protected function calculateDiscount(Coupon $coupon, float $subtotal): float
    {
        return match ($coupon->type) {
            'percentage' => round($subtotal * (float) $coupon->value / 100, 2),
            'fixed' => min((float) $coupon->value, $subtotal),
            default => 0,
        };
    }

    /**
     * 清除购物车优惠券
     */
    protected function clearCoupon(Cart $cart): void
    {
        if ($cart->coupon_code) {
            $cart->update([
                'coupon_code' => null,
                'coupon_id' => null,
                'coupon_discount' => 0,
            ]);
        }
    }
}
