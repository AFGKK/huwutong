<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PricingPlan;
use App\Models\Product;
use App\Services\ProductLocalizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductLocalizationController extends Controller
{
    public function __construct(
        protected ProductLocalizationService $localizationService,
    ) {}

    /**
     * 获取支持的语言列表
     */
    public function languages()
    {
        return ApiResponse::success($this->localizationService->getSupportedLanguages());
    }

    // ─── 商品翻译 ───

    public function productTranslations(int $productId)
    {
        $product = Product::findOrFail($productId);
        return ApiResponse::success($this->localizationService->getTranslations($product));
    }

    public function saveProductTranslations(Request $request, int $productId)
    {
        $product = Product::findOrFail($productId);

        $validator = Validator::make($request->all(), [
            'locale' => 'required|string|size:2|in:zh_CN,en,ja,ko,fr,de,es,pt,ru,ar,th,vi',
            'translations' => 'required|array',
            'translations.name' => 'nullable|string|max:255',
            'translations.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $this->localizationService->saveTranslations(
            $product,
            $request->input('locale'),
            $request->input('translations'),
            $request->boolean('auto_translated', false),
        );

        return ApiResponse::success(['message' => '翻译已保存']);
    }

    public function deleteProductTranslation(Request $request, int $productId)
    {
        $product = Product::findOrFail($productId);

        $validator = Validator::make($request->all(), [
            'locale' => 'required|string',
            'field' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $this->localizationService->deleteTranslation(
            $product,
            $request->input('locale'),
            $request->input('field'),
        );

        return ApiResponse::success(['message' => '已删除']);
    }

    // ─── 方案翻译 ───

    public function planTranslations(int $planId)
    {
        $plan = PricingPlan::findOrFail($planId);
        return ApiResponse::success($this->localizationService->getTranslations($plan));
    }

    public function savePlanTranslations(Request $request, int $planId)
    {
        $plan = PricingPlan::findOrFail($planId);

        $validator = Validator::make($request->all(), [
            'locale' => 'required|string',
            'translations' => 'required|array',
            'translations.name' => 'nullable|string|max:255',
            'translations.description' => 'nullable|string',
            'translations.features' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $this->localizationService->saveTranslations(
            $plan,
            $request->input('locale'),
            $request->input('translations'),
            $request->boolean('auto_translated', false),
        );

        return ApiResponse::success(['message' => '翻译已保存']);
    }

    public function deletePlanTranslation(Request $request, int $planId)
    {
        $plan = PricingPlan::findOrFail($planId);

        $validator = Validator::make($request->all(), [
            'locale' => 'required|string',
            'field' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $this->localizationService->deleteTranslation(
            $plan,
            $request->input('locale'),
            $request->input('field'),
        );

        return ApiResponse::success(['message' => '已删除']);
    }

    // ─── 统计 ───

    public function stats()
    {
        return ApiResponse::success($this->localizationService->getStats());
    }

    // ─── 公开API ───

    public function localizedProduct(int $productId, string $locale)
    {
        $product = Product::with('translations')->findOrFail($productId);
        $localized = $this->localizationService->getLocalized($product, $locale);

        return ApiResponse::success([
            'id' => $product->id,
            'slug' => $product->slug,
            'is_active' => $product->is_active,
            'locale' => $locale,
            'translations' => $localized,
        ]);
    }

    public function localizedPlan(int $planId, string $locale)
    {
        $plan = PricingPlan::with('translations')->findOrFail($planId);
        $localized = $this->localizationService->getLocalized($plan, $locale);

        return ApiResponse::success([
            'id' => $plan->id,
            'slug' => $plan->slug,
            'locale' => $locale,
            'translations' => $localized,
        ]);
    }
}
