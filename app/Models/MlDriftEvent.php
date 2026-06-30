<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlDriftEvent extends Model
{
    protected $table = 'ml_drift_events';

    protected $fillable = [
        'ml_model_version_id', 'metric', 'baseline_value', 'current_value',
        'drift_value', 'severity', 'auto_retrain_triggered', 'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'auto_retrain_triggered' => 'boolean',
        ];
    }

    public function modelVersion(): BelongsTo { return $this->belongsTo(MlModelVersion::class, 'ml_model_version_id'); }
}
