<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 租户加密凭据
 *
 * 存储 API Key、密码、证书等敏感信息的加密版本。
 * 所有值使用 envelope encryption 加密（DEK 由 KEK 加密）。
 */
class TenantSecret extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'slug', 'type',
        'encrypted_value', 'description', 'status',
        'expires_at', 'last_rotated_by', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(SecretAccessLog::class, 'secret_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiring($query, int $days = 7)
    {
        return $query->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    public function scopeByTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
