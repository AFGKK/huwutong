<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceReportExport extends Model
{
    protected $table = 'compliance_report_exports';

    protected $fillable = [
        'compliance_report_id',
        'format',
        'status',
        'file_path',
        'file_size',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ComplianceReport::class, 'compliance_report_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
