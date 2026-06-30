<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDecisionLog extends Model
{
    protected $fillable = [
        'decision_id', 'ai_system_id', 'model_name', 'decision_type',
        'input_summary', 'output_summary', 'full_input', 'full_output',
        'confidence_score', 'was_overridden', 'overridden_by', 'overridden_at',
        'override_reason', 'customer_id', 'tenant_id', 'result',
        'ip_address', 'user_agent', 'disclosure_shown', 'occurred_at',
    ];

    protected $casts = [
        'full_input' => 'array',
        'full_output' => 'array',
        'confidence_score' => 'decimal:2',
        'was_overridden' => 'boolean',
        'disclosure_shown' => 'boolean',
        'overridden_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    public function system(): BelongsTo { return $this->belongsTo(AiSystemRegistry::class, 'ai_system_id'); }
    public function overrider(): BelongsTo { return $this->belongsTo(User::class, 'overridden_by'); }
    public function overrideRequests(): HasMany { return $this->hasMany(AiOverrideRequest::class, 'ai_decision_log_id'); }
}
