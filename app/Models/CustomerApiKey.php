<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'key',
        'prefix',
        'abilities',
        'ip_whitelist',
        'expires_at',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeByPrefix($query, string $prefix)
    {
        return $query->where('prefix', $prefix);
    }

    public function hasAbility(string $ability): bool
    {
        if (empty($this->abilities)) {
            return true; // 无限制
        }
        return in_array('*', $this->abilities) || in_array($ability, $this->abilities);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public static function generateKey(string $prefix = 'hwt_'): string
    {
        return $prefix . Str::random(48 - strlen($prefix));
    }

    /** 截断显示 Key（仅显示前 10 位） */
    public function getMaskedKeyAttribute(): string
    {
        return $this->prefix . '****' . substr($this->key, -6);
    }
}
