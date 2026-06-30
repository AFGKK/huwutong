<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmtpDeliveryLog extends Model
{
    protected $fillable = [
        'smtp_config_id',
        'event_type',
        'status',
        'from_address',
        'to_address',
        'subject',
        'error_message',
        'stack_trace',
        'failure_count',
        'fallback_action',
    ];

    public function smtpConfig(): BelongsTo
    {
        return $this->belongsTo(CustomerSmtpConfig::class, 'smtp_config_id');
    }

    public function scopeByType($query, ?string $type)
    {
        if ($type) {
            return $query->where('event_type', $type);
        }
        return $query;
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
