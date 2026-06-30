<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureConsistencyCheck extends Model
{
    protected $table = 'feature_consistency_checks';

    protected $fillable = [
        'feature_definition_id', 'total_samples', 'matched_count', 'mismatched_count',
        'match_percent', 'drift_percent', 'status', 'details', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo { return $this->belongsTo(FeatureDefinition::class, 'feature_definition_id'); }
}
