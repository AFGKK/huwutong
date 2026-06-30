<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizationReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'tenant_id',
        'reservation_token',
        'fingerprint',
        'ip_address',
        'payload',
        'status',
        'expires_at',
        'committed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logs()
    {
        return $this->hasMany(AuthorizationReservationLog::class, 'reservation_id');
    }

    /**
     * 预留是否仍然有效（未过期且状态为 reserved）
     */
    public function isValid(): bool
    {
        return $this->status === 'reserved' && $this->expires_at->isFuture();
    }

    /**
     * 预留是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * 预留是否可提交
     */
    public function isCommittable(): bool
    {
        return $this->status === 'reserved' && !$this->isExpired();
    }

    /**
     * 预留是否可取消
     */
    public function isCancellable(): bool
    {
        return $this->status === 'reserved';
    }
}
