<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditArchiveRecord extends Model
{
    protected $table = 'audit_archive_records';

    protected $fillable = [
        'policy_id', 'type', 'status',
        'total_logs', 'archived_logs', 'deleted_logs',
        'archive_file', 'file_size_bytes',
        'archive_date_from', 'archive_date_to',
        'error_message', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'archive_date_from' => 'date',
            'archive_date_to' => 'date',
            'executed_at' => 'datetime',
        ];
    }

    public function policy(): BelongsTo { return $this->belongsTo(AuditArchivePolicy::class, 'policy_id'); }
}
