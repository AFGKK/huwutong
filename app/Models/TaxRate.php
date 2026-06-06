<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = [
        'country_code', 'region_code', 'name', 'rate',
        'type', 'category', 'description', 'is_eu',
        'is_active', 'effective_from', 'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'is_eu' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    /**
     * 查找指定国家的适用税率
     */
    public static function findRate(string $countryCode, ?string $regionCode = null, string $type = 'vat'): ?self
    {
        $query = static::where('country_code', strtoupper($countryCode))
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>', now());
            });

        // 优先找 region 精确匹配
        if ($regionCode) {
            $region = $query->clone()->where('region_code', strtoupper($regionCode))->first();
            if ($region) return $region;
        }

        // 回退到国家级别
        return $query->whereNull('region_code')->first();
    }

    /**
     * 获取所有 EU 国家代码
     */
    public static function getEuCountries(): array
    {
        return static::where('is_eu', true)->distinct()->pluck('country_code')->toArray();
    }
}
