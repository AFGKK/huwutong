<?php

namespace App\Services;

use App\Models\ProductSku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SKU 商品规格管理服务 (M1.1-24)
 *
 * 管理 product_skus 表的 CRUD、库存、统计。
 */
class ProductSkuService
{
    // ─── 仪表盘 ────────────────────────────────

    /**
     * 获取 SKU 仪表盘数据
     */
    public function getDashboard(): array
    {
        $cacheKey = 'product_sku:dashboard';
        $ttl = 300;

        return Cache::remember($cacheKey, $ttl, function () {
            $totalSkus = ProductSku::count();
            $activeSkus = ProductSku::where('is_active', true)->count();
            $totalStock = ProductSku::where('stock', '>=', 0)->sum('stock');
            $lowStockCount = ProductSku::where('stock', '>=', 0)
                ->where('stock', '<=', 10)
                ->count();
            $totalSold = ProductSku::sum('sold_count');
            $outOfStock = ProductSku::where('stock', 0)->count();

            // 按产品统计
            $productStats = ProductSku::select(
                'product_id',
                DB::raw('count(*) as sku_count'),
                DB::raw('sum(sold_count) as total_sold'),
                DB::raw('sum(CASE WHEN is_active THEN 1 ELSE 0 END) as active_count')
            )
                ->groupBy('product_id')
                ->with('product:id,name')
                ->get()
                ->toArray();

            return [
                'total_skus' => $totalSkus,
                'active_skus' => $activeSkus,
                'total_stock' => $totalStock,
                'low_stock_count' => $lowStockCount,
                'total_sold' => $totalSold,
                'out_of_stock' => $outOfStock,
                'product_stats' => $productStats,
            ];
        });
    }

    /**
     * 清除仪表盘缓存
     */
    public function clearDashboardCache(): void
    {
        Cache::forget('product_sku:dashboard');
    }

    // ─── SKU CRUD ────────────────────────────────

    /**
     * 分页查询 SKU 列表
     */
    public function getSkus(array $params = []): array
    {
        $query = ProductSku::with('product')
            ->orderByDesc('created_at');

        if (!empty($params['product_id'])) {
            $query->where('product_id', $params['product_id']);
        }
        if (isset($params['is_active'])) {
            $query->where('is_active', filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($params['billing_cycle'])) {
            $query->where('billing_cycle', $params['billing_cycle']);
        }
        if (!empty($params['search'])) {
            $s = $params['search'];
            $query->where(function ($q) use ($s) {
                $q->where('sku_code', 'like', "%{$s}%")
                  ->orWhere('name', 'like', "%{$s}%");
            });
        }
        if (!empty($params['stock_status'])) {
            match ($params['stock_status']) {
                'low' => $query->where('stock', '>=', 0)->where('stock', '<=', 10),
                'out' => $query->where('stock', 0),
                'unlimited' => $query->where('stock', -1),
                default => null,
            };
        }

        $perPage = min((int) ($params['per_page'] ?? 15), 100);
        $page = (int) ($params['page'] ?? 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取单条 SKU 详情
     */
    public function getSkuDetail(int $id): ?ProductSku
    {
        return ProductSku::with('product')->find($id);
    }

    /**
     * 创建 SKU
     */
    public function createSku(array $data): ProductSku
    {
        $sku = ProductSku::create([
            'product_id' => $data['product_id'],
            'sku_code' => $data['sku_code'] ?? $this->generateSkuCode($data['product_id']),
            'name' => $data['name'],
            'specs' => $data['specs'] ?? null,
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'currency' => $data['currency'] ?? 'CNY',
            'stock' => $data['stock'] ?? -1,
            'is_active' => $data['is_active'] ?? true,
            'billing_cycle' => $data['billing_cycle'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'deliverables' => $data['deliverables'] ?? null,
        ]);

        $this->clearDashboardCache();

        Log::info('SKU创建成功', ['sku_id' => $sku->id, 'sku_code' => $sku->sku_code]);

        return $sku;
    }

    /**
     * 更新 SKU
     */
    public function updateSku(int $id, array $data): ?ProductSku
    {
        $sku = ProductSku::findOrFail($id);

        $sku->update($data);
        $this->clearDashboardCache();

        Log::info('SKU更新成功', ['sku_id' => $sku->id]);

        return $sku->fresh();
    }

    /**
     * 删除 SKU
     */
    public function deleteSku(int $id): bool
    {
        $sku = ProductSku::findOrFail($id);
        $sku->delete();

        $this->clearDashboardCache();

        Log::info('SKU删除成功', ['sku_id' => $id]);

        return true;
    }

    /**
     * 生成 SKU 编码
     */
    protected function generateSkuCode(int $productId): string
    {
        $prefix = 'SKU-' . str_pad($productId, 4, '0', STR_PAD_LEFT) . '-';
        $last = ProductSku::where('sku_code', 'like', "{$prefix}%")
            ->orderByDesc('sku_code')
            ->value('sku_code');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ─── 库存管理 ────────────────────────────────

    /**
     * 批量更新库存
     */
    public function batchUpdateStock(array $items): array
    {
        $results = [];
        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $sku = ProductSku::findOrFail($item['id']);
                $sku->stock = $item['stock'];
                $sku->save();
                $results[] = ['id' => $sku->id, 'sku_code' => $sku->sku_code, 'stock' => $sku->stock];
            }
            DB::commit();
            $this->clearDashboardCache();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    /**
     * 切换 SKU 上下架状态
     */
    public function toggleActive(int $id): ?ProductSku
    {
        $sku = ProductSku::findOrFail($id);
        $sku->is_active = !$sku->is_active;
        $sku->save();

        $this->clearDashboardCache();

        return $sku->fresh();
    }
}
