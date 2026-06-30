<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationPoll extends Model
{
    protected $fillable = [
        'conversation_id', 'creator_id', 'question', 'options',
        'type', 'is_anonymous', 'expires_at', 'is_closed',
    ];

    protected $casts = [
        'options' => 'array',
        'is_anonymous' => 'boolean',
        'is_closed' => 'boolean',
        'expires_at' => 'datetime',
    ];

    const TYPES = ['single' => '单选', 'multiple' => '多选'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(UserConversation::class, 'conversation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ConversationPollVote::class, 'poll_id');
    }

    public function hasVoted(int $userId): bool
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    public function results(): array
    {
        $options = $this->options;
        $totalVotes = $this->votes()->count();
        foreach ($options as &$opt) {
            $opt['count'] = $this->votes()
                ->whereJsonContains('selected_options', $opt['key'])
                ->count();
            $opt['percentage'] = $totalVotes > 0 ? round(($opt['count'] / $totalVotes) * 100) : 0;
        }
        return $options;
    }
}
