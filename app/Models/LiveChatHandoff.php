<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLiveChatHandoff
 */
class LiveChatHandoff extends Model
{
    protected $table = 'live_chat_handoffs';

    protected $fillable = [
        'conversation_id', 'reason', 'status', 'agent_id', 'notes',
        'handoff_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'handoff_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(LiveChatConversation::class, 'conversation_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(User::class, 'agent_id'); }
}
