<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductBundle;
use App\Services\BundleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BundleController extends Controller
{
    public function __construct(
        protected BundleService $bundleService,
    ) {}

    // ─── 可选项目列表 ───

    public function availableItems()
    {
        return ApiResponse::success($this->bundleService->getAvailableItems());
    }

    // ─── 管理端CRUD ───

    public function index(Request $request)
    {
        return ApiResponse::success(
            $this->bundleService->listBundles(
                $request->only(['search', 'is_active', 'billing_period', 'per_page']),
            )
        );
    }

    public function show(int $id)
    {
        return ApiResponse::success($this->bundleService->getBundle($id));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'bundle_price' => 'required|numeric|min:0',
            'slug' => 'nullable|string|max:255|unique:product_bundles,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:500',
            'currency' => 'nullable|string|max:10',
            'billing_period' => 'nullable|string|in:monthly,yearly,one_time',
            'stock' => 'nullable|integer|min:0',
            'max_purchase_per_user' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'items' => 'nullable|array',
            'items.*.name' => 'required|string|max:255',
            'items.*.original_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.itemable_type' => 'nullable|string',
            'items.*.itemable_id' => 'nullable|integer',
            'items.*.type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->bundleService->createBundle($request->all()), 201);
    }

    public function update(Request $request, int $id)
    {
        $bundle = ProductBundle::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'bundle_price' => 'sometimes|numeric|min:0',
            'slug' => 'sometimes|string|max:255|unique:product_bundles,slug,' . $id,
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:500',
            'currency' => 'nullable|string|max:10',
            'billing_period' => 'nullable|string|in:monthly,yearly,one_time',
            'stock' => 'nullable|integer|min:0',
            'max_purchase_per_user' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.original_price' => 'required_with:items|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.itemable_type' => 'nullable|string',
            'items.*.itemable_id' => 'nullable|integer',
            'items.*.type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->bundleService->updateBundle($bundle, $request->all()));
    }

    public function destroy(int $id)
    {
        $bundle = ProductBundle::findOrFail($id);
        $this->bundleService->deleteBundle($bundle);
        return ApiResponse::success(['message' => __("app.bundle.msg_5cc23262")]);
    }

    // ─── 公开API ───

    public function published()
    {
        return ApiResponse::success($this->bundleService->getPublishedBundles());
    }

    public function showBySlug(string $slug)
    {
        return ApiResponse::success($this->bundleService->getBundleBySlug($slug));
    }

    // ─── 购买 ───

    public function purchase(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        try {
            $purchase = $this->bundleService->purchase(
                $id,
                $request->input('customer_id'),
                $request->user()->tenant_id,
            );
            return ApiResponse::success($purchase, 201);
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['message' => $e->getMessage()], 422);
        }
    }

    // ─── 购买记录 ───

    public function purchases(Request $request)
    {
        return ApiResponse::success(
            $this->bundleService->getPurchases(
                $request->user()->tenant_id,
                $request->only(['customer_id', 'status', 'date_from', 'date_to', 'per_page']),
            )
        );
    }

    // ─── 统计 ───

    public function stats()
    {
        return ApiResponse::success($this->bundleService->getStats());
    }
}
