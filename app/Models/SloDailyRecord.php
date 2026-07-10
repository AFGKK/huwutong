<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSloDailyRecord
 */
class SloDailyRecord extends Model
{
    protected $fillable = [
        'slo_definition_id',
        'record_date',
        'total_requests',
        'good_requests',
        'bad_requests',
        'sli',
        'budget_consumed',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'sli' => 'decimal:2',
            'budget_consumed' => 'decimal:2',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(SloDefinition::class, 'slo_definition_id');
    }
}
