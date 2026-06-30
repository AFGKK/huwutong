<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductSku;
use App\Services\MultiCurrencyPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MultiCurrencyPricingController extends Controller
{
    public function __construct(
        protected MultiCurrencyPricingService $pricingService,
    ) {}

    /**
     * 多币种定价概览
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->pricingService->getDashboard());
    }

    /**
     * 获取SKU的多币种定价
     */
    public function skuPrices(int $skuId): JsonResponse
    {
        return ApiResponse::success($this->pricingService->getSkuPriceList($skuId));
    }

    /**
     * 更新SKU的多币种定价
     */
    public function updateSkuPrices(Request $request, int $skuId): JsonResponse
    {
        $sku = ProductSku::findOrFail($skuId);

        $validator = Validator::make($request->all(), [
            'prices' => 'required|array|min:1',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.compare_at_price' => 'nullable|numeric|min:0|gt:prices.*.price',
            'prices.*.cost_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $saved = $this->pricingService->updateSkuPrices(
            $skuId,
            $request->input('prices'),
        );

        return ApiResponse::success($saved, 200, 'SKU多币种定价已更新');
    }

    /**
     * 批量更新多个SKU的多币种定价
     */
    public function batchUpdatePrices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'skus' => 'required|array|min:1',
            'skus.*.sku_id' => 'required_without:skus.*.sku_code|exists:product_skus,id',
            'skus.*.sku_code' => 'required_without:skus.*.sku_id|exists:product_skus,sku_code',
            'skus.*.prices' => 'required|array|min:1',
            'skus.*.prices.*.price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $results = [];
        foreach ($request->input('skus') as $item) {
            $skuId = isset($item['sku_id'])
                ? (int) $item['sku_id']
                : \App\Models\ProductSku::where('sku_code', $item['sku_code'])->value('id');

            if (!$skuId) {
                $results[] = [
                    'sku_id' => $item['sku_id'] ?? null,
                    'sku_code' => $item['sku_code'] ?? null,
                    'status' => 'failed',
                    'error' => 'SKU not found',
                ];
                continue;
            }

            $saved = $this->pricingService->updateSkuPrices(
                $skuId,
                $item['prices'],
            );
            $results[] = [
                'sku_id' => $skuId,
                'sku_code' => $item['sku_code'] ?? null,
                'status' => 'updated',
                'currencies' => $saved->pluck('currency')->toArray(),
            ];
        }

        return ApiResponse::success($results, 200, '批量更新完成');
    }

    /**
     * 获取商品所有SKU的展示价格（前台公开）
     */
    public function productPrices(Request $request, int $productId): JsonResponse
    {
        $currency = $request->input('currency')
            ?? $request->header('X-Currency')
            ?? config('multi-currency-pricing.currencies.default', 'CNY');

        $prices = $this->pricingService->getProductDisplayPrices($productId, strtoupper($currency));

        return ApiResponse::success($prices);
    }

    /**
     * 获取SKU的展示价格（前台公开）
     */
    public function displayPrice(Request $request, int $skuId): JsonResponse
    {
        $currency = $request->input('currency')
            ?? $request->header('X-Currency')
            ?? config('multi-currency-pricing.currencies.default', 'CNY');

        return ApiResponse::success(
            $this->pricingService->getDisplayPrice($skuId, strtoupper($currency))
        );
    }

    /**
     * 获取所有启用了多币种定价的SKU列表（管理端）
     */
    public function enabledSkus(Request $request): JsonResponse
    {
        $query = ProductSku::with('product:id,name')
            ->where('multi_currency_enabled', true);

        if ($request->has('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        $skus = $query->orderBy('product_id')->paginate($request->input('per_page', 20));

        return ApiResponse::success($skus);
    }

    /**
     * 禁用SKU的多币种定价
     */
    public function disableMultiCurrency(int $skuId): JsonResponse
    {
        $sku = ProductSku::findOrFail($skuId);
        $sku->update(['multi_currency_enabled' => false]);

        // 清理缓存
        $this->pricingService->clearCache($skuId);

        return ApiResponse::success(['message' => '多币种定价已禁用']);
    }
}
