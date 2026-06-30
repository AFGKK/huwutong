<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBiasDetection extends Model
{
    protected $fillable = [
        'ai_system_id', 'metric', 'score', 'threshold', 'flagged',
        'severity', 'description', 'segment_data', 'mitigation_action',
        'status', 'detected_at', 'resolved_at',
    ];

    protected $casts = [
        'score' => 'decimal:4',
        'threshold' => 'decimal:4',
        'flagged' => 'boolean',
        'segment_data' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function system(): BelongsTo { return $this->belongsTo(AiSystemRegistry::class, 'ai_system_id'); }
}
