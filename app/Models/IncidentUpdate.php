<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 事件更新
 *
 * @mixin IdeHelperIncidentUpdate
 */
class IncidentUpdate extends Model
{
    protected $fillable = [
        'incident_id', 'status', 'message',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(StatusIncident::class, 'incident_id');
    }
}
