<?php

namespace App\Services;

use App\Models\Language;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Model;

class ProductLocalizationService
{
    /**
     * 可翻译的模型类型
     */
    const TRANSLATABLE_TYPES = [
        'product' => \App\Models\Product::class,
        'plan' => \App\Models\PricingPlan::class,
    ];

    /**
     * 各模型可翻译的字段
     */
    const TRANSLATABLE_FIELDS = [
        'product' => ['name', 'description'],
        'plan' => ['name', 'description', 'features'],
    ];

    /**
     * 获取支持的语言列表
     */
    public function getSupportedLanguages()
    {
        return Language::where('is_active', true)
            ->orderBy('sort_order')
            ->select(['locale', 'name', 'native_name', 'flag', 'is_rtl'])
            ->get();
    }

    /**
     * 获取模型的翻译
     */
    public function getTranslations(Model $model, ?string $locale = null): array
    {
        $query = $model->translations();

        if ($locale) {
            $query->where('locale', $locale);
        }

        return $query->orderBy('locale')->orderBy('field')->get()->toArray();
    }

    /**
     * 获取指定语言的结构化翻译
     */
    public function getLocalized(Model $model, string $locale): array
    {
        $translations = $model->translationsFor($locale);
        $type = $this->resolveType($model);
        $fields = self::TRANSLATABLE_FIELDS[$type] ?? [];

        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $translations[$field] ?? $model->$field ?? null;
        }

        return $result;
    }

    /**
     * 批量保存翻译
     */
    public function saveTranslations(Model $model, string $locale, array $translations, bool $autoTranslated = false): void
    {
        foreach ($translations as $field => $value) {
            if ($value !== null && $value !== '') {
                $model->setTranslation($locale, $field, $value, $autoTranslated);
            }
        }
    }

    /**
     * 删除翻译
     */
    public function deleteTranslation(Model $model, string $locale, ?string $field = null): void
    {
        $query = ProductTranslation::where('translatable_type', get_class($model))
            ->where('translatable_id', $model->id)
            ->where('locale', $locale);

        if ($field) {
            $query->where('field', $field);
        }

        $query->delete();
    }

    /**
     * 复制翻译到另一个模型
     */
    public function copyTranslations(Model $source, Model $target): void
    {
        $translations = ProductTranslation::where('translatable_type', get_class($source))
            ->where('translatable_id', $source->id)
            ->get();

        foreach ($translations as $t) {
            ProductTranslation::create([
                'translatable_type' => get_class($target),
                'translatable_id' => $target->id,
                'locale' => $t->locale,
                'field' => $t->field,
                'value' => $t->value,
                'is_auto_translated' => $t->is_auto_translated,
            ]);
        }
    }

    /**
     * 获取翻译统计
     */
    public function getStats(): array
    {
        $languages = Language::where('is_active', true)->pluck('locale');

        $totalEntries = ProductTranslation::count();
        $autoTranslated = ProductTranslation::where('is_auto_translated', true)->count();
        $manualTranslated = $totalEntries - $autoTranslated;

        $perLanguage = [];
        foreach ($languages as $locale) {
            $count = ProductTranslation::where('locale', $locale)->count();
            $perLanguage[$locale] = $count;
        }

        $perType = [];
        foreach (self::TRANSLATABLE_TYPES as $key => $class) {
            $count = ProductTranslation::where('translatable_type', $class)->count();
            $perType[$key] = $count;
        }

        return [
            'total_entries' => $totalEntries,
            'auto_translated' => $autoTranslated,
            'manual_translated' => $manualTranslated,
            'per_language' => $perLanguage,
            'per_type' => $perType,
            'language_count' => $languages->count(),
        ];
    }

    /**
     * 解析模型类型标识
     */
    protected function resolveType(Model $model): string
    {
        $class = get_class($model);
        foreach (self::TRANSLATABLE_TYPES as $key => $typeClass) {
            if ($class === $typeClass) {
                return $key;
            }
        }
        return 'product';
    }
}
