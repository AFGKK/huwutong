<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 状态事件/事故
 *
 * @mixin IdeHelperStatusIncident
 */
class StatusIncident extends Model
{
    protected $fillable = [
        'title', 'description', 'severity',
        'status', 'is_public',
        'occurred_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(StatusComponent::class, 'incident_component', 'incident_id', 'component_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class, 'incident_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days))
            ->orWhere('created_at', '>=', now()->subDays($days));
    }

    public function severityLabel(): string
    {
        return [
            'minor' => '轻微',
            'major' => '严重',
            'critical' => '重大',
        ][$this->severity] ?? $this->severity;
    }

    public function statusLabel(): string
    {
        return [
            'investigating' => '调查中',
            'identified' => '已确认',
            'monitoring' => '监控中',
            'resolved' => '已解决',
            'postmortem' => '事后分析',
        ][$this->status] ?? $this->status;
    }

    public function severityTagType(): string
    {
        return [
            'minor' => 'warning',
            'major' => 'danger',
            'critical' => 'danger',
        ][$this->severity] ?? 'info';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'postmortem']);
    }
}
