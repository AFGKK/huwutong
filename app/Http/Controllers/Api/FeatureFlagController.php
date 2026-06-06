<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function __construct(
        protected FeatureFlagService $featureFlagService,
    ) {}

    /**
     * 获取所有 Feature Flag 定义
     *
     * GET /api/feature-flags
     */
    public function index(): JsonResponse
    {
        $flags = FeatureFlag::all()->map(fn($f) => [
            'id' => $f->id,
            'key' => $f->key,
            'name' => $f->name,
            'description' => $f->description,
            'is_active' => $f->is_active,
        ]);

        return ApiResponse::success($flags);
    }

    /**
     * 获取产品的 Feature Flag 配置
     *
     * GET /api/products/{product}/features
     */
    public function productFeatures(int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $features = $this->featureFlagService->getProductFeatures($product);

        return ApiResponse::success($features);
    }

    /**
     * 检查 License 是否有某个功能的权限（SDK 调用）
     *
     * POST /api/license/check-feature
     * Body: { license_key, feature }
     */
    public function checkFeature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string',
            'feature' => 'required|string',
        ]);

        $license = License::where('license_key', $data['license_key'])->first();

        if (! $license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', 'License Key 不存在', 404);
        }

        $hasFeature = $this->featureFlagService->hasFeature($license, $data['feature']);

        return ApiResponse::success([
            'license_key' => $license->license_key,
            'feature' => $data['feature'],
            'granted' => $hasFeature,
        ]);
    }

    /**
     * 批量检查 License 功能权限
     *
     * POST /api/license/check-features
     * Body: { license_key, features: ["ai", "api_access", ...] }
     */
    public function checkFeatures(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string',
            'features' => 'required|array',
            'features.*' => 'required|string',
        ]);

        $license = License::where('license_key', $data['license_key'])->first();

        if (! $license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', 'License Key 不存在', 404);
        }

        $results = $this->featureFlagService->checkFeatures($license, $data['features']);

        return ApiResponse::success([
            'license_key' => $license->license_key,
            'features' => $results,
        ]);
    }

    /**
     * 获取 License 的所有可用功能
     *
     * POST /api/license/features
     * Body: { license_key }
     */
    public function licenseFeatures(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string',
        ]);

        $license = License::where('license_key', $data['license_key'])->first();

        if (! $license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', 'License Key 不存在', 404);
        }

        $features = $this->featureFlagService->getLicenseFeatures($license);

        return ApiResponse::success([
            'license_key' => $license->license_key,
            'features' => $features,
        ]);
    }

    // ─── 管理接口（auth:sanctum） ───

    /**
     * 关联功能到产品
     *
     * POST /api/feature-flags/assign
     */
    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'feature_flag_id' => 'required|integer|exists:feature_flags,id',
            'is_active' => 'boolean',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $feature = FeatureFlag::findOrFail($data['feature_flag_id']);

        $this->featureFlagService->assignFeatureToProduct(
            $product,
            $feature,
            $data['is_active'] ?? true,
        );

        return ApiResponse::success(null, '功能已分配到产品');
    }
}
