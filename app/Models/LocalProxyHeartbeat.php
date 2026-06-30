<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalProxyHeartbeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'node_id',
        'heartbeat_at',
        'metrics',
        'cache_stats',
        'status',
        'error_message',
    ];

    protected $casts = [
        'heartbeat_at' => 'datetime',
        'metrics' => 'array',
        'cache_stats' => 'array',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(LocalProxyNode::class, 'node_id');
    }
}
