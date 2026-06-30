<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaAlertLog extends Model
{
    protected $fillable = [
        'quota_alert_id',
        'quota_type',
        'level',
        'usage_percent',
        'current_usage',
        'quota_limit',
        'channel',
        'status',
        'message',
        'response',
    ];

    public function quotaAlert(): BelongsTo
    {
        return $this->belongsTo(QuotaAlert::class, 'quota_alert_id');
    }

    public function scopeByLevel($query, ?string $level)
    {
        if ($level) {
            return $query->where('level', $level);
        }
        return $query;
    }
}
