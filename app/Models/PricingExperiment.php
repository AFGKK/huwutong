<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingExperiment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description',
        'status', 'experiment_type', 'target_metric',
        'confidence_level', 'minimum_sample_size', 'sample_size',
        'traffic_split', 'control_config', 'treatment_config',
        'segment_filters', 'starts_at', 'ends_at',
        'results', 'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'control_config' => 'array',
            'treatment_config' => 'array',
            'segment_filters' => 'array',
            'results' => 'array',
            'metadata' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(PricingExperimentParticipant::class, 'experiment_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PricingExperimentEvent::class, 'experiment_id');
    }
}
