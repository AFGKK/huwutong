<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAuditExportSchedule
 */
class AuditExportSchedule extends Model
{
    protected $table = 'audit_export_schedules';

    protected $fillable = [
        'user_id', 'name', 'cron_expression', 'format', 'filters',
        'notification_emails', 'is_active', 'max_records',
        'compression', 'description',
        'last_run_at', 'next_run_at', 'run_count',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'notification_emails' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
