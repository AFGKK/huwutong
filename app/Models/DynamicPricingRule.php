<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DynamicPricingRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dynamic_pricing_rules';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'rule_type',
        'target_type',
        'target_id',
        'target_ids',
        'adjustment_type',
        'adjustment_value',
        'min_price',
        'max_price',
        'conditions',
        'schedule',
        'timezone',
        'priority',
        'stack_mode',
        'allowed_stack_with',
        'is_active',
        'applied_count',
        'last_applied_at',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'target_ids' => 'array',
            'conditions' => 'array',
            'schedule' => 'array',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'adjustment_value' => 'decimal:2',
            'priority' => 'integer',
            'applied_count' => 'integer',
            'allowed_stack_with' => 'array',
            'is_active' => 'boolean',
            'last_applied_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ─── 作用域 ───

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('rule_type', $type);
    }

    public function scopeForTarget($query, string $targetType, ?int $targetId = null)
    {
        $query->where(function ($q) use ($targetType, $targetId) {
            $q->where('target_type', $targetType);
            if ($targetId) {
                $q->where(function ($sub) use ($targetId) {
                    $sub->where('target_id', $targetId)
                        ->orWhereJsonContains('target_ids', $targetId);
                });
            }
        });
        return $query;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    // ─── 规则类型常量 ───

    const TYPE_VOLUME = 'volume';
    const TYPE_SEGMENT = 'segment';
    const TYPE_TIME_SEASONAL = 'time_seasonal';
    const TYPE_TIME_HOURLY = 'time_hourly';
    const TYPE_PROMOTION = 'promotion';
    const TYPE_LLM_OPTIMIZED = 'llm_optimized';

    const TARGET_TYPES = ['plan', 'customer', 'segment', 'product'];

    const ADJUSTMENT_TYPES = ['percentage', 'fixed', 'override', 'formula'];

    const STACK_MODES = ['replace', 'add', 'multiply', 'compound'];
}
