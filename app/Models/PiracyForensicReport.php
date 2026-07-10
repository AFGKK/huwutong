<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPiracyForensicReport
 */
class PiracyForensicReport extends Model
{
    protected $table = 'piracy_forensic_reports';

    protected $fillable = [
        'piracy_evidence_id', 'license_id', 'title', 'report_type',
        'evidence_items', 'analysis', 'timeline', 'affected_licenses',
        'recommended_action', 'file_path', 'status',
        'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_items' => 'array',
            'timeline' => 'array',
            'affected_licenses' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function evidence(): BelongsTo { return $this->belongsTo(PiracyEvidence::class, 'piracy_evidence_id'); }
    public function license(): BelongsTo { return $this->belongsTo(License::class); }
    public function generator(): BelongsTo { return $this->belongsTo(User::class, 'generated_by'); }
}
