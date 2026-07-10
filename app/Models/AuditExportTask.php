<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAuditExportTask
 */
class AuditExportTask extends Model
{
    protected $fillable = [
        'user_id', 'name', 'format', 'filters',
        'status', 'total_records', 'exported_records',
        'file_size_bytes', 'file_path', 'file_name', 'disk',
        'error_message', 'completed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    const FORMATS = ['csv', 'json', 'xlsx', 'pdf'];
    const STATUSES = ['pending', 'processing', 'completed', 'failed', 'cancelled'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
