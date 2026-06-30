<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'locale',
        'name',
        'native_name',
        'flag',
        'direction',
        'is_rtl',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_rtl' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class, 'locale', 'locale');
    }

    public static function getActive(): array
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public static function defaultLocale(): string
    {
        return static::where('is_default', true)->value('locale') ?? 'en';
    }
}
