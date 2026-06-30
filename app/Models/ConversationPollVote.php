<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationPollVote extends Model
{
    protected $fillable = ['poll_id', 'user_id', 'selected_options'];

    protected $casts = ['selected_options' => 'array'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(ConversationPoll::class, 'poll_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
