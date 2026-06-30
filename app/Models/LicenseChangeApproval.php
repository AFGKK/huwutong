<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $license_id
 * @property string $action
 * @property string $status
 * @property array $request_data
 * @property array|null $current_snapshot
 * @property string|null $reason
 * @property int $requested_by
 * @property int|null $approved_by
 * @property string|null $approved_at
 * @property string|null $reject_reason
 * @property string $expires_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class LicenseChangeApproval extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_id',
        'action',
        'status',
        'request_data',
        'current_snapshot',
        'reason',
        'requested_by',
        'approved_by',
        'approved_at',
        'reject_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'current_snapshot' => 'array',
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->gt($this->expires_at);
    }
}
