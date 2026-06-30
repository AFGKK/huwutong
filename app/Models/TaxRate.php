<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    protected $fillable = [
        'country_code', 'region_code', 'name', 'rate', 'type',
        'category', 'description', 'is_eu', 'is_active',
        'effective_from', 'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_eu' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_until')->orWhere('effective_until', '>=', now());
            });
    }

    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    public function scopeForRegion($query, ?string $regionCode)
    {
        if ($regionCode) {
            return $query->where('region_code', $regionCode);
        }
        return $query->whereNull('region_code');
    }

    /**
     * 查找适用税率（修复 TaxCalculatorService 的调用）
     */
    public static function findRate(string $countryCode, ?string $regionCode = null): ?self
    {
        return self::active()
            ->forCountry($countryCode)
            ->forRegion($regionCode)
            ->orderByDesc('region_code') // 优先精确匹配区域
            ->first();
    }

    /**
     * 获取所有 EU 国家代码
     */
    public static function getEuCountries(): array
    {
        return self::where('is_eu', true)
            ->whereNull('region_code')
            ->distinct()
            ->pluck('country_code')
            ->toArray();
    }

    public function taxLines()
    {
        return $this->hasMany(InvoiceTaxLine::class);
    }
}
