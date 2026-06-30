<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductComparison;
use App\Models\ProductComparisonItem;
use App\Models\ProductSpec;
use App\Models\ProductSpecGroup;
use App\Models\ProductSpecValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductComparisonService
{
    // ─── 规格管理 ───

    /**
     * 获取商品的规格结构（分组+规格项+值）
     */
    public function getProductSpecs(int $productId): array
    {
        $product = Product::with([
            'specGroups' => fn($q) => $q->orderBy('sort_order'),
            'specGroups.specs' => fn($q) => $q->orderBy('sort_order'),
            'specValues',
        ])->findOrFail($productId);

        $groups = [];
        foreach ($product->specGroups as $group) {
            $specs = [];
            foreach ($group->specs as $spec) {
                $value = $product->specValues->firstWhere('spec_id', $spec->id);
                $specs[] = [
                    'id' => $spec->id,
                    'label' => $spec->label,
                    'type' => $spec->type,
                    'unit' => $spec->unit,
                    'options' => $spec->options,
                    'value' => $value?->value,
                    'formatted_value' => $value ? $value->formattedValue() : '-',
                ];
            }
            $groups[] = [
                'id' => $group->id,
                'name' => $group->name,
                'specs' => $specs,
            ];
        }

        return $groups;
    }

    /**
     * 创建规格分组
     */
    public function createSpecGroup(int $productId, string $name, int $sortOrder = 0): ProductSpecGroup
    {
        return ProductSpecGroup::create([
            'product_id' => $productId,
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * 更新规格分组
     */
    public function updateSpecGroup(int $groupId, array $data): ProductSpecGroup
    {
        $group = ProductSpecGroup::findOrFail($groupId);
        $group->update($data);
        return $group->fresh();
    }

    /**
     * 删除规格分组
     */
    public function deleteSpecGroup(int $groupId): void
    {
        ProductSpecGroup::findOrFail($groupId)->delete();
    }

    /**
     * 创建规格项
     */
    public function createSpec(int $groupId, array $data): ProductSpec
    {
        return ProductSpec::create([
            'spec_group_id' => $groupId,
            'label' => $data['label'],
            'type' => $data['type'] ?? 'text',
            'unit' => $data['unit'] ?? null,
            'options' => $data['options'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * 更新规格项
     */
    public function updateSpec(int $specId, array $data): ProductSpec
    {
        $spec = ProductSpec::findOrFail($specId);
        $spec->update($data);
        return $spec->fresh();
    }

    /**
     * 删除规格项
     */
    public function deleteSpec(int $specId): void
    {
        ProductSpec::findOrFail($specId)->delete();
    }

    /**
     * 设置商品规格值
     */
    public function setSpecValue(int $productId, int $specId, ?string $value): ProductSpecValue
    {
        return ProductSpecValue::updateOrCreate(
            ['product_id' => $productId, 'spec_id' => $specId],
            ['value' => $value],
        );
    }

    // ─── 商品比较 ───

    /**
     * 比较多个商品的规格
     */
    public function compareProducts(array $productIds): array
    {
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        if ($products->isEmpty()) {
            return [];
        }

        // 收集所有规格项（取所有商品规格的并集，按标签+分组名去重）
        $allSpecs = collect();
        $productSpecValues = [];

        foreach ($products as $product) {
            $groups = $this->getProductSpecs($product->id);
            $productSpecValues[$product->id] = $groups;

            foreach ($groups as $group) {
                foreach ($group['specs'] as $spec) {
                    $dedupKey = $group['name'] . '::' . $spec['label'];
                    if (!$allSpecs->has($dedupKey)) {
                        $allSpecs->put($dedupKey, [
                            'id' => $spec['id'],
                            'label' => $spec['label'],
                            'type' => $spec['type'],
                            'unit' => $spec['unit'],
                            'group_name' => $group['name'],
                            'group_id' => $group['id'],
                        ]);
                    }
                }
            }
        }

        // 按分组构建对比表
        $groups = [];
        $groupedSpecs = $allSpecs->groupBy('group_name');

        foreach ($groupedSpecs as $groupName => $specs) {
            $rows = [];
            foreach ($specs as $spec) {
                $row = [
                    'label' => $spec['label'],
                    'type' => $spec['type'],
                    'unit' => $spec['unit'],
                ];
                foreach ($productIds as $pid) {
                    $value = '-';
                    if (isset($productSpecValues[$pid])) {
                        foreach ($productSpecValues[$pid] as $g) {
                            if ($g['name'] === $spec['group_name']) {
                                foreach ($g['specs'] as $s) {
                                    if ($s['label'] === $spec['label']) {
                                        $value = $s['formatted_value'];
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                    $row['values'][$pid] = $value;
                }
                $rows[] = $row;
            }
            $groups[] = [
                'name' => $groupName,
                'rows' => $rows,
            ];
        }

        return [
            'products' => $products->values()->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug]),
            'groups' => $groups,
        ];
    }

    /**
     * 创建比较列表
     */
    public function createComparison(?int $userId, ?string $sessionId, array $productIds, ?string $name = null): ProductComparison
    {
        return DB::transaction(function () use ($userId, $sessionId, $productIds, $name) {
            $comparison = ProductComparison::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'name' => $name ?? '商品对比',
            ]);

            foreach ($productIds as $i => $productId) {
                ProductComparisonItem::create([
                    'comparison_id' => $comparison->id,
                    'product_id' => $productId,
                    'sort_order' => $i,
                ]);
            }

            return $comparison->load('products');
        });
    }

    /**
     * 获取比较列表
     */
    public function getComparison(int $comparisonId): ?ProductComparison
    {
        return ProductComparison::with('products')->findOrFail($comparisonId);
    }

    /**
     * 获取用户的比较列表
     */
    public function getUserComparisons(int $userId): Collection
    {
        return ProductComparison::with('products')
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    /**
     * 添加商品到比较列表
     */
    public function addToComparison(int $comparisonId, int $productId): ProductComparisonItem
    {
        $maxSort = ProductComparisonItem::where('comparison_id', $comparisonId)
            ->max('sort_order') ?? 0;

        return ProductComparisonItem::firstOrCreate(
            ['comparison_id' => $comparisonId, 'product_id' => $productId],
            ['sort_order' => $maxSort + 1],
        );
    }

    /**
     * 从比较列表移除商品
     */
    public function removeFromComparison(int $comparisonId, int $productId): void
    {
        ProductComparisonItem::where('comparison_id', $comparisonId)
            ->where('product_id', $productId)
            ->delete();
    }

    /**
     * 删除比较列表
     */
    public function deleteComparison(int $comparisonId): void
    {
        ProductComparison::findOrFail($comparisonId)->delete();
    }

    /**
     * 管理端：列出所有规格
     */
    public function listAdminSpecs(array $filters = [])
    {
        $query = ProductSpec::with([
            'group.product:id,name,slug',
            'values' => fn($q) => $q->select(['id', 'spec_id', 'product_id', 'value']),
        ]);

        if (!empty($filters['product_id'])) {
            $query->whereHas('group', fn($q) => $q->where('product_id', (int) $filters['product_id']));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('label', 'like', "%{$search}%");
        }

        $perPage = (int) ($filters['per_page'] ?? 50);
        return $query->orderBy('sort_order')->paginate(min($perPage, 100));
    }
}
