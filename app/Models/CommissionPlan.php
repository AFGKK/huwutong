<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 佣金计划
 *
 * 定义一套佣金规则，包含多条计划明细（不同产品×等级组合）。
 *
 * @mixin IdeHelperCommissionPlan
 */
class CommissionPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommissionPlanItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
