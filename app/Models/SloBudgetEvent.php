<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSloBudgetEvent
 */
class SloBudgetEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slo_definition_id',
        'event_type',
        'budget_remaining',
        'burn_rate',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'budget_remaining' => 'decimal:2',
            'burn_rate' => 'decimal:2',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(SloDefinition::class, 'slo_definition_id');
    }
}
