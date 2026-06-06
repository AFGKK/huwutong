<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalEscalation extends Model
{
    protected $fillable = [
        'subscription_id', 'channel', 'status',
        'contact', 'message', 'sent_at',
        'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
