<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\FlashSaleOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 🛒 秒杀/抢购防护服务 (M2-159)
 *
 * 令牌桶限流 + Redis库存预占 + 排队队列 + 防刷机制
 */
class FlashSaleService
{
    /**
     * 创建秒杀活动
     */
    public function createFlashSale(int $tenantId, array $data): FlashSale
    {
        $sale = FlashSale::create([
            'tenant_id' => $tenantId,
            'sku_id' => $data['sku_id'],
            'name' => $data['name'],
            'flash_price' => $data['flash_price'],
            'original_price' => $data['original_price'],
            'stock' => $data['stock'],
            'max_per_user' => $data['max_per_user'] ?? 1,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 'scheduled',
            'preheat_at' => $data['start_time'] ? now()->parse($data['start_time'])->subMinutes(config('flash-sale.flash_sale.preheat_before_minutes', 10)) : null,
        ]);

        // 预热：加载库存至 Redis
        $this->preheatStock($sale);

        return $sale;
    }

    /**
     * 预热库存到 Redis
     */
    public function preheatStock(FlashSale $sale): void
    {
        $prefix = config('flash-sale.cache.stock_key_prefix', 'flash_stock:');
        Cache::put("{$prefix}{$sale->id}", $sale->stock, now()->addHours(2));
    }

    /**
     * 秒杀排队入口
     */
    public function enterQueue(int $flashSaleId, int $customerId, string $deviceFingerprint = null, string $ipAddress = null): array
    {
        $sale = FlashSale::findOrFail($flashSaleId);

        // 检查活动状态
        if ($sale->status !== 'active') {
            return ['success' => false, 'message' => '秒杀未开始或已结束'];
        }

        if (now()->lt($sale->start_time) || now()->gt($sale->end_time)) {
            return ['success' => false, 'message' => '不在秒杀时间段内'];
        }

        // 防刷检查
        $fraudCheck = $this->antiFraudCheck($flashSaleId, $customerId, $deviceFingerprint, $ipAddress);
        if (!$fraudCheck['allowed']) {
            return ['success' => false, 'message' => $fraudCheck['reason']];
        }

        // 令牌桶限流
        $tokenKey = config('flash-sale.cache.token_key_prefix', 'flash_token:') . $flashSaleId;
        $tokens = Cache::get($tokenKey, config('flash-sale.rate_limit.token_bucket_capacity', 100));
        if ($tokens <= 0) {
            return ['success' => false, 'message' => '请求过于频繁，请稍后再试'];
        }
        Cache::decrement($tokenKey);

        // 库存预占
        $stockKey = config('flash-sale.cache.stock_key_prefix', 'flash_stock:') . $flashSaleId;
        $stock = Cache::decrement($stockKey);
        if ($stock === false || $stock < 0) {
            Cache::increment($stockKey);
            return ['success' => false, 'message' => '库存已售罄'];
        }

        // 生成排队令牌
        $token = 'FS_' . strtoupper(Str::random(24));
        $ttl = config('flash-sale.flash_sale.stock_reserve_seconds', 300);

        Cache::put("flash_reserve:{$token}", [
            'flash_sale_id' => $flashSaleId,
            'customer_id' => $customerId,
        ], $ttl);

        // 数据库记录
        $order = FlashSaleOrder::create([
            'flash_sale_id' => $flashSaleId,
            'customer_id' => $customerId,
            'queue_token' => $token,
            'status' => 'reserved',
            'device_fingerprint' => $deviceFingerprint,
            'ip_address' => $ipAddress,
            'reserved_at' => now(),
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return [
            'success' => true,
            'queue_token' => $token,
            'expires_in' => $ttl,
            'message' => '排队成功，请在有效期内完成支付',
        ];
    }

    /**
     * 确认支付
     */
    public function confirmPayment(string $queueToken, int $orderId): bool
    {
        return DB::transaction(function () use ($queueToken, $orderId) {
            $record = FlashSaleOrder::where('queue_token', $queueToken)
                ->where('status', 'reserved')
                ->lockForUpdate()
                ->first();

            if (!$record) return false;

            $record->update([
                'status' => 'paid',
                'order_id' => $orderId,
                'paid_at' => now(),
            ]);

            Cache::forget("flash_reserve:{$queueToken}");
            return true;
        });
    }

    /**
     * 释放过期预占
     */
    public function releaseExpiredReservations(int $flashSaleId): int
    {
        $expired = FlashSaleOrder::where('flash_sale_id', $flashSaleId)
            ->where('status', 'reserved')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        if ($expired > 0) {
            $stockKey = config('flash-sale.cache.stock_key_prefix', 'flash_stock:') . $flashSaleId;
            Cache::increment($stockKey, $expired);
        }

        return $expired;
    }

    /**
     * 防刷检查
     */
    protected function antiFraudCheck(int $flashSaleId, int $customerId, ?string $deviceFingerprint, ?string $ipAddress): array
    {
        // 同用户购买数量限制
        $userCount = FlashSaleOrder::where('flash_sale_id', $flashSaleId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['reserved', 'paid'])
            ->count();

        $maxPerUser = FlashSale::find($flashSaleId)?->max_per_user ?? 1;
        if ($userCount >= $maxPerUser) {
            return ['allowed' => false, 'reason' => '已达到最大购买数量'];
        }

        // 同设备限制
        if ($deviceFingerprint && config('flash-sale.anti_fraud.check_same_device', true)) {
            $deviceCount = FlashSaleOrder::whereHas('flashSale', fn($q) => $q->where('id', $flashSaleId))
                ->where('device_fingerprint', $deviceFingerprint)
                ->whereIn('status', ['reserved', 'paid'])
                ->count();
            if ($deviceCount >= config('flash-sale.anti_fraud.max_orders_per_device', 2)) {
                return ['allowed' => false, 'reason' => '该设备已达到购买上限'];
            }
        }

        // 同IP限制
        if ($ipAddress && config('flash-sale.anti_fraud.check_same_ip', true)) {
            $ipCount = FlashSaleOrder::whereHas('flashSale', fn($q) => $q->where('id', $flashSaleId))
                ->where('ip_address', $ipAddress)
                ->whereIn('status', ['reserved', 'paid'])
                ->count();
            if ($ipCount >= config('flash-sale.rate_limit.max_orders_per_ip', 5)) {
                return ['allowed' => false, 'reason' => '该IP已达到购买上限'];
            }
        }

        return ['allowed' => true];
    }

    /**
     * 令牌桶定时填充
     */
    public function refillTokenBucket(int $flashSaleId): void
    {
        $key = config('flash-sale.cache.token_key_prefix', 'flash_token:') . $flashSaleId;
        $capacity = config('flash-sale.rate_limit.token_bucket_capacity', 100);
        $refillPerSec = config('flash-sale.rate_limit.token_refill_per_second', 10);

        $current = Cache::get($key, $capacity);
        $new = min($capacity, $current + $refillPerSec);
        Cache::put($key, $new, now()->addHour());
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $total = FlashSale::where('tenant_id', $tenantId)->count();
        $active = FlashSale::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $scheduled = FlashSale::where('tenant_id', $tenantId)->where('status', 'scheduled')->count();
        $ended = FlashSale::where('tenant_id', $tenantId)->where('status', 'ended')->count();

        $totalOrders = FlashSaleOrder::whereHas('flashSale', fn($q) => $q->where('tenant_id', $tenantId))->count();
        $paidOrders = FlashSaleOrder::whereHas('flashSale', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'paid')->count();

        return compact('total', 'active', 'scheduled', 'ended', 'totalOrders', 'paidOrders');
    }
}
