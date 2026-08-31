<?php

namespace App\Services;

use App\Models\BundlePurchase;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\PricingPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BundleService
{
    /**
     * 获取商品/方案列表（用于添加到套餐）
     */
    public function getAvailableItems(): array
    {
        // 可捆绑的有 License 产品和 PricingPlan
        $products = Product::where('is_active', true)
            ->select(['id', 'name', DB::raw("'product' as type"), DB::raw('0 as price')])
            ->get()
            ->toArray();

        $plans = PricingPlan::where('is_active', true)
            ->select(['id', 'name', DB::raw("'plan' as type"), 'price_monthly as price'])
            ->get()
            ->toArray();

        return array_merge($products, $plans);
    }

    // ─── CRUD ───

    public function listBundles(array $filters = [], int $perPage = 20)
    {
        $query = ProductBundle::with('items')->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('slug', 'like', "%{$filters['search']}%");
            });
        }
        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active'] === 'true' || $filters['is_active'] === true);
        }
        if (!empty($filters['billing_period'])) {
            $query->where('billing_period', $filters['billing_period']);
        }

        return $query->paginate($perPage);
    }

    public function getBundle(int $id): ProductBundle
    {
        return ProductBundle::with('items')->findOrFail($id);
    }

    public function createBundle(array $data): ProductBundle
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $bundle = ProductBundle::create($data);

            if (!empty($items)) {
                $this->syncItems($bundle, $items);
            }

            // 计算原价总和
            $bundle->load('items');
            $originalPrice = $bundle->items->sum(function ($item) {
                return $item->effective_price;
            });
            $bundle->update(['original_price' => $originalPrice]);

            return $bundle->fresh('items');
        });
    }

    public function updateBundle(ProductBundle $bundle, array $data): ProductBundle
    {
        return DB::transaction(function () use ($bundle, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $bundle->update($data);

            if ($items !== null) {
                $this->syncItems($bundle, $items);
            }

            // 重新计算原价总和
            $bundle->load('items');
            $originalPrice = $bundle->items->sum(function ($item) {
                return $item->effective_price;
            });
            $bundle->update(['original_price' => $originalPrice]);

            return $bundle->fresh('items');
        });
    }

    public function deleteBundle(ProductBundle $bundle): bool
    {
        return DB::transaction(function () use ($bundle) {
            $bundle->items()->delete();
            return $bundle->delete();
        });
    }

    protected function syncItems(ProductBundle $bundle, array $items): void
    {
        // 清除旧项
        $bundle->items()->delete();

        foreach ($items as $i => $item) {
            $bundle->items()->create([
                'itemable_type' => $item['itemable_type'] ?? 'App\\Models\\PricingPlan',
                'itemable_id' => $item['itemable_id'] ?? 0,
                'name' => $item['name'],
                'original_price' => $item['original_price'] ?? 0,
                'discount_percent' => $item['discount_percent'] ?? 0,
                'quantity' => $item['quantity'] ?? 1,
                'type' => $item['type'] ?? 'plan',
                'sort_order' => $i,
            ]);
        }
    }

    // ─── 公开API ───

    public function getPublishedBundles()
    {
        return ProductBundle::with('items')
            ->active()
            ->ordered()
            ->get();
    }

    public function getBundleBySlug(string $slug): ProductBundle
    {
        return ProductBundle::with('items')
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    // ─── 购买 ───

    public function purchase(int $bundleId, int $customerId, int $tenantId): BundlePurchase
    {
        return DB::transaction(function () use ($bundleId, $customerId, $tenantId) {
            $bundle = ProductBundle::with('items')->findOrFail($bundleId);

            if (!$bundle->is_active) {
                throw new \RuntimeException(__("app.bundle.bundle_unavailable"));
            }
            if (!$bundle->hasStock()) {
                throw new \RuntimeException(__("app.bundle.bundle_out_of_stock"));
            }

            // 检查限购
            $purchaseCount = BundlePurchase::where('product_bundle_id', $bundleId)
                ->where('customer_id', $customerId)
                ->where('status', 'completed')
                ->count();
            if ($purchaseCount >= $bundle->max_purchase_per_user) {
                throw new \RuntimeException(__("app.bundle.msg_6d1c3144"));
            }

            // 创建订单
            $orderNo = 'BND-' . date('Ymd') . '-' . strtoupper(Str::random(10));
            $purchase = BundlePurchase::create([
                'product_bundle_id' => $bundleId,
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'order_no' => $orderNo,
                'paid_amount' => $bundle->bundle_price,
                'currency' => $bundle->currency,
                'status' => 'completed',
                'purchased_items' => $bundle->items->toArray(),
                'purchased_at' => now(),
            ]);

            // 扣减库存
            $bundle->decrementStock();

            return $purchase;
        });
    }

    // ─── 查询 ───

    public function getPurchases(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = BundlePurchase::with(['bundle:id,name', 'customer:id,user_id'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    public function getStats(): array
    {
        return [
            'total_bundles' => ProductBundle::count(),
            'active_bundles' => ProductBundle::where('is_active', true)->count(),
            'total_purchases' => BundlePurchase::count(),
            'total_revenue' => BundlePurchase::where('status', 'completed')->sum('paid_amount'),
        ];
    }
}
