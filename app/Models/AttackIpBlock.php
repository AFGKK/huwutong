<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttackIpBlock extends Model
{
    protected $table = 'attack_ip_blocks';

    protected $fillable = [
        'ip', 'reason', 'attack_type', 'confidence',
        'blocked_at', 'expires_at', 'is_permanent',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'blocked_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_permanent' => 'boolean',
        ];
    }
}
