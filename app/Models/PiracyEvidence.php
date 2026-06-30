<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PiracyEvidence extends Model
{
    protected $table = 'piracy_evidence';

    protected $fillable = [
        'piracy_scan_task_id', 'license_id', 'license_key',
        'source', 'source_url', 'snippet', 'screenshot_path',
        'confidence', 'confidence_level', 'matched_pattern',
        'context', 'status', 'assignee', 'notes',
        'detected_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function scanTask(): BelongsTo { return $this->belongsTo(PiracyScanTask::class, 'piracy_scan_task_id'); }
    public function license(): BelongsTo { return $this->belongsTo(License::class); }
    public function forensicReports(): HasMany { return $this->hasMany(PiracyForensicReport::class, 'piracy_evidence_id'); }
}
