<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttackEvent extends Model
{
    protected $table = 'attack_events';

    protected $fillable = [
        'tenant_id', 'attack_type', 'severity', 'confidence',
        'source_ip', 'target', 'method', 'path', 'description',
        'raw_data', 'context', 'ai_analysis', 'status',
        'action_taken', 'detected_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'raw_data' => 'array',
            'context' => 'array',
            'ai_analysis' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
