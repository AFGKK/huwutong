<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperSyntheticMonitorRegion
 */
class SyntheticMonitorRegion extends Model
{
    protected $fillable = [
        'code', 'name', 'name_en', 'locations', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'locations' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
