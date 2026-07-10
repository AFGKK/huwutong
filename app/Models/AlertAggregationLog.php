<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAlertAggregationLog
 */
class AlertAggregationLog extends Model
{
    protected $fillable = [
        'parent_event_id', 'child_event_id', 'group_key', 'reason',
    ];

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(AlertEvent::class, 'parent_event_id');
    }

    public function childEvent(): BelongsTo
    {
        return $this->belongsTo(AlertEvent::class, 'child_event_id');
    }
}
