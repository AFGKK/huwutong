<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceReport extends Model
{
    protected $fillable = [
        'framework_id', 'title', 'type', 'status',
        'period_start', 'period_end',
        'controls_assessed', 'findings', 'evidence_refs',
        'summary', 'risk_level',
        'passed_count', 'failed_count', 'na_count',
        'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'controls_assessed' => 'array',
            'findings' => 'array',
            'evidence_refs' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(ComplianceFramework::class, 'framework_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
