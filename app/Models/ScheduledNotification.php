<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledNotification extends Model
{
    protected $fillable = [
        'title',
        'type',
        'channel',
        'content',
        'rich_content',
        'action_url',
        'action_text',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'success_count',
        'failure_count',
        'created_by',
        'filters',
        'metadata',
        'is_cancelled',
        'cancelled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'filters' => 'array',
        'metadata' => 'array',
        'is_cancelled' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(NotificationDeliveryLog::class, 'notification_id');
    }

    public function scopeByStatus($query, ?string $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }

    public function scopeByType($query, ?string $type)
    {
        if ($type) {
            return $query->where('type', $type);
        }
        return $query;
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->where('is_cancelled', false);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
