<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureValue extends Model
{
    protected $table = 'feature_values';

    protected $fillable = [
        'feature_definition_id', 'entity_id', 'value', 'value_hash',
        'effective_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo { return $this->belongsTo(FeatureDefinition::class, 'feature_definition_id'); }
}
