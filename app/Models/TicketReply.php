<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'content', 'is_internal', 'attachments',
    ];

    protected $table = 'ticket_replies';

    protected $appends = ['is_admin'];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'attachments' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->user?->hasRole('admin') || $this->user?->hasRole('super-admin');
    }
}
