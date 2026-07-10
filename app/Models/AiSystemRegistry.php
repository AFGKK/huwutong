<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAiSystemRegistry
 */
class AiSystemRegistry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'version', 'purpose', 'provider', 'deployment_status',
        'risk_level', 'description', 'owner_department', 'owner_email',
        'capabilities', 'limitations', 'tags',
        'last_reviewed_at', 'next_review_at', 'is_active',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'limitations' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'last_reviewed_at' => 'datetime',
        'next_review_at' => 'datetime',
    ];

    public function riskAssessments(): HasMany { return $this->hasMany(AiRiskAssessment::class, 'ai_system_id'); }
    public function decisionLogs(): HasMany { return $this->hasMany(AiDecisionLog::class, 'ai_system_id'); }
    public function biasDetections(): HasMany { return $this->hasMany(AiBiasDetection::class, 'ai_system_id'); }
    public function trainingDataSources(): HasMany { return $this->hasMany(AiTrainingDataSource::class, 'ai_system_id'); }
    public function disclosures(): HasMany { return $this->hasMany(AiTransparencyDisclosure::class, 'ai_system_id'); }
}
