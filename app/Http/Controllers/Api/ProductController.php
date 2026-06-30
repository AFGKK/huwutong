<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductSpecGroup;
use App\Models\ProductSpecValue;
use App\Models\SeoMetadata;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name')->with('creator:id,name,avatar')->with('skus');

        // Only active products for public listing
        $query->where('is_active', true);

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 筛选
        if ($request->filled('filter.category_id')) {
            $query->where('category_id', $request->input('filter.category_id'));
        }

        // 排序
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        // Clear any default ordering
        $query->reorder();

        match ($field) {
            'price' => $query->orderByRaw('COALESCE((SELECT MIN(price) FROM product_skus WHERE product_id = products.id AND is_active = 1), 999999) ' . ($direction === 'asc' ? 'ASC' : 'DESC')),
            'sold_total' => $query->orderByRaw('COALESCE((SELECT SUM(sold_count) FROM product_skus WHERE product_id = products.id AND is_active = 1), 0) DESC'),
            'name' => $query->orderBy('name', $direction),
            default => $query->latest('products.created_at'),
        };

        $perPage = min((int) $request->input('per_page', 20), 100);

        $products = $query->paginate($perPage);

        // 附加 License 数量
        $products->loadCount('licenses as licenses_count');

        // 附加 SKU 价格范围
        $products->getCollection()->transform(function ($product) {
            if ($product->relationLoaded('skus') && $product->skus->count() > 0) {
                $product->sku_price_min = (float) $product->skus->min('price');
                $product->sku_price_max = (float) $product->skus->max('price');
            } else {
                $product->sku_price_min = $product->base_price ? (float) $product->base_price : null;
                $product->sku_price_max = null;
            }
            unset($product->skus);
            return $product;
        });

        return ApiResponse::paginated($products);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $product = Product::with('featureFlags')
            ->withCount('licenses as licenses_count')
            ->findOrFail($id);

        // 最近 License
        $recentLicenses = License::with('customer.user')
            ->where('product_id', $product->id)
            ->latest()
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'product' => $product,
            'recent_licenses' => $recentLicenses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:products,slug',
            'description' => 'nullable|string|max:5000',
            'long_description' => 'nullable|string',
            'version' => 'nullable|string|max:50',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
            'category_id' => 'nullable|integer|exists:product_categories,id',
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'base_price' => 'nullable|numeric|min:0',
            'sales_count' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'image_url' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'string|max:500',
        ]);

        $product = Product::create(array_merge($validated, ['user_id' => $request->user()->id]));

        return ApiResponse::created($product->load('featureFlags', 'creator:id,name,avatar'), '产品创建成功');
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:products,slug,' . $id,
            'description' => 'nullable|string|max:5000',
            'long_description' => 'nullable|string',
            'version' => 'nullable|string|max:50',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
            'category_id' => 'nullable|integer|exists:product_categories,id',
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'base_price' => 'nullable|numeric|min:0',
            'sales_count' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'image_url' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'string|max:500',
        ]);

        $product->update($validated);

        return ApiResponse::success($product->fresh()->load('featureFlags'), '产品更新成功');
    }

    /**
     * 上传产品图片
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $file = $request->file('file');
        $path = $file->store('products/' . date('Ymd'), 'public');

        if (!$path) {
            return ApiResponse::error('图片上传失败', 500);
        }

        $url = '/storage/' . $path;

        return ApiResponse::success(['url' => $url, 'path' => $path], '上传成功');
    }

    /**
     * 产品统计
     */
    public function stats(): JsonResponse
    {
        $total = Product::count();
        $active = Product::where('is_active', true)->count();
        $totalLicenses = License::count();
        $topProducts = Product::withCount('licenses as licenses_count')
            ->orderByDesc('licenses_count')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'version']);

        return ApiResponse::success([
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'total_licenses' => $totalLicenses,
            'top_products' => $topProducts,
        ]);
    }

    /**
     * 获取产品的 Feature Flags
     */
    public function features(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $features = $product->featureFlags()->orderBy('name')->get();
        $allFeatures = FeatureFlag::orderBy('name')->get();

        return ApiResponse::success([
            'assigned' => $features,
            'available' => $allFeatures,
        ]);
    }

    /**
     * 分配 Feature Flag 到产品
     */
    public function assignFeature(int $id, Request $request): JsonResponse
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'feature_ids' => 'required|array',
            'feature_ids.*' => 'exists:feature_flags,id',
        ]);

        $product->featureFlags()->sync($validated['feature_ids']);

        return ApiResponse::success(
            $product->fresh()->load('featureFlags'),
            'Feature Flags 更新成功'
        );
    }

    /**
     * 批量操作
     */
    public function batchAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:activate,deactivate,delete,set_sellable,set_not_sellable',
            'ids' => 'required|array',
            'ids.*' => 'exists:products,id',
        ]);

        $count = 0;
        $products = Product::whereIn('id', $validated['ids']);

        switch ($validated['action']) {
            case 'activate':
                $count = (clone $products)->update(['is_active' => true]);
                break;
            case 'deactivate':
                $count = (clone $products)->update(['is_active' => false]);
                break;
            case 'delete':
                $count = (clone $products)->delete();
                break;
            case 'set_sellable':
                $count = (clone $products)->update(['is_sellable' => true]);
                break;
            case 'set_not_sellable':
                $count = (clone $products)->update(['is_sellable' => false]);
                break;
        }

        return ApiResponse::success(['affected' => $count], "批量操作完成，影响 {$count} 个产品");
    }

    /**
     * 克隆产品
     */
    public function clone(int $id, Request $request): JsonResponse
    {
        $source = Product::findOrFail($id);
        $clone = $source->replicate();
        $clone->name = $source->name . ' (副本)';
        $clone->slug = $source->slug . '-copy-' . time();
        $clone->sales_count = 0;
        $clone->user_id = $request->user()->id;
        $clone->save();

        return ApiResponse::success($clone->fresh(), '产品已克隆');
    }

    /**
     * 保存规格参数
     */
    public function saveSpecs(int $id, Request $request): JsonResponse
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'groups' => 'required|array',
            'groups.*.name' => 'required|string|max:100',
            'groups.*.values' => 'required|array',
            'groups.*.values.*.name' => 'required|string|max:100',
            'groups.*.values.*.value' => 'nullable|string|max:500',
        ]);

        // 清除旧规格（级联删除 specs + spec_values）
        $product->specGroups()->delete();

        foreach ($validated['groups'] as $g) {
            $group = $product->specGroups()->create(['name' => $g['name']]);
            foreach ($g['values'] as $v) {
                // 创建规格项 (ProductSpec)
                $spec = $group->specs()->create([
                    'label' => $v['name'],
                    'type' => 'text',
                    'unit' => null,
                    'sort_order' => 0,
                ]);
                // 创建规格值 (ProductSpecValue)
                $spec->values()->create([
                    'product_id' => $product->id,
                    'value' => $v['value'] ?? '',
                ]);
            }
        }

        return ApiResponse::success(null, '规格参数已保存');
    }

    /**
     * 获取规格参数
     */
    public function getSpecs(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $groups = $product->specGroups()->with(['specs.values' => function ($q) use ($id) {
            $q->where('product_id', $id);
        }])->orderBy('sort_order')->get();

        // 转成前端需要的格式
        $result = $groups->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'values' => $group->specs->map(function ($spec) {
                    $value = $spec->values->first();
                    return [
                        'id' => $spec->id,
                        'name' => $spec->label,
                        'value' => $value->value ?? '',
                    ];
                }),
            ];
        });

        return ApiResponse::success($result);
    }

    /**
     * 获取 SEO
     */
    public function getSeo(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $seo = SeoMetadata::where('seoable_type', Product::class)
            ->where('seoable_id', $product->id)
            ->first();
        return ApiResponse::success($seo);
    }

    /**
     * 保存 SEO
     */
    public function saveSeo(int $id, Request $request): JsonResponse
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:160',
            'og_description' => 'nullable|string|max:500',
        ]);

        $tenantId = $product->tenant_id ?? auth()->user()->tenant_id;
        $seo = app(SeoService::class)->upsertMetadata($product, $tenantId, $validated);

        return ApiResponse::success($seo, 'SEO 已保存');
    }

    /**
     * 保存多语言翻译
     */
    public function saveTranslations(int $id, Request $request): JsonResponse
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'translations' => 'required|array',
            'translations.*.locale' => 'required|string|size:2|in:en,zh,ja,zh-TW',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string|max:5000',
        ]);

        // Use HasProductTranslations trait
        foreach ($validated['translations'] as $t) {
            $product->translations()->updateOrCreate(
                ['locale' => $t['locale']],
                ['name' => $t['name'], 'description' => $t['description'] ?? '']
            );
        }

        return ApiResponse::success(null, '翻译已保存');
    }

    /**
     * 产品的 License 列表
     */
    public function licenses(int $id, Request $request): JsonResponse
    {
        $product = Product::findOrFail($id);
        $perPage = min((int) $request->input('per_page', 20), 100);

        $licenses = License::with('customer.user')
            ->where('product_id', $product->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::paginated($licenses);
    }
}
