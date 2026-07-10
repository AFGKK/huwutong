<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 系统检查记录
 *
 * @mixin IdeHelperStatusUptimeRecord
 */
class StatusUptimeRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'component_slug', 'is_up', 'latency_ms', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'latency_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }
}
