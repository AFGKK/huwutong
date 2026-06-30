<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeatureGroup extends Model
{
    use SoftDeletes;

    protected $table = 'feature_groups';

    protected $fillable = [
        'tenant_id', 'name', 'group_key', 'entity_type', 'description',
        'status', 'source_type', 'source_config', 'tags',
    ];

    protected function casts(): array
    {
        return [
            'source_config' => 'array',
            'tags' => 'array',
        ];
    }

    public function features(): HasMany { return $this->hasMany(FeatureDefinition::class, 'feature_group_id'); }
}
