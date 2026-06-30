<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalProxyConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'node_id',
        'sync_mode',
        'sync_interval_seconds',
        'heartbeat_interval_seconds',
        'cache_ttl_seconds',
        'max_cached_licenses',
        'allow_offline_activation',
        'require_cloud_validation',
        'allowed_actions',
        'ip_whitelist',
        'extra_settings',
    ];

    protected $casts = [
        'allow_offline_activation' => 'boolean',
        'require_cloud_validation' => 'boolean',
        'allowed_actions' => 'array',
        'ip_whitelist' => 'array',
        'extra_settings' => 'array',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(LocalProxyNode::class, 'node_id');
    }
}
