<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QuotaAlert extends Model
{
    protected $fillable = [
        'alertable_type',
        'alertable_id',
        'quota_type',
        'quota_limit',
        'current_usage',
        'usage_percent',
        'level',
        'notifications_enabled',
        'auto_upgrade',
        'last_checked_at',
        'last_notified_at',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'auto_upgrade' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_notified_at' => 'datetime',
    ];

    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(QuotaAlertLog::class, 'quota_alert_id');
    }

    public function scopeByLevel($query, ?string $level)
    {
        if ($level) {
            return $query->where('level', $level);
        }
        return $query;
    }

    public function scopeByQuotaType($query, ?string $type)
    {
        if ($type) {
            return $query->where('quota_type', $type);
        }
        return $query;
    }

    public function scopeActive($query)
    {
        return $query->whereIn('level', ['warning', 'critical', 'exceeded']);
    }

    public function isWarning(): bool
    {
        return $this->level === 'warning';
    }

    public function isCritical(): bool
    {
        return $this->level === 'critical';
    }

    public function isExceeded(): bool
    {
        return $this->level === 'exceeded';
    }
}
