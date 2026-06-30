<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidgetCache extends Model
{
    protected $table = 'dashboard_widget_caches';

    protected $fillable = [
        'widget_id', 'data', 'refresh_interval_seconds',
        'cached_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'cached_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function widget(): BelongsTo { return $this->belongsTo(DashboardWidget::class, 'widget_id'); }

    public function isExpired(): bool
    {
        return !$this->expires_at || $this->expires_at->isPast();
    }
}
