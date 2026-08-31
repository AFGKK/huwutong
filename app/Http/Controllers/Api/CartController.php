<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\CartService;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 购物车API完整版 (M2-145 🛒)
 *
 * - 添加/移除/更新数量（含库存实时校验）
 * - 优惠券应用/移除
 * - 购物车合并（匿名→登录）
 * - 价格计算（快照锁定+变更检测）
 * - 下单前校验
 */
class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected OrderService $orderService,
        protected InventoryService $inventoryService,
    ) {}

    /**
     * 查看购物车
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart(
            $request->user()->id,
            $request->user()->tenant_id
        );

        return ApiResponse::success(
            $this->cartService->getCartSummary($cart)
        );
    }

    /**
     * 添加商品（含库存实时校验）
     */
    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
            'quantity' => 'integer|min:1|max:99',
        ]);

        $cart = $this->cartService->getOrCreateCart(
            $request->user()->id,
            $request->user()->tenant_id
        );

        try {
            $item = $this->cartService->addItem($cart, $data['sku_id'], $data['quantity'] ?? 1);
            return ApiResponse::success($item->load('sku.product'), __('app.api.cart.added'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CART_ERROR', $e->getMessage(), 400);
        }
    }

    /**
     * 更新数量
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        try {
            $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
            $this->cartService->updateQuantity($cart, $data['sku_id'], $data['quantity']);
            return ApiResponse::success(
                $this->cartService->getCartSummary($cart->fresh()),
                __('app.api.cart.updated')
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CART_ERROR', $e->getMessage(), 400);
        }
    }

    /**
     * 移除商品
     */
    public function remove(Request $request): JsonResponse
    {
        $data = $request->validate(['sku_id' => 'required|exists:product_skus,id']);

        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $this->cartService->removeItem($cart, $data['sku_id']);
        }

        return ApiResponse::success(null, __('app.api.cart.removed'));
    }

    /**
     * 清空购物车
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $this->cartService->clear($cart);
        }

        return ApiResponse::success(null, __('app.api.cart.cleared'));
    }

    /**
     * 获取购物车汇总（含价格计算）
     */
    public function summary(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if (!$cart) {
            return ApiResponse::success([
                'items' => [], 'item_count' => 0, 'total_quantity' => 0,
                'subtotal' => 0, 'coupon_code' => null,
                'coupon_discount' => 0, 'final_amount' => 0,
                'price_changed' => false,
            ]);
        }

        return ApiResponse::success($this->cartService->getCartSummary($cart));
    }

    /**
     * 应用优惠券
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $cart = Cart::where('user_id', $request->user()->id)->first();
        if (!$cart) {
            return ApiResponse::error('CART_EMPTY', __('app.api.cart.empty'), 400);
        }

        try {
            $result = $this->cartService->applyCoupon($cart, $data['code']);
            return ApiResponse::success($result, __('app.api.cart.coupon_applied'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('COUPON_ERROR', $e->getMessage(), 400);
        }
    }

    /**
     * 移除优惠券
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if ($cart) {
            $this->cartService->removeCoupon($cart);
        }

        return ApiResponse::success(null, __('app.api.cart.coupon_removed'));
    }

    /**
     * 合并匿名购物车到用户购物车（登录后调用）
     */
    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            $cart = $this->cartService->mergeGuestCart(
                $request->user()->id,
                $request->user()->tenant_id,
                $data['session_id']
            );
            return ApiResponse::success(
                $this->cartService->getCartSummary($cart),
                __('app.api.cart.merged')
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('MERGE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 购物车下单前校验
     */
    public function validateCheckout(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        if (!$cart || $cart->items->isEmpty()) {
            return ApiResponse::error('CART_EMPTY', __('app.api.cart.empty'), 400);
        }

        $result = $this->cartService->validateForCheckout($cart);
        if ($result['valid']) {
            return ApiResponse::success($result, __('app.api.cart.validated'));
        }
        return ApiResponse::error('CHECKOUT_VALIDATION_FAILED', __('app.api.cart.validation_failed'), 400, $result);
    }

    /**
     * 购物车→下单（一键结算）
     */
    public function checkout(Request $request): JsonResponse
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return ApiResponse::error('CART_EMPTY', __('app.api.cart.empty'), 400);
        }

        // 下单前校验
        $validation = $this->cartService->validateForCheckout($cart);
        if (!$validation['valid']) {
            return ApiResponse::error('CHECKOUT_VALIDATION_FAILED', __('app.api.cart.validation_failed'), 400, $validation);
        }

        try {
            $orderData = [
                'items' => $cart->items->map(fn($i) => [
                    'sku_id' => $i->sku_id,
                    'quantity' => $i->quantity,
                    'item_type' => $i->sku?->billing_cycle === 'monthly' || $i->sku?->billing_cycle === 'yearly'
                        ? 'subscription' : 'license',
                    'unit_price' => $i->unit_price,
                ])->toArray(),
                'tenant_id' => $request->user()->tenant_id,
                'user_id' => $request->user()->id,
                'customer_id' => $request->user()->customer?->id,
                'currency' => 'CNY',
                'coupon_info' => $cart->coupon_code ? [
                    'code' => $cart->coupon_code,
                    'coupon_id' => $cart->coupon_id,
                    'discount' => $cart->coupon_discount,
                ] : null,
                'discount_amount' => $cart->coupon_discount ?? 0,
            ];

            $order = $this->orderService->createOrder($orderData);

            // 清空购物车
            $this->cartService->clear($cart);

            return ApiResponse::created(
                $order->load('items.sku'),
                __('app.api.cart.order_created_pay')
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CHECKOUT_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 快速购买（一键加购→下单→支付跳转）
     */
    public function quickBuy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku_id' => 'required|exists:product_skus,id',
            'quantity' => 'integer|min:1|max:99',
        ]);

        try {
            // 1. 加购
            $cart = $this->cartService->getOrCreateCart(
                $request->user()->id,
                $request->user()->tenant_id
            );
            $this->cartService->addItem($cart, $data['sku_id'], $data['quantity'] ?? 1);

            // 2. 校验并下单
            $validation = $this->cartService->validateForCheckout($cart->fresh());
            if (!$validation['valid']) {
                $this->cartService->clear($cart);
                return ApiResponse::error('CHECKOUT_VALIDATION_FAILED', __('app.api.cart.validation_failed'), 400, $validation);
            }

            $orderData = [
                'items' => $cart->items->map(fn($i) => [
                    'sku_id' => $i->sku_id,
                    'quantity' => $i->quantity,
                    'item_type' => $i->sku?->billing_cycle === 'monthly' || $i->sku?->billing_cycle === 'yearly'
                        ? 'subscription' : 'license',
                    'unit_price' => $i->unit_price,
                ])->toArray(),
                'tenant_id' => $request->user()->tenant_id,
                'user_id' => $request->user()->id,
                'customer_id' => $request->user()->customer?->id,
                'currency' => 'CNY',
            ];

            $order = $this->orderService->createOrder($orderData);
            $this->cartService->clear($cart);

            return ApiResponse::created([
                'order' => $order->load('items.sku'),
                'payment' => null,
            ], __('app.api.cart.order_created'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error('QUICK_BUY_FAILED', $e->getMessage(), 400);
        }
    }
}
