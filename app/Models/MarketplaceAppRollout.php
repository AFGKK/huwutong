<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperMarketplaceAppRollout
 */
class MarketplaceAppRollout extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_id', 'version_id', 'name', 'description',
        'rollout_type', 'percentage', 'target_filters',
        'status', 'auto_rollback', 'error_threshold',
        'assigned_count', 'installed_count', 'error_count',
        'started_at', 'paused_at', 'completed_at',
        'rolled_back_at', 'rolled_back_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'integer',
            'auto_rollback' => 'boolean',
            'error_threshold' => 'decimal:2',
            'assigned_count' => 'integer',
            'installed_count' => 'integer',
            'error_count' => 'integer',
            'target_filters' => 'array',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(MarketplaceApp::class, 'app_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAppVersion::class, 'version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rollbacker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(MarketplaceAppRolloutTenant::class, 'rollout_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketplaceAppRolloutEvent::class, 'rollout_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRolledBack(): bool
    {
        return $this->status === 'rolled_back';
    }

    public function errorRate(): float
    {
        if ($this->assigned_count === 0) return 0;
        return round(($this->error_count / $this->assigned_count) * 100, 2);
    }

    public function progressPercent(): float
    {
        if ($this->assigned_count === 0) return 0;
        return round(($this->installed_count / $this->assigned_count) * 100, 1);
    }

    public function shouldAutoRollback(): bool
    {
        return $this->auto_rollback && $this->errorRate() >= $this->error_threshold;
    }

    public function scopeByStatus($q, $status) { return $q->where('status', $status); }
    public function scopeActive($q) { return $q->where('status', 'active'); }
    public function scopeForApp($q, $appId) { return $q->where('app_id', $appId); }
}
