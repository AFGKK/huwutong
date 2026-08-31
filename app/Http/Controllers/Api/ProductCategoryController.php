<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductCategory::with('children:id,name,parent_id,sort_order');

        // 筛选租户
        $tenantId = $request->input('tenant_id');
        if ($tenantId) {
            $query->where(fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
        }

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // 是否只返回根分类（树形用）
        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->paginate($request->input('per_page', 50));

        return ApiResponse::success($categories);
    }

    /**
     * 获取全部分类（树形结构，不分页）
     */
    public function tree(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $tree = ProductCategory::tree($tenantId);
        return ApiResponse::success($tree);
    }

    /**
     * 获取分类选项列表（用于下拉选择）
     */
    public function options(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $categories = ProductCategory::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'slug']);

        // 构建带层级前缀的选项
        $options = $this->buildOptions($categories);
        return ApiResponse::success($options);
    }

    /**
     * 公开分类列表（客户门户商品商店用）
     */
    public function publicList(): JsonResponse
    {
        $categories = ProductCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'sort_order']);

        return ApiResponse::success($categories);
    }

    protected function buildOptions($categories, $parentId = null, $prefix = ''): array
    {
        $result = [];
        foreach ($categories as $cat) {
            if ($cat->parent_id === $parentId) {
                $result[] = [
                    'id' => $cat->id,
                    'name' => $prefix . $cat->name,
                    'parent_id' => $cat->parent_id,
                    'slug' => $cat->slug,
                ];
                $children = $this->buildOptions($categories, $cat->id, $prefix . '— ');
                $result = array_merge($result, $children);
            }
        }
        return $result;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:product_categories,slug',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'image_url' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:product_categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:500',
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        $validated['slug'] ??= Str::slug($validated['name']);
        $validated['sort_order'] ??= 0;
        $validated['is_active'] ??= true;

        $category = ProductCategory::create($validated);
        return ApiResponse::success($category->load('children'), 201);
    }

    public function show(ProductCategory $productCategory): JsonResponse
    {
        $productCategory->load(['children' => fn($q) => $q->orderBy('sort_order'), 'parent']);
        $productCategory->loadCount('products');
        return ApiResponse::success($productCategory);
    }

    /**
     * 获取分类下的产品列表
     */
    public function products(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $query = $productCategory->products()
            ->with('category:id,name')
            ->select('id', 'name', 'slug', 'category_id', 'is_active', 'is_sellable', 'base_price', 'sales_count', 'image_url', 'created_at');

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // 筛选
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ApiResponse::paginated($products);
    }

    public function update(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:100|unique:product_categories,slug,' . $productCategory->id,
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'image_url' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:product_categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:500',
        ]);

        // 防止将自己设为父分类
        if (isset($validated['parent_id']) && $validated['parent_id'] == $productCategory->id) {
            return ApiResponse::error(__('app.api.product_category.self_parent'));
        }

        $productCategory->update($validated);
        return ApiResponse::success($productCategory->fresh()->load(['children', 'parent']));
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        if ($productCategory->products()->count() > 0) {
            return ApiResponse::error(__('app.api.product_category.has_products', ['count' => $productCategory->products()->count()]));
        }

        // 子分类上移
        ProductCategory::where('parent_id', $productCategory->id)->update(['parent_id' => $productCategory->parent_id]);

        $productCategory->delete();
        return ApiResponse::success(null, 204);
    }

    /**
     * 批量排序
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:product_categories,id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['orders'] as $item) {
            ProductCategory::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return ApiResponse::success(['message' => __('app.api.product_category.sort_updated')]);
    }

    // ──────────────────────────────────────────────
    // 🆕 批量操作
    // ──────────────────────────────────────────────

    /**
     * 批量启用/停用
     */
    public function batchToggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:product_categories,id',
            'is_active' => 'required|boolean',
        ]);

        $count = ProductCategory::whereIn('id', $validated['ids'])
            ->update(['is_active' => $validated['is_active']]);

        return ApiResponse::success([
            'message' => __('app.api.product_category.status_toggled', ['action' => $validated['is_active'] ? __('app.api.product_category.enabled') : __('app.api.product_category.disabled'), 'count' => $count]),
            'affected' => $count,
        ]);
    }

    /**
     * 批量删除（事务保护）
     */
    public function batchDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:product_categories,id',
        ]);

        $errors = [];
        $deleted = 0;

        foreach ($validated['ids'] as $id) {
            $category = ProductCategory::find($id);
            if (!$category) continue;

            if ($category->products()->count() > 0) {
                $errors[] = __('app.api.product_category.skip_has_products', ['name' => $category->name, 'count' => $category->products()->count()]);
                continue;
            }

            // 子分类上移到父分类
            ProductCategory::where('parent_id', $category->id)->update(['parent_id' => $category->parent_id]);
            $category->delete();
            $deleted++;
        }

        return ApiResponse::success([
            'message' => __('app.api.product_category.deleted_count', ['deleted' => $deleted]),
            'deleted' => $deleted,
            'errors' => $errors,
        ]);
    }

    // ──────────────────────────────────────────────
    // 🆕 移动分类（拖拽时更新 parent_id + sort_order）
    // ──────────────────────────────────────────────

    /**
     * 移动分类到新父分类下
     */
    public function move(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:product_categories,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // 循环引用检测：不能将分类移到自己的后代下
        if (isset($validated['parent_id'])) {
            $descendantIds = $productCategory->descendantIds();
            if (in_array((int) $validated['parent_id'], $descendantIds)) {
                return ApiResponse::error(__('app.api.product_category.circular_ref'));
            }
        }

        $productCategory->update($validated);
        return ApiResponse::success($productCategory->fresh()->load('parent', 'children'));
    }

    // ──────────────────────────────────────────────
    // 🆕 分类路径/面包屑
    // ──────────────────────────────────────────────

    /**
     * 获取从根到当前分类的路径
     */
    public function path(ProductCategory $productCategory): JsonResponse
    {
        $path = collect();
        $current = $productCategory;

        while ($current) {
            $path->prepend([
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return ApiResponse::success($path->values());
    }

    // ──────────────────────────────────────────────
    // 🆕 分类统计看板
    // ──────────────────────────────────────────────

    /**
     * 分类统计
     */
    public function stats(): JsonResponse
    {
        $total = ProductCategory::count();
        $active = ProductCategory::where('is_active', true)->count();
        $inactive = $total - $active;
        $rootCount = ProductCategory::whereNull('parent_id')->count();
        $maxDepth = $this->calculateMaxDepth();
        $withProducts = ProductCategory::has('products', '>', 0)->count();

        return ApiResponse::success([
            'total_categories' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'root_count' => $rootCount,
            'max_depth' => $maxDepth,
            'categories_with_products' => $withProducts,
        ]);
    }

    protected function calculateMaxDepth(): int
    {
        $max = 0;
        $all = ProductCategory::all(['id', 'parent_id']);
        foreach ($all as $cat) {
            $depth = 0;
            $current = $cat;
            while ($current->parent_id && $depth < 100) {
                $parent = $all->firstWhere('id', $current->parent_id);
                if (!$parent) break;
                $current = $parent;
                $depth++;
            }
            $max = max($max, $depth);
        }
        return $max;
    }

    // ──────────────────────────────────────────────
    // 🆕 分类合并
    // ──────────────────────────────────────────────

    /**
     * 将源分类合并到目标分类
     */
    public function merge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => 'required|exists:product_categories,id',
            'target_id' => 'required|exists:product_categories,id|different:source_id',
        ]);

        $source = ProductCategory::findOrFail($validated['source_id']);
        $target = ProductCategory::findOrFail($validated['target_id']);

        // 循环引用检测
        $descendantIds = $source->descendantIds();
        if (in_array((int) $target->id, $descendantIds)) {
            return ApiResponse::error(__('app.api.product_category.cannot_merge_child'));
        }

        // 移动产品
        $movedProducts = $source->products()->count();
        $source->products()->update(['category_id' => $target->id]);

        // 移动子分类
        ProductCategory::where('parent_id', $source->id)->update(['parent_id' => $target->id]);

        // 删除源分类
        $source->delete();

        return ApiResponse::success([
            'message' => __('app.api.product_category.merged_products', ['count' => $movedProducts, 'target' => $target->name]),
            'moved_products' => $movedProducts,
        ]);
    }

    // ──────────────────────────────────────────────
    // 🆕 导入/导出
    // ──────────────────────────────────────────────

    /**
     * 导出分类为 CSV
     */
    public function export(): \Illuminate\Http\Response
    {
        $categories = ProductCategory::orderBy('sort_order')->orderBy('name')->get();

        $csv = "id,name,slug,description,icon,parent_id,sort_order,is_active\n";
        foreach ($categories as $cat) {
            $csv .= implode(',', [
                $cat->id,
                $this->csvEscape($cat->name),
                $this->csvEscape($cat->slug),
                $this->csvEscape($cat->description),
                $this->csvEscape($cat->icon),
                $cat->parent_id ?? '',
                $cat->sort_order,
                $cat->is_active ? '1' : '0',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="product-categories.csv"',
        ]);
    }

    /**
     * 导入分类
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'csv' => 'required|string',
        ]);

        $lines = explode("\n", $request->input('csv'));
        $header = str_getcsv(array_shift($lines));
        $created = 0;
        $errors = [];

        foreach ($lines as $line) {
            $row = str_getcsv(trim($line));
            if (count($row) < 2) continue;

            $data = array_combine($header, $row);

            try {
                $parentId = $data['parent_id'] ?? null;
                if ($parentId && !ProductCategory::where('id', $parentId)->exists()) {
                    $parentId = null;
                }

                ProductCategory::create([
                    'name' => $data['name'],
                    'slug' => $data['slug'] ?? Str::slug($data['name']),
                    'description' => $data['description'] ?? '',
                    'icon' => $data['icon'] ?? '',
                    'parent_id' => $parentId,
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ]);
                $created++;
            } catch (\Exception $e) {
                $errors[] = "行 '{$data['name']}': {$e->getMessage()}";
            }
        }

        return ApiResponse::success([
            'message' => __('app.api.product_category.imported', ['created' => $created]) . ($errors ? __('app.api.product_category.import_errors', ['errors' => $errors]) : ''),
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    protected function csvEscape(?string $value): string
    {
        if ($value === null || $value === '') return '';
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
