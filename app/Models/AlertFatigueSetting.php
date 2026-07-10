<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperAlertFatigueSetting
 */
class AlertFatigueSetting extends Model
{
    protected $fillable = [
        'source_type', 'repetition_threshold', 'decay_factor',
        'auto_downgrade', 'target_severity', 'is_active',
    ];

    protected $casts = [
        'repetition_threshold' => 'integer',
        'decay_factor' => 'decimal:2',
        'auto_downgrade' => 'boolean',
        'is_active' => 'boolean',
    ];
}
