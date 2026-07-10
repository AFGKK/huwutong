<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperRefundRiskAssessment
 */
class RefundRiskAssessment extends Model
{
    protected $table = 'refund_risk_assessments';

    protected $fillable = [
        'assessable_type', 'assessable_id', 'risk_score', 'risk_level',
        'factors', 'matched_rules', 'decision',
        'review_status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'factors' => 'array',
            'matched_rules' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function assessable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
