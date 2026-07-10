<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTicketSlaEvent
 */
class TicketSlaEvent extends Model
{
    protected $fillable = [
        'ticket_id', 'event_type', 'triggered_at', 'notified',
    ];

    protected $table = 'ticket_sla_events';

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'notified' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
