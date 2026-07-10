<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperConversationMessage
 */
class ConversationMessage extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'message_type', 'content', 'attachments', 'metadata', 'reply_to_id', 'is_edited', 'edited_at', 'deleted_at', 'client_msg_id', 'thread_parent_id', 'thread_reply_count', 'is_pinned', 'pinned_at', 'pinned_by', 'deliver_status', 'read_at', 'delivered_at', 'confirmed_at', 'is_recalled', 'expires_at'];

    protected $appends = ['read_count'];

    protected function casts(): array {
        return [
            'attachments' => 'array',
            'metadata' => 'array',
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'thread_reply_count' => 'integer',
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
            'read_at' => 'datetime',
            'delivered_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'is_recalled' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    // ── 过期作用域 ──

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('deleted_at');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now())
            ->whereNull('deleted_at');
    }

    // ── 辅助 ──

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && $this->deleted_at === null;
    }

    /**
     * 获取消息已读人数（基于会话参与者的 last_read_at）
     */
    public function getReadCountAttribute(): int
    {
        return ConversationParticipant::where('conversation_id', $this->conversation_id)
            ->whereNull('deleted_at')
            ->where('last_read_at', '>=', $this->created_at)
            ->count();
    }

    public function conversation(): BelongsTo { return $this->belongsTo(UserConversation::class, 'conversation_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function replyTo(): BelongsTo { return $this->belongsTo(self::class, 'reply_to_id'); }
}
