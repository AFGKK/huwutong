<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFeatureOfflineStore
 */
class FeatureOfflineStore extends Model
{
    protected $table = 'feature_offline_stores';

    protected $fillable = [
        'feature_definition_id', 'entity_id', 'value', 'value_hash',
        'event_date', 'batch_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'batch_processed_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo { return $this->belongsTo(FeatureDefinition::class, 'feature_definition_id'); }
}
