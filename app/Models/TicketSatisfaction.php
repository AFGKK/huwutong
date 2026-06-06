<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSatisfaction extends Model
{
    protected $fillable = [
        'ticket_id', 'customer_id', 'score', 'comment',
    ];

    protected $table = 'ticket_satisfactions';

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
