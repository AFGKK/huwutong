<?php

namespace App\Models\Concerns;

use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasProductTranslations
{
    public function translations(): MorphMany
    {
        return $this->morphMany(ProductTranslation::class, 'translatable');
    }

    /**
     * 获取指定语言的翻译值
     */
    public function translation(string $locale, string $field): ?string
    {
        $translation = $this->translations
            ->where('locale', $locale)
            ->where('field', $field)
            ->first();

        return $translation?->value;
    }

    /**
     * 获取指定语言的所有翻译
     */
    public function translationsFor(string $locale): array
    {
        return $this->translations
            ->where('locale', $locale)
            ->pluck('value', 'field')
            ->toArray();
    }

    /**
     * 设置翻译
     */
    public function setTranslation(string $locale, string $field, ?string $value, bool $autoTranslated = false): ProductTranslation
    {
        return ProductTranslation::updateOrCreate(
            [
                'translatable_type' => static::class,
                'translatable_id' => $this->id,
                'locale' => $locale,
                'field' => $field,
            ],
            [
                'value' => $value,
                'is_auto_translated' => $autoTranslated,
            ]
        );
    }

    /**
     * 批量设置翻译
     */
    public function setTranslations(string $locale, array $fields, bool $autoTranslated = false): void
    {
        foreach ($fields as $field => $value) {
            $this->setTranslation($locale, $field, $value, $autoTranslated);
        }
    }

    /**
     * 删除指定语言的所有翻译
     */
    public function deleteTranslationsFor(string $locale): void
    {
        ProductTranslation::where('translatable_type', static::class)
            ->where('translatable_id', $this->id)
            ->where('locale', $locale)
            ->delete();
    }
}
