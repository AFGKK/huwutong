<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertEvent extends Model
{
    protected $table = 'alert_events';

    protected $fillable = [
        'alert_rule_id', 'event_type', 'severity',
        'title', 'message', 'status',
        'context', 'channels_sent',
        'source_type', 'source_id',
        'fired_at', 'acknowledged_at', 'resolved_at',
        'acknowledged_by', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'channels_sent' => 'array',
            'fired_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isFiring(): bool
    {
        return $this->status === 'firing';
    }

    public function acknowledge(int $userId): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    public function resolve(int $userId): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);
    }
}
