<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSiemPushLog
 */
class SiemPushLog extends Model
{
    protected $fillable = [
        'siem_connection_id',
        'status',
        'records_count',
        'response_code',
        'response_body',
        'error_message',
        'duration_ms',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SiemConnection::class, 'siem_connection_id');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
