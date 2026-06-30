<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Support\Facades\App;

/**
 * M3-88 多语言商品详情页
 */
class MultilingualProductService
{
    /**
     * 获取商品的翻译（按当前语言或指定语言）
     */
    public function getTranslation(Product $product, string $locale = null): array
    {
        $locale = $locale ?? App::getLocale();
        $translations = ProductTranslation::where('translatable_type', Product::class)
            ->where('translatable_id', $product->id)
            ->where('locale', $locale)
            ->get()
            ->keyBy('field');

        $fallbackLocale = config('multilingual-product.languages.fallback', 'en');
        if ($translations->isEmpty() && $locale !== $fallbackLocale) {
            return $this->getTranslation($product, $fallbackLocale);
        }

        return [
            'locale' => $locale,
            'fields' => [
                'name' => $translations->get('name')?->value ?? $product->name,
                'description' => $translations->get('description')?->value ?? $product->description,
                'short_description' => $translations->get('short_description')?->value ?? '',
                'seo_title' => $translations->get('seo_title')?->value ?? $product->name,
                'seo_description' => $translations->get('seo_description')?->value ?? '',
            ],
        ];
    }

    /**
     * 保存商品翻译
     */
    public function saveTranslation(Product $product, string $locale, array $fields): void
    {
        foreach ($fields as $field => $value) {
            ProductTranslation::updateOrCreate(
                [
                    'translatable_type' => Product::class,
                    'translatable_id' => $product->id,
                    'locale' => $locale,
                    'field' => $field,
                ],
                [
                    'value' => $value,
                    'is_auto_translated' => false,
                ]
            );
        }
    }

    /**
     * 获取商品支持的语言列表
     */
    public function getAvailableLocales(Product $product): array
    {
        return ProductTranslation::where('translatable_type', Product::class)
            ->where('translatable_id', $product->id)
            ->distinct()
            ->pluck('locale')
            ->toArray();
    }

    /**
     * 获取所有商品的多语言版本（用于SEO hreflang）
     */
    public function getHreflangTags(Product $product): array
    {
        $locales = $this->getAvailableLocales($product);
        $tags = [];

        foreach ($locales as $locale) {
            $tags[] = [
                'locale' => $locale,
                'url' => url("/products/{$product->id}?lang={$locale}"),
            ];
        }

        return $tags;
    }
}
