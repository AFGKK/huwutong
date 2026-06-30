<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalProxyCachedLicense extends Model
{
    use HasFactory;

    protected $fillable = [
        'node_id',
        'license_id',
        'license_key',
        'cache_key',
        'cached_payload',
        'cached_at',
        'expires_at',
        'last_verified_at',
        'verify_count',
    ];

    protected $casts = [
        'cached_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'verify_count' => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(LocalProxyNode::class, 'node_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
