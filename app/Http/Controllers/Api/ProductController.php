<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 筛选
        if ($request->filled('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        // 排序
        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        $allowedSorts = ['name', 'slug', 'version', 'is_active', 'created_at', 'updated_at'];
        if (in_array($field, $allowedSorts)) {
            $query->orderBy($field, $direction);
        } else {
            $query->latest();
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        $products = $query->paginate($perPage);

        // 附加 License 数量
        $products->loadCount('licenses as licenses_count');

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
            'version' => 'nullable|string|max:50',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($validated);

        return ApiResponse::created($product->load('featureFlags'), '产品创建成功');
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100|unique:products,slug,' . $id,
            'description' => 'nullable|string|max:5000',
            'version' => 'nullable|string|max:50',
            'modules' => 'nullable|array',
            'modules.*' => 'string',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return ApiResponse::success($product->fresh()->load('featureFlags'), '产品更新成功');
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
