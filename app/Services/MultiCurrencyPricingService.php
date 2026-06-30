<?php

namespace App\Services;

use App\Models\ProductSku;
use App\Models\SkuCurrencyPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * M3-83 多币种商品定价服务
 *
 * 管理商品SKU各币种定价、自动转换、前台展示、结算币种选择。
 * 对接 CurrencyService (M2-30) 汇率引擎 + PaymentGateway (M2-143) 支付结算。
 */
class MultiCurrencyPricingService
{
    const CACHE_PREFIX = 'mcp:sku:';

    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * 获取SKU在所有支持币种下的定价
     */
    public function getSkuPrices(int $skuId): array
    {
        $sku = ProductSku::findOrFail($skuId);
        $supported = config('multi-currency-pricing.currencies.supported', ['CNY', 'USD', 'EUR']);
        $defaultCurrency = config('multi-currency-pricing.currencies.default', 'CNY');

        $storedPrices = SkuCurrencyPrice::where('product_sku_id', $skuId)
            ->get()
            ->keyBy('currency');

        $result = [];
        foreach ($supported as $currency) {
            if (isset($storedPrices[$currency])) {
                $result[$currency] = [
                    'price' => (float) $storedPrices[$currency]->price,
                    'compare_at_price' => $storedPrices[$currency]->compare_at_price
                        ? (float) $storedPrices[$currency]->compare_at_price : null,
                    'cost_price' => $storedPrices[$currency]->cost_price
                        ? (float) $storedPrices[$currency]->cost_price : null,
                    'is_converted' => $storedPrices[$currency]->is_converted,
                    'source_currency' => $storedPrices[$currency]->source_currency,
                ];
            } elseif ($currency === $defaultCurrency && $sku->currency === $defaultCurrency) {
                // 默认币种回退到 SKU base price
                $result[$currency] = [
                    'price' => (float) $sku->price,
                    'compare_at_price' => $sku->compare_at_price ? (float) $sku->compare_at_price : null,
                    'is_converted' => false,
                    'source_currency' => null,
                ];
            } elseif (config('multi-currency-pricing.conversion.fallback_to_convert', true)) {
                // 自动从基准币种转换
                $basePrice = $storedPrices[$defaultCurrency]->price ?? $sku->price;
                $converted = $this->currencyService->convert(
                    (float) $basePrice,
                    $defaultCurrency,
                    $currency,
                );
                $result[$currency] = [
                    'price' => $converted['amount'],
                    'compare_at_price' => null,
                    'is_converted' => true,
                    'source_currency' => $defaultCurrency,
                    'rate_used' => $converted['rate'],
                ];
            }
        }

        return $result;
    }

    /**
     * 更新SKU在各币种下的定价
     */
    public function updateSkuPrices(int $skuId, array $pricesByCurrency): Collection
    {
        $sku = ProductSku::findOrFail($skuId);
        $supported = config('multi-currency-pricing.currencies.supported', ['CNY', 'USD', 'EUR']);

        return DB::transaction(function () use ($sku, $skuId, $pricesByCurrency, $supported) {
            $saved = collect();

            foreach ($pricesByCurrency as $currency => $data) {
                $currency = strtoupper($currency);
                if (!in_array($currency, $supported)) {
                    continue;
                }

                $priceData = [
                    'price' => $data['price'] ?? 0,
                    'compare_at_price' => $data['compare_at_price'] ?? null,
                    'cost_price' => $data['cost_price'] ?? null,
                    'is_converted' => false,
                    'source_currency' => null,
                ];

                $skuPrice = SkuCurrencyPrice::updateOrCreate(
                    ['product_sku_id' => $skuId, 'currency' => $currency],
                    $priceData,
                );

                $saved->push($skuPrice);
            }

            // 启用多币种标记
            if (!$sku->multi_currency_enabled) {
                $sku->update(['multi_currency_enabled' => true]);
            }

            // 自动转换未指定的币种
            if (config('multi-currency-pricing.conversion.auto_convert_on_save', true)) {
                $this->autoConvertMissingPrices($skuId, $pricesByCurrency);
            }

            // 清除缓存
            $this->clearCache($skuId);

            return $saved;
        });
    }

    /**
     * 自动将已设定的币种价格转换到未设定的币种
     */
    protected function autoConvertMissingPrices(int $skuId, array $existingPrices): void
    {
        $supported = config('multi-currency-pricing.currencies.supported', ['CNY', 'USD', 'EUR']);

        // 找第一个已设定的币种作为基准
        $sourceCurrency = null;
        $sourcePrice = null;
        foreach ($supported as $currency) {
            if (isset($existingPrices[$currency]['price']) && $existingPrices[$currency]['price'] > 0) {
                $sourceCurrency = $currency;
                $sourcePrice = (float) $existingPrices[$currency]['price'];
                break;
            }
        }

        if (!$sourceCurrency || !$sourcePrice) {
            return;
        }

        foreach ($supported as $targetCurrency) {
            if (isset($existingPrices[$targetCurrency]) || $targetCurrency === $sourceCurrency) {
                continue;
            }

            $converted = $this->currencyService->convert(
                $sourcePrice,
                $sourceCurrency,
                $targetCurrency,
            );

            if ($converted['rate'] !== null) {
                SkuCurrencyPrice::updateOrCreate(
                    ['product_sku_id' => $skuId, 'currency' => $targetCurrency],
                    [
                        'price' => $converted['amount'],
                        'is_converted' => true,
                        'source_currency' => $sourceCurrency,
                    ],
                );
            }
        }
    }

    /**
     * 获取客户可见的SKU价格（按首选币种）
     */
    public function getDisplayPrice(int $skuId, ?string $preferredCurrency = null): array
    {
        $cacheKey = self::CACHE_PREFIX . "display:{$skuId}:{$preferredCurrency}";
        $ttl = config('multi-currency-pricing.cache.sku_prices_ttl', 1800);

        return Cache::remember($cacheKey, $ttl, function () use ($skuId, $preferredCurrency) {
            $sku = ProductSku::with('product')->findOrFail($skuId);
            $currency = $preferredCurrency ?? config('multi-currency-pricing.currencies.default', 'CNY');
            $allPrices = $this->getSkuPrices($skuId);

            $displayPrice = $allPrices[$currency] ?? null;

            // 如果首选币种无定价，从默认币种转换
            if (!$displayPrice && $sku->price > 0) {
                $baseCurrency = $sku->currency;
                $basePrice = (float) $sku->price;

                $converted = $this->currencyService->convert($basePrice, $baseCurrency, $currency);
                $displayPrice = [
                    'price' => $converted['amount'],
                    'compare_at_price' => null,
                    'is_converted' => true,
                    'source_currency' => $baseCurrency,
                    'rate_used' => $converted['rate'],
                ];
            }

            return [
                'sku_id' => $skuId,
                'sku_name' => $sku->name,
                'currency' => $currency,
                'price' => $displayPrice['price'] ?? 0,
                'compare_at_price' => $displayPrice['compare_at_price'] ?? null,
                'formatted' => $this->formatPrice($displayPrice['price'] ?? 0, $currency),
                'formatted_compare' => $displayPrice['compare_at_price']
                    ? $this->formatPrice($displayPrice['compare_at_price'], $currency) : null,
                'is_converted' => $displayPrice['is_converted'] ?? false,
                'all_prices' => config('multi-currency-pricing.display.show_all_prices', false) ? $allPrices : null,
            ];
        });
    }

    /**
     * 批量获取商品的显示价格
     */
    public function getProductDisplayPrices(int $productId, ?string $preferredCurrency = null): array
    {
        $skus = ProductSku::where('product_id', $productId)->where('is_active', true)->get();
        $results = [];

        foreach ($skus as $sku) {
            $results[] = $this->getDisplayPrice($sku->id, $preferredCurrency);
        }

        return $results;
    }

    /**
     * 获取SKU定价列表（管理端）
     */
    public function getSkuPriceList(int $skuId): array
    {
        $sku = ProductSku::with('product:id,name')->findOrFail($skuId);
        $prices = $this->getSkuPrices($skuId);

        return [
            'sku' => [
                'id' => $sku->id,
                'sku_code' => $sku->sku_code,
                'name' => $sku->name,
                'base_currency' => $sku->currency,
                'base_price' => (float) $sku->price,
                'multi_currency_enabled' => $sku->multi_currency_enabled,
            ],
            'prices' => $prices,
        ];
    }

    /**
     * 获取管理端概览
     */
    public function getDashboard(): array
    {
        $totalSkus = ProductSku::count();
        $multiCurrencySkus = ProductSku::where('multi_currency_enabled', true)->count();
        $supported = config('multi-currency-pricing.currencies.supported', ['CNY', 'USD', 'EUR']);
        $defaultCurrency = config('multi-currency-pricing.currencies.default', 'CNY');

        // 各币种已定价SKU数
        $currencyStats = [];
        foreach ($supported as $currency) {
            $currencyStats[$currency] = SkuCurrencyPrice::where('currency', $currency)->count();
        }

        return [
            'total_skus' => $totalSkus,
            'multi_currency_skus' => $multiCurrencySkus,
            'coverage_rate' => $totalSkus > 0 ? round($multiCurrencySkus / $totalSkus * 100, 1) : 0,
            'supported_currencies' => $supported,
            'default_currency' => $defaultCurrency,
            'currency_coverage' => $currencyStats,
        ];
    }

    /**
     * 格式化价格
     */
    public function formatPrice(float $amount, string $currency): string
    {
        return \App\Models\ExchangeRate::format($amount, $currency);
    }

    /**
     * 清除SKU价格缓存
     */
    public function clearCache(int $skuId): void
    {
        $supported = config('multi-currency-pricing.currencies.supported', ['CNY', 'USD', 'EUR']);
        foreach ($supported as $currency) {
            Cache::forget(self::CACHE_PREFIX . "display:{$skuId}:{$currency}");
        }
        Cache::forget(self::CACHE_PREFIX . "all:{$skuId}");
    }
}
