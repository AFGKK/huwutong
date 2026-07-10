<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 合规审计问卷模板 & 响应
 *
 * 存储 SOC2/ISO27001 预填审计问卷的问题模板和客户响应。
 *
 * @m3-69 CompliancePack
 * @mixin IdeHelperComplianceQuestionnaire
 */
class ComplianceQuestionnaire extends Model
{
    protected $fillable = [
        'framework_code',
        'category',
        'question_key',
        'question',
        'guidance',
        'control_ref',
        'severity',
        'sort_order',
        'is_required',
        'is_active',
        'response_type',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ComplianceQuestionnaireResponse::class, 'question_id');
    }
}

/**
 * 问卷响应
 *
 * @mixin IdeHelperComplianceQuestionnaireResponse
 */
class ComplianceQuestionnaireResponse extends Model
{
    protected $fillable = [
        'question_id',
        'report_id',
        'response',
        'evidence_refs',
        'notes',
        'status',
        'answered_by',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_refs' => 'array',
            'answered_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ComplianceQuestionnaire::class, 'question_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ComplianceReport::class, 'report_id');
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
