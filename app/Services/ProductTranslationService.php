<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\ProductTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 多语言商品详情页服务 (M3-88)
 *
 * 管理商品（Product）和 SKU 的多语言翻译，支持：
 * - 按语言翻译商品标题、描述、属性、SEO Meta
 * - 自动根据 Accept-Language 展示对应语言
 * - 翻译管理后台：手动编辑 + AI 自动翻译
 * - LLM 智能翻译（利用 TranslationEngineService）
 *
 * 数据模型：ProductTranslation (morphTo polymorphic)
 */
class ProductTranslationService
{
    public function __construct(
        protected LlmService $llmService,
        protected TranslationEngineService $translationEngine,
    ) {}

    // ─── 翻译查询 ───

    /**
     * 获取商品在指定语言下的翻译数据
     */
    public function getProductTranslations(Product $product, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $translations = ProductTranslation::where('translatable_type', Product::class)
            ->where('translatable_id', $product->id)
            ->where('locale', $locale)
            ->get()
            ->keyBy('field');

        $fields = ['title', 'description', 'short_description', 'features', 'specifications',
                    'meta_title', 'meta_description', 'meta_keywords'];

        $result = [];
        foreach ($fields as $field) {
            $translation = $translations->get($field);
            $result[$field] = [
                'translated' => $translation ? $translation->value : null,
                'is_auto' => $translation ? $translation->is_auto_translated : false,
                'translation_id' => $translation ? $translation->id : null,
            ];
        }

        return $result;
    }

    /**
     * 获取商品的完整翻译集合（所有语言）
     */
    public function getAllProductTranslations(Product $product): array
    {
        $translations = ProductTranslation::where('translatable_type', Product::class)
            ->where('translatable_id', $product->id)
            ->get()
            ->groupBy('locale');

        $result = [];
        foreach ($translations as $locale => $items) {
            $result[$locale] = $items->keyBy('field')->map(fn($t) => [
                'value' => $t->value,
                'is_auto' => $t->is_auto_translated,
                'id' => $t->id,
            ]);
        }

        return $result;
    }

    // ─── 翻译管理 ───

    /**
     * 设置商品指定字段的翻译
     */
    public function setTranslation(
        Product $product,
        string $locale,
        string $field,
        string $value,
        bool $isAutoTranslated = false,
        ?int $userId = null,
    ): ProductTranslation {
        return ProductTranslation::updateOrCreate(
            [
                'translatable_type' => Product::class,
                'translatable_id' => $product->id,
                'locale' => $locale,
                'field' => $field,
            ],
            [
                'value' => $value,
                'is_auto_translated' => $isAutoTranslated,
            ]
        );
    }

    /**
     * 批量设置商品翻译
     */
    public function batchSetTranslations(Product $product, string $locale, array $fields, ?int $userId = null): array
    {
        $results = [];
        foreach ($fields as $field => $value) {
            $results[$field] = $this->setTranslation($product, $locale, $field, $value, false, $userId);
        }
        return $results;
    }

    /**
     * 删除商品指定语言的翻译
     */
    public function deleteLocaleTranslations(Product $product, string $locale): int
    {
        return ProductTranslation::where('translatable_type', Product::class)
            ->where('translatable_id', $product->id)
            ->where('locale', $locale)
            ->delete();
    }

    /**
     * 删除商品指定字段的翻译
     */
    public function deleteFieldTranslation(Product $product, string $locale, string $field): bool
    {
        return ProductTranslation::where('translatable_type', Product::class)
            ->where('translatable_id', $product->id)
            ->where('locale', $locale)
            ->where('field', $field)
            ->delete();
    }

    // ─── SKU 翻译 ───

    /**
     * 获取 SKU 的多语言翻译
     */
    public function getSkuTranslations(ProductSku $sku, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $translations = ProductTranslation::where('translatable_type', ProductSku::class)
            ->where('translatable_id', $sku->id)
            ->where('locale', $locale)
            ->get()
            ->keyBy('field');

        $fields = ['name', 'description', 'features'];

        $result = [];
        foreach ($fields as $field) {
            $translation = $translations->get($field);
            $result[$field] = [
                'translated' => $translation ? $translation->value : null,
                'is_auto' => $translation ? $translation->is_auto_translated : false,
                'translation_id' => $translation ? $translation->id : null,
            ];
        }

        return $result;
    }

    /**
     * 设置 SKU 翻译
     */
    public function setSkuTranslation(
        ProductSku $sku,
        string $locale,
        string $field,
        string $value,
        bool $isAutoTranslated = false,
    ): ProductTranslation {
        return ProductTranslation::updateOrCreate(
            [
                'translatable_type' => ProductSku::class,
                'translatable_id' => $sku->id,
                'locale' => $locale,
                'field' => $field,
            ],
            [
                'value' => $value,
                'is_auto_translated' => $isAutoTranslated,
            ]
        );
    }

    // ─── AI 自动翻译 ───

    /**
     * AI 自动翻译商品到指定语言
     */
    public function autoTranslateProduct(Product $product, string $targetLocale, ?int $userId = null): array
    {
        $sourceLocale = Language::defaultLocale();

        if ($sourceLocale === $targetLocale) {
            return ['error' => '源语言与目标语言相同'];
        }

        $fields = ['title', 'description', 'short_description', 'features', 'specifications',
                    'meta_title', 'meta_description', 'meta_keywords'];

        $results = [];
        $sourceFields = $this->getSourceFields($product, $fields);

        foreach ($fields as $field) {
            $sourceValue = $sourceFields[$field] ?? null;
            if (empty($sourceValue)) {
                $results[$field] = ['status' => 'skipped', 'reason' => '源文本为空'];
                continue;
            }

            try {
                $translated = $this->translateWithAi($sourceValue, $sourceLocale, $targetLocale, $product, $field);
                $this->setTranslation($product, $targetLocale, $field, $translated, true, $userId);
                $results[$field] = ['status' => 'success', 'value' => $translated];
            } catch (\Throwable $e) {
                Log::error('商品AI翻译失败', [
                    'product_id' => $product->id,
                    'field' => $field,
                    'locale' => $targetLocale,
                    'error' => $e->getMessage(),
                ]);
                $results[$field] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * AI 自动翻译 SKU 到指定语言
     */
    public function autoTranslateSku(ProductSku $sku, string $targetLocale): array
    {
        $sourceLocale = Language::defaultLocale();

        if ($sourceLocale === $targetLocale) {
            return ['error' => '源语言与目标语言相同'];
        }

        $fields = ['name', 'description', 'features'];
        $results = [];

        foreach ($fields as $field) {
            $sourceValue = match ($field) {
                'name' => $sku->name,
                'description' => $sku->description,
                'features' => $sku->features,
                default => null,
            };

            if (empty($sourceValue)) {
                $results[$field] = ['status' => 'skipped', 'reason' => '源文本为空'];
                continue;
            }

            try {
                $translated = $this->translateWithAi(
                    is_array($sourceValue) ? json_encode($sourceValue, JSON_UNESCAPED_UNICODE) : $sourceValue,
                    $sourceLocale,
                    $targetLocale,
                    $sku,
                    $field,
                );
                $this->setSkuTranslation($sku, $targetLocale, $field, $translated, true);
                $results[$field] = ['status' => 'success', 'value' => $translated];
            } catch (\Throwable $e) {
                Log::error('SKU AI翻译失败', [
                    'sku_id' => $sku->id,
                    'field' => $field,
                    'error' => $e->getMessage(),
                ]);
                $results[$field] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * AI 批量翻译所有商品到指定语言
     */
    public function autoTranslateAll(int $tenantId, string $targetLocale, ?int $userId = null, int $chunkSize = 10): array
    {
        $stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'details' => []];

        Product::where(function ($q) use ($tenantId) {
            // 全局商品或租户商品
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        })->chunk($chunkSize, function ($products) use ($targetLocale, $userId, &$stats) {
            foreach ($products as $product) {
                try {
                    $result = $this->autoTranslateProduct($product, $targetLocale, $userId);
                    $failed = count(array_filter($result, fn($r) => ($r['status'] ?? '') === 'failed'));
                    $stats['total']++;
                    $stats['success'] += $failed === 0 ? 1 : 0;
                    $stats['failed'] += $failed > 0 ? 1 : 0;
                    $stats['details'][] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'result' => $result,
                    ];
                } catch (\Throwable $e) {
                    $stats['total']++;
                    $stats['failed']++;
                    $stats['details'][] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return $stats;
    }

    /**
     * 获取源文本字段
     */
    protected function getSourceFields(Product $product, array $fields): array
    {
        $source = [];
        foreach ($fields as $field) {
            $source[$field] = match ($field) {
                'title' => $product->name,
                'description' => $product->description,
                'short_description' => $product->short_description ?? $product->description,
                'features' => is_string($product->features) ? $product->features : (is_array($product->features) ? json_encode($product->features, JSON_UNESCAPED_UNICODE) : ''),
                'specifications' => is_string($product->specifications) ? $product->specifications : (is_array($product->specifications) ? json_encode($product->specifications, JSON_UNESCAPED_UNICODE) : ''),
                'meta_title' => $product->meta_title ?? $product->name,
                'meta_description' => $product->meta_description ?? '',
                'meta_keywords' => $product->meta_keywords ?? '',
                default => $product->{$field} ?? '',
            };
        }
        return $source;
    }

    /**
     * 使用 AI 翻译文本
     */
    protected function translateWithAi(
        string $sourceText,
        string $sourceLocale,
        string $targetLocale,
        $entity,
        string $field,
    ): string {
        $localeNames = [
            'zh' => '中文', 'en' => 'English', 'ja' => '日本語',
            'ko' => '한국어', 'fr' => 'Français', 'de' => 'Deutsch',
            'es' => 'Español', 'pt' => 'Português', 'ru' => 'Русский',
            'ar' => 'العربية', 'th' => 'ไทย', 'vi' => 'Tiếng Việt',
        ];

        $sourceLang = $localeNames[$sourceLocale] ?? $sourceLocale;
        $targetLang = $localeNames[$targetLocale] ?? $targetLocale;

        $context = '';
        if ($entity instanceof Product) {
            $context = "商品名称: {$entity->name}";
        } elseif ($entity instanceof ProductSku) {
            $context = "SKU名称: {$entity->name}";
        }

        $prompt = "你是一位专业的电商翻译专家。请将以下商品信息从 {$sourceLang} 翻译为 {$targetLang}。\n\n"
            . "上下文：{$context}\n"
            . "字段类型：{$field}\n\n"
            . "翻译要求：\n"
            . "1. 保持专业术语准确\n"
            . "2. 符合目标语言表达习惯\n"
            . "3. 保留原始格式（如有 Markdown）\n"
            . "4. SEO 关键词需自然融入\n"
            . "5. 仅输出翻译结果，不要附加说明\n\n"
            . "原文：\n{$sourceText}";

        return $this->llmService->chat($prompt);
    }

    // ─── 前端展示 ───

    /**
     * 获取商品在指定语言的展示数据（合并默认字段 + 翻译覆盖）
     */
    public function getDisplayProduct(Product $product, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $translations = $this->getProductTranslations($product, $locale);

        return [
            'id' => $product->id,
            'name' => $translations['title']['translated'] ?? $product->name,
            'description' => $translations['description']['translated'] ?? $product->description,
            'short_description' => $translations['short_description']['translated'] ?? ($product->short_description ?? ''),
            'features' => $translations['features']['translated'] ?? $product->features,
            'specifications' => $translations['specifications']['translated'] ?? $product->specifications,
            'meta_title' => $translations['meta_title']['translated'] ?? ($product->meta_title ?? $product->name),
            'meta_description' => $translations['meta_description']['translated'] ?? ($product->meta_description ?? ''),
            'meta_keywords' => $translations['meta_keywords']['translated'] ?? ($product->meta_keywords ?? ''),
            'base_price' => $product->base_price,
            'sales_count' => $product->sales_count,
            'skus' => $product->skus->map(fn($sku) => $this->getDisplaySku($sku, $locale)),
        ];
    }

    /**
     * 获取 SKU 在指定语言的展示数据
     */
    public function getDisplaySku(ProductSku $sku, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $translations = $this->getSkuTranslations($sku, $locale);

        return [
            'id' => $sku->id,
            'name' => $translations['name']['translated'] ?? $sku->name,
            'description' => $translations['description']['translated'] ?? $sku->description,
            'features' => $translations['features']['translated'] ?? $sku->features,
            'price' => $sku->price,
            'cycle' => $sku->cycle,
            'stock' => $sku->stock,
        ];
    }

    /**
     * 检查商品是否缺少某语言的翻译
     */
    public function getMissingLocales(Product $product): array
    {
        $availableLocales = Language::where('is_active', true)->pluck('code')->toArray();
        $existingLocales = ProductTranslation::where('translatable_type', Product::class)
            ->where('translatable_id', $product->id)
            ->distinct()
            ->pluck('locale')
            ->toArray();

        return array_values(array_diff($availableLocales, $existingLocales));
    }

    // ─── 语言管理 ───

    /**
     * 获取启用的语言列表
     */
    public function getActiveLanguages(): array
    {
        return Language::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * 获取翻译覆盖率统计
     */
    public function getCoverageStats(int $tenantId = null): array
    {
        $query = Product::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        $totalProducts = $query->count();
        $locales = Language::where('is_active', true)->pluck('code')->toArray();

        $coverage = [];
        foreach ($locales as $locale) {
            $translatedCount = ProductTranslation::where('translatable_type', Product::class)
                ->where('locale', $locale)
                ->whereIn('translatable_id', $query->pluck('id'))
                ->distinct('translatable_id')
                ->count('translatable_id');

            $coverage[$locale] = [
                'translated_products' => $translatedCount,
                'total_products' => $totalProducts,
                'coverage_rate' => $totalProducts > 0 ? round($translatedCount / $totalProducts * 100, 2) : 0,
            ];
        }

        return $coverage;
    }
}
