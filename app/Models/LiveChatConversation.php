<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperLiveChatConversation
 */
class LiveChatConversation extends Model
{
    protected $table = 'live_chat_conversations';

    protected $fillable = [
        'tenant_id', 'user_id', 'session_id', 'status', 'source',
        'department', 'assigned_to', 'rating', 'rating_comment',
        'assigned_at', 'closed_at', 'is_pinned', 'is_muted',
        'unread_count', 'draft_content', 'last_read_message_id',
        // 访客信息
        'visitor_ip', 'visitor_country', 'visitor_region', 'visitor_city',
        'visitor_isp', 'visitor_browser', 'visitor_os', 'visitor_device',
        'referrer_url', 'referrer_domain',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_pinned' => 'boolean',
            'is_muted' => 'boolean',
            'unread_count' => 'integer',
        ];
    }

    public function messages(): HasMany { return $this->hasMany(LiveChatMessage::class, 'conversation_id'); }
    public function handoffs(): HasMany { return $this->hasMany(LiveChatHandoff::class, 'conversation_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}
