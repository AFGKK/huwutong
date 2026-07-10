<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperBillingCycle
 */
class BillingCycle extends Model
{
    protected $fillable = [
        'code', 'name', 'months', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 获取所有启用周期编码列表（用于验证规则）
     */
    public static function activeCodes(): array
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    /**
     * 获取选项列表（用于前端下拉）
     */
    public static function options(): array
    {
        return self::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'months'])
            ->toArray();
    }
}
