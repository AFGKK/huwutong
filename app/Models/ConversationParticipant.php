<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperConversationParticipant
 */
class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'role', 'unread_count', 'last_read_at', 'is_pinned', 'is_muted', 'deleted_at', 'slow_mode_until', 'archived_at', 'is_hidden', 'hidden_at', 'request_status', 'request_responded_at'];
    protected function casts(): array { return ['unread_count' => 'integer', 'is_pinned' => 'boolean', 'is_muted' => 'boolean', 'last_read_at' => 'datetime', 'deleted_at' => 'datetime', 'slow_mode_until' => 'datetime', 'archived_at' => 'datetime', 'is_hidden' => 'boolean', 'hidden_at' => 'datetime', 'request_responded_at' => 'datetime']; }

    public function conversation(): BelongsTo { return $this->belongsTo(UserConversation::class, 'conversation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
