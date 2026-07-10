<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPricingExperimentEvent
 */
class PricingExperimentEvent extends Model
{
    protected $table = 'pricing_experiment_events';

    protected $fillable = [
        'experiment_id', 'participant_id', 'event_type',
        'event_data', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(PricingExperiment::class, 'experiment_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(PricingExperimentParticipant::class, 'participant_id');
    }
}
