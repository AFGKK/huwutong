<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperReportSnapshot
 */
class ReportSnapshot extends Model
{
    protected $table = 'report_snapshots';

    protected $fillable = [
        'report_id', 'status', 'snapshot_data', 'summary',
        'row_count', 'file_path', 'file_format', 'file_size',
        'generated_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
            'summary' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class, 'report_id');
    }
}
