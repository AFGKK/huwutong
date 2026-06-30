<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceAiReport extends Model
{
    protected $table = 'compliance_ai_reports';

    protected $fillable = [
        'tenant_id', 'framework', 'title', 'status',
        'sections', 'evidence_summary', 'gap_analysis', 'recommendations',
        'ai_prompt', 'ai_response', 'file_path', 'language',
        'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'evidence_summary' => 'array',
            'gap_analysis' => 'array',
            'recommendations' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function generator(): BelongsTo { return $this->belongsTo(User::class, 'generated_by'); }
    public function evidenceItems(): HasMany { return $this->hasMany(ComplianceEvidenceItem::class, 'compliance_ai_report_id'); }
}
