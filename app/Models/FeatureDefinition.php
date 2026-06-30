<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureDefinition extends Model
{
    protected $table = 'feature_definitions';

    protected $fillable = [
        'feature_group_id', 'name', 'feature_key', 'value_type', 'description',
        'is_online', 'is_offline', 'default_value', 'validation_rules', 'metadata', 'version',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'is_offline' => 'boolean',
            'validation_rules' => 'array',
            'metadata' => 'array',
        ];
    }

    public function group(): BelongsTo { return $this->belongsTo(FeatureGroup::class, 'feature_group_id'); }
    public function values(): HasMany { return $this->hasMany(FeatureValue::class, 'feature_definition_id'); }
    public function offlineRecords(): HasMany { return $this->hasMany(FeatureOfflineStore::class, 'feature_definition_id'); }
    public function consistencyChecks(): HasMany { return $this->hasMany(FeatureConsistencyCheck::class, 'feature_definition_id'); }
}
