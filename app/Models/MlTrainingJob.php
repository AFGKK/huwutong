<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlTrainingJob extends Model
{
    protected $table = 'ml_training_jobs';

    protected $fillable = [
        'ml_model_id', 'job_id', 'status', 'config', 'results',
        'duration_seconds', 'error_message', 'started_at', 'completed_at', 'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'results' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function model(): BelongsTo { return $this->belongsTo(MlModel::class, 'ml_model_id'); }
    public function trigger(): BelongsTo { return $this->belongsTo(User::class, 'triggered_by'); }
}
