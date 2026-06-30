<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TamperProtectionConfig extends Model
{
    protected $table = 'tamper_protection_configs';

    protected $fillable = [
        'rule_name', 'rule_type', 'conditions', 'actions',
        'severity', 'is_active', 'cooldown_seconds', 'threshold', 'description',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
