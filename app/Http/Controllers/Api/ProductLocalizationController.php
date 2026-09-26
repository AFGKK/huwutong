<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\PricingPlan;
use App\Models\Product;
use App\Services\ProductLocalizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductLocalizationController extends Controller
{
    /** 无 languages 表数据时的回退白名单 */
    private const FALLBACK_LOCALES = [
        'zh_CN', 'en', 'ja', 'ko', 'fr', 'de', 'es', 'pt', 'ru', 'ar', 'th', 'vi', 'zh-TW',
    ];

    public function __construct(
        protected ProductLocalizationService $localizationService,
    ) {}

    /**
     * 获取支持的语言列表
     */
    public function languages()
    {
        $list = $this->localizationService->getSupportedLanguages();

        if ($list->isEmpty()) {
            $list = collect([
                ['locale' => 'zh_CN', 'name' => '简体中文', 'native_name' => '简体中文', 'flag' => '🇨🇳', 'is_rtl' => false],
                ['locale' => 'en', 'name' => 'English', 'native_name' => 'English', 'flag' => '🇺🇸', 'is_rtl' => false],
                ['locale' => 'ja', 'name' => '日本語', 'native_name' => '日本語', 'flag' => '🇯🇵', 'is_rtl' => false],
            ]);
        }

        return ApiResponse::success($list);
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
            'locale' => $this->localeRules(),
            'translations' => 'required|array',
            'translations.name' => 'nullable|string|max:255',
            'translations.description' => 'nullable|string',
            'translations.features' => 'nullable|string',
            'auto_translated' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(null, $validator->errors()->toArray());
        }

        $this->localizationService->saveTranslations(
            $product,
            $request->input('locale'),
            $request->input('translations'),
            $request->boolean('auto_translated', false),
        );

        return ApiResponse::success(
            $this->localizationService->getTranslations($product, $request->input('locale')),
            __('app.product_localization.translation_saved'),
        );
    }

    public function deleteProductTranslation(Request $request, int $productId)
    {
        $product = Product::findOrFail($productId);

        $validator = Validator::make($request->all(), [
            'locale' => 'required|string|max:20',
            'field' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(null, $validator->errors()->toArray());
        }

        $this->localizationService->deleteTranslation(
            $product,
            $request->input('locale'),
            $request->input('field'),
        );

        return ApiResponse::success(null, __('app.product_localization.deleted'));
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
            'locale' => $this->localeRules(),
            'translations' => 'required|array',
            'translations.name' => 'nullable|string|max:255',
            'translations.description' => 'nullable|string',
            'translations.features' => 'nullable|string',
            'auto_translated' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(null, $validator->errors()->toArray());
        }

        $this->localizationService->saveTranslations(
            $plan,
            $request->input('locale'),
            $request->input('translations'),
            $request->boolean('auto_translated', false),
        );

        return ApiResponse::success(
            $this->localizationService->getTranslations($plan, $request->input('locale')),
            __('app.product_localization.translation_saved'),
        );
    }

    public function deletePlanTranslation(Request $request, int $planId)
    {
        $plan = PricingPlan::findOrFail($planId);

        $validator = Validator::make($request->all(), [
            'locale' => 'required|string|max:20',
            'field' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(null, $validator->errors()->toArray());
        }

        $this->localizationService->deleteTranslation(
            $plan,
            $request->input('locale'),
            $request->input('field'),
        );

        return ApiResponse::success(null, __('app.product_localization.deleted'));
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

    /**
     * @return list<mixed>
     */
    private function localeRules(): array
    {
        $active = Language::query()
            ->where('is_active', true)
            ->pluck('locale')
            ->filter()
            ->values()
            ->all();

        $allowed = $active !== [] ? $active : self::FALLBACK_LOCALES;

        return ['required', 'string', 'max:20', Rule::in($allowed)];
    }
}
