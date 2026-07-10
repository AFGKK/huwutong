<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperRateLimitStat
 */
class RateLimitStat extends Model
{
    protected $fillable = [
        'rule_slug', 'dimension', 'hit_count',
        'blocked_count', 'window_start', 'window_end',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
        ];
    }
}
