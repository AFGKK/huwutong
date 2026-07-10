<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperSloDefinition
 */
class SloDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'service_name',
        'sli_type',
        'target',
        'window_days',
        'burn_rate_alerts',
        'is_active',
        'total_requests',
        'good_requests',
        'current_sli',
        'remaining_budget',
        'burn_rate',
    ];

    protected function casts(): array
    {
        return [
            'burn_rate_alerts' => 'array',
            'target' => 'decimal:2',
            'window_days' => 'decimal:1',
            'is_active' => 'boolean',
            'current_sli' => 'decimal:2',
            'remaining_budget' => 'decimal:2',
            'burn_rate' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function budgetEvents(): HasMany
    {
        return $this->hasMany(SloBudgetEvent::class, 'slo_definition_id');
    }

    public function dailyRecords(): HasMany
    {
        return $this->hasMany(SloDailyRecord::class, 'slo_definition_id');
    }

    /**
     * 计算错误预算总量（分钟）
     * 基于滚动窗口总可用时间 * (1 - target%)
     */
    public function totalBudgetMinutes(): float
    {
        $totalMinutes = $this->window_days * 24 * 60;
        return round($totalMinutes * (1 - $this->target / 100), 2);
    }

    /**
     * 计算已消耗的错误预算（分钟）
     */
    public function consumedBudgetMinutes(): float
    {
        $totalBudget = $this->totalBudgetMinutes();
        if ($totalBudget <= 0) return 0;
        return round($totalBudget - ($this->remaining_budget ?? $totalBudget), 2);
    }
}
