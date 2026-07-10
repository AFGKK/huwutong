<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCleanupSchedule
 */
class CleanupSchedule extends Model
{
    protected $fillable = [
        'data_source',
        'frequency',
        'time_of_day',
        'day_of_week',
        'batch_size',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'batch_size' => 'integer',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'frequency' => 'daily',
        'time_of_day' => '02:00',
        'batch_size' => 1000,
        'is_active' => true,
    ];
}
