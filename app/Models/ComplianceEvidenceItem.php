<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperComplianceEvidenceItem
 */
class ComplianceEvidenceItem extends Model
{
    protected $table = 'compliance_evidence_items';

    protected $fillable = [
        'compliance_ai_report_id', 'framework', 'section', 'control_id',
        'title', 'description', 'status', 'evidence',
        'gap', 'recommendation', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'compliance_ai_report_id' => 'integer',
        ];
    }

    public function report(): BelongsTo { return $this->belongsTo(ComplianceAiReport::class, 'compliance_ai_report_id'); }
}
