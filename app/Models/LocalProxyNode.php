<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocalProxyNode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'node_id',
        'register_token',
        'api_key',
        'base_url',
        'version',
        'os',
        'architecture',
        'capabilities',
        'status',
        'last_heartbeat_at',
        'registered_at',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'last_heartbeat_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(LocalProxyHeartbeat::class, 'node_id');
    }

    public function cachedLicenses(): HasMany
    {
        return $this->hasMany(LocalProxyCachedLicense::class, 'node_id');
    }

    public function activationLogs(): HasMany
    {
        return $this->hasMany(LocalProxyActivationLog::class, 'node_id');
    }

    public function config(): HasMany
    {
        return $this->hasMany(LocalProxyConfig::class, 'node_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isHealthy(): bool
    {
        if (!$this->last_heartbeat_at) {
            return false;
        }
        return $this->last_heartbeat_at->diffInMinutes() <= 10;
    }
}
