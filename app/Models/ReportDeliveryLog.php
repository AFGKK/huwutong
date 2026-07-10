<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperReportDeliveryLog
 */
class ReportDeliveryLog extends Model
{
    protected $table = 'report_delivery_logs';

    protected $fillable = [
        'schedule_id',
        'report_id',
        'snapshot_id',
        'status',
        'export_format',
        'file_path',
        'file_size',
        'recipients',
        'delivery_results',
        'attempts',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'delivery_results' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'schedule_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class, 'report_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'snapshot_id');
    }
}
