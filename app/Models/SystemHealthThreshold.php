<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthThreshold extends Model
{
    protected $table = 'system_health_thresholds';

    protected $fillable = [
        'metric', 'label', 'warning_threshold',
        'critical_threshold', 'unit', 'comparison', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'warning_threshold' => 'decimal:2',
            'critical_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
