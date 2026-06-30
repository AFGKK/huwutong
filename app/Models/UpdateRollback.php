<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M2-15 更新回滚记录
 */
class UpdateRollback extends Model
{
    protected $fillable = [
        'update_package_id', 'from_version', 'to_version', 'trigger_type',
        'status', 'reason', 'rollback_metrics', 'rollback_result',
        'affected_instances', 'executed_at', 'completed_at',
        'approved_by', 'created_by',
    ];

    protected $casts = [
        'rollback_metrics' => 'json',
        'rollback_result' => 'json',
        'executed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const TRIGGER_TYPES = ['manual', 'auto_crash', 'auto_failure', 'auto_timeout'];
    public const STATUSES = ['pending', 'approved', 'rejected', 'executed', 'failed', 'rolled_forward'];

    public function package(): BelongsTo { return $this->belongsTo(UpdatePackage::class, 'update_package_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeExecuted($q) { return $q->where('status', 'executed'); }
}
