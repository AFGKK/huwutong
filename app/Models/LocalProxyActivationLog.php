<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalProxyActivationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'node_id',
        'license_id',
        'license_key',
        'fingerprint',
        'action',
        'result',
        'reason',
        'client_ip',
        'metadata',
        'synced_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'synced_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(LocalProxyNode::class, 'node_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
