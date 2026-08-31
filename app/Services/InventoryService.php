<?php

namespace App\Services;

use App\Models\InventoryLog;
use App\Models\ProductSku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 商品库存管理服务 (M2-150 🛒)
 *
 * - SKU库存初始化
 * - 下单扣减（Redis锁防超卖）
 * - 取消回滚
 * - 库存预警阈值
 * - 库存快照
 * - 无库存/无限库存模式
 */
class InventoryService
{
    const LOCK_PREFIX = 'inventory:lock:';
    const LOCK_TTL = 10; // 秒

    /**
     * 初始化SKU库存
     */
    public function initializeStock(int $skuId, int $quantity, string $remark = ''): ProductSku
    {
        $sku = ProductSku::findOrFail($skuId);

        $before = $sku->stock;
        $sku->update(['stock' => $quantity]);

        $this->log($skuId, 'initial', $quantity - $before, $before, $quantity, 'system', $remark);

        return $sku->fresh();
    }

    /**
     * 下单扣减库存（带Redis分布式锁）
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function deduct(int $skuId, int $quantity, string $orderRef = ''): array
    {
        $sku = ProductSku::findOrFail($skuId);

        // 无限库存模式
        if ($sku->stock === null || $sku->stock < 0) {
            $sku->increment('sold_count', $quantity);
            return ['success' => true, 'message' => __('app.common.unlimited_stock_mode')];
        }

        // Redis 分布式锁
        $lockKey = self::LOCK_PREFIX . $skuId;
        $lock = Cache::lock($lockKey, self::LOCK_TTL);

        try {
            if (!$lock->get()) {
                return ['success' => false, 'message' => __('app.common.stock_operation_busy')];
            }

            $freshSku = $sku->fresh();
            if ($freshSku->stock < $quantity) {
                return ['success' => false, 'message' => __('app.common.insufficient_stock', ['stock' => $freshSku->stock, 'quantity' => $quantity])];
            }

            DB::transaction(function () use ($freshSku, $skuId, $quantity, $orderRef) {
                $before = $freshSku->stock;
                $after = $before - $quantity;

                $freshSku->update(['stock' => $after]);
                $freshSku->increment('sold_count', $quantity);

                $this->log($skuId, 'deduct', -$quantity, $before, $after, $orderRef);
            });

            return ['success' => true, 'message' => __('app.common.deduction_success')];
        } catch (\Throwable $e) {
            Log::error('库存扣减异常', ['sku_id' => $skuId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => __('app.common.stock_deduction_exception', ['message' => $e->getMessage()])];
        } finally {
            $lock?->release();
        }
    }

    /**
     * 批量扣减库存
     */
    public function batchDeduct(array $items, string $orderRef = ''): array
    {
        $results = [];
        foreach ($items as $item) {
            $skuId = $item['sku_id'] ?? $item['id'];
            $qty = $item['quantity'] ?? 1;
            $results[] = [
                'sku_id' => $skuId,
                'result' => $this->deduct($skuId, $qty, $orderRef),
            ];
        }
        return $results;
    }

    /**
     * 取消订单回滚库存
     */
    public function rollback(int $skuId, int $quantity, string $orderRef = ''): array
    {
        $sku = ProductSku::findOrFail($skuId);

        DB::transaction(function () use ($sku, $skuId, $quantity, $orderRef) {
            $before = $sku->stock;
            $after = $before + $quantity;

            $sku->update(['stock' => $after]);
            $sku->decrement('sold_count', $quantity);

            $this->log($skuId, 'rollback', $quantity, $before, $after, $orderRef);
        });

        return ['success' => true, 'message' => __('app.common.rollback_success')];
    }

    /**
     * 批量回滚
     */
    public function batchRollback(array $items, string $orderRef = ''): array
    {
        $results = [];
        foreach ($items as $item) {
            $skuId = $item['sku_id'] ?? $item['id'];
            $qty = $item['quantity'] ?? 1;
            $results[] = [
                'sku_id' => $skuId,
                'result' => $this->rollback($skuId, $qty, $orderRef),
            ];
        }
        return $results;
    }

    /**
     * 预占库存（下单前锁定）
     */
    public function reserve(int $skuId, int $quantity, string $reserveRef = ''): array
    {
        return $this->deduct($skuId, $quantity, $reserveRef);
    }

    /**
     * 释放预占库存
     */
    public function unreserve(int $skuId, int $quantity, string $reserveRef = ''): array
    {
        return $this->rollback($skuId, $quantity, $reserveRef);
    }

    /**
     * 手动调整库存
     */
    public function adjust(int $skuId, int $delta, string $remark = '', ?int $operatorId = null): ProductSku
    {
        $sku = ProductSku::findOrFail($skuId);
        $before = $sku->stock;
        $after = max(0, $before + $delta);

        $sku->update(['stock' => $after]);
        $this->log($skuId, 'adjust', $delta, $before, $after, 'manual', $remark, $operatorId);

        return $sku->fresh();
    }

    /**
     * 获取库存预警列表
     */
    public function getAlerts(int $threshold = 10): array
    {
        return ProductSku::with('product:id,name')
            ->where('stock', '<=', $threshold)
            ->where('is_active', true)
            ->orderBy('stock')
            ->get()
            ->toArray();
    }

    /**
     * 获取库存快照
     */
    public function getSnapshot(?int $skuId = null): array
    {
        $query = ProductSku::with('product:id,name');
        if ($skuId) {
            $query->where('id', $skuId);
        }
        return $query->orderBy('product_id')->get()->toArray();
    }

    /**
     * 获取库存变更日志
     */
    public function getLogs(int $skuId, int $limit = 50): array
    {
        return InventoryLog::where('sku_id', $skuId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 记录库存变更
     */
    protected function log(int $skuId, string $type, int $quantity, ?int $before, ?int $after, string $ref = '', string $remark = '', ?int $operatorId = null): void
    {
        InventoryLog::create([
            'sku_id' => $skuId,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_type' => $ref ? (str_contains($ref, 'order') ? 'order' : 'adjustment') : null,
            'reference_id' => $ref ?: null,
            'remark' => $remark,
            'operator_id' => $operatorId,
        ]);
    }
}
