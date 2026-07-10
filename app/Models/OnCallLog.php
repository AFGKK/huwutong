<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOnCallLog
 */
class OnCallLog extends Model
{
    protected $fillable = [
        'on_call_entry_id', 'alert_event_id', 'user_id',
        'action', 'channel', 'status', 'details',
    ];

    protected $casts = ['details' => 'array'];

    public function onCallEntry(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OnCallEntry::class, 'on_call_entry_id');
    }
}
