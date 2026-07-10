<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLiveChatMessage
 */
class LiveChatMessage extends Model
{
    protected $table = 'live_chat_messages';

    protected $fillable = [
        'conversation_id', 'sender_type', 'sender_id', 'content', 'message_type',
        'attachments', 'metadata', 'sent_at', 'is_read', 'reply_to_id', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(LiveChatConversation::class, 'conversation_id'); }
}
