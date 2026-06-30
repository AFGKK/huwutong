<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiOverrideRequest extends Model
{
    protected $fillable = [
        'request_id', 'ai_decision_log_id', 'customer_identifier',
        'customer_email', 'reason', 'status', 'assigned_to',
        'escalation_level', 'resolution_notes', 'final_decision',
        'submitted_at', 'resolved_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function decisionLog(): BelongsTo { return $this->belongsTo(AiDecisionLog::class, 'ai_decision_log_id'); }
}
