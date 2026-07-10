<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 合规差距分析
 *
 * 记录当前状态与合规目标之间的差距，以及整改计划。
 *
 * @m3-69 CompliancePack
 * @mixin IdeHelperComplianceGapAnalysis
 */
class ComplianceGapAnalysis extends Model
{
    protected $fillable = [
        'framework_code',
        'report_id',
        'control_ref',
        'control_title',
        'current_state',
        'target_state',
        'gap_description',
        'risk_level',
        'remediation_plan',
        'remediation_steps',
        'remediation_status',
        'priority',
        'owner',
        'target_date',
        'completed_at',
        'verified_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'remediation_steps' => 'array',
            'target_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ComplianceReport::class, 'report_id');
    }

    public function scopeFramework($query, string $code)
    {
        return $query->where('framework_code', $code);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('remediation_status', ['identified', 'in_progress']);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }
}
