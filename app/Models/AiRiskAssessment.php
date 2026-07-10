<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAiRiskAssessment
 */
class AiRiskAssessment extends Model
{
    protected $fillable = [
        'ai_system_id', 'assessor_name', 'severity', 'likelihood_score',
        'impact_score', 'risk_score', 'impact_analysis', 'mitigation_measures',
        'residual_risk', 'status', 'attachments', 'assessed_at',
    ];

    protected $casts = [
        'likelihood_score' => 'decimal:2',
        'impact_score' => 'decimal:2',
        'risk_score' => 'decimal:2',
        'impact_analysis' => 'array',
        'attachments' => 'array',
        'assessed_at' => 'datetime',
    ];

    public function system(): BelongsTo { return $this->belongsTo(AiSystemRegistry::class, 'ai_system_id'); }
}
