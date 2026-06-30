<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M2-15 灰度发布规则
 */
class UpdateGrayRelease extends Model
{
    protected $fillable = [
        'update_package_id', 'strategy', 'current_stage', 'current_percentage',
        'target_regions', 'excluded_regions', 'whitelist_tenants',
        'blacklist_tenants', 'tenant_tags', 'status',
        'stage_started_at', 'stage_ends_at', 'stage_metrics',
        'config', 'created_by',
    ];

    protected $casts = [
        'target_regions' => 'json',
        'excluded_regions' => 'json',
        'whitelist_tenants' => 'json',
        'blacklist_tenants' => 'json',
        'tenant_tags' => 'json',
        'stage_metrics' => 'json',
        'config' => 'json',
        'stage_started_at' => 'datetime',
        'stage_ends_at' => 'datetime',
    ];

    public const STRATEGIES = ['region', 'percentage', 'whitelist', 'tenant_tag'];
    public const STAGES = ['canary', 'beta', 'wide', 'full'];
    public const STATUSES = ['pending', 'running', 'paused', 'completed', 'rolled_back'];

    public function package(): BelongsTo { return $this->belongsTo(UpdatePackage::class, 'update_package_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeRunning($q) { return $q->where('status', 'running'); }
    public function scopeByStage($q, $stage) { return $q->where('current_stage', $stage); }

    public function isEligibleForNextStage(): bool
    {
        if ($this->current_stage === 'full') return false;
        if ($this->status !== 'running') return false;
        if (!$this->stage_ends_at) return true;

        return $this->stage_ends_at->isPast();
    }
}
