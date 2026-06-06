<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditChainAnchor extends Model
{
    protected $fillable = [
        'root_hash',
        'prev_root_hash',
        'anchored_at',
        'anchor_type',
        'anchor_ref',
        'log_count',
        'from_log_id',
        'to_log_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'anchored_at' => 'datetime',
            'log_count' => 'integer',
            'metadata' => 'array',
        ];
    }
}
