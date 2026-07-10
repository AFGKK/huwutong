<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M2-17 SDK远程销毁命令
 *
 * @property int $id
 * @property string $command_id
 * @property string|null $sdk_instance_id
 * @property string|null $language
 * @property string|null $version_constraint
 * @property string $destroy_mode soft|hard
 * @property string $trigger_type
 * @property string|null $reason
 * @property string $status pending|dispatched|confirmed|expired|cancelled
 * @property array|null $dispatched_instances
 * @property array|null $confirmed_instances
 * @property int $affected_count
 * @mixin IdeHelperSdkDestroyCommand
 */
class SdkDestroyCommand extends Model
{
    protected $fillable = [
        'command_id', 'sdk_instance_id', 'language', 'version_constraint',
        'destroy_mode', 'trigger_type', 'reason', 'status',
        'dispatched_instances', 'confirmed_instances', 'affected_count',
        'expires_at', 'dispatched_at', 'confirmed_at', 'created_by',
    ];

    protected $casts = [
        'dispatched_instances' => 'json',
        'confirmed_instances' => 'json',
        'expires_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public const TRIGGER_TYPES = [
        'integrity_failure', 'remote_command', 'license_revoked',
        'device_blacklisted', 'version_deprecated',
    ];

    public const STATUSES = ['pending', 'dispatched', 'confirmed', 'expired', 'cancelled'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeActive($q) { return $q->whereIn('status', ['pending', 'dispatched']); }
    public function scopeByInstance($q, $id) { return $q->where('sdk_instance_id', $id); }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
