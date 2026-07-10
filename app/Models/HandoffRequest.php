<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 统一转接请求模型
 *
 * 管理 AI/在线客服 → 人工客服的转接队列、分配、处理和跟踪。
 * 支持两种来源：
 * - AI客服对话 (conversation_id → rag_conversations)
 * - 在线客服对话 (live_chat_conversation_id → live_chat_conversations)
 *
 * @mixin IdeHelperHandoffRequest
 */
class HandoffRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'conversation_id', 'live_chat_conversation_id',
        'customer_id', 'user_id',
        'assigned_to', 'ticket_id',
        'reason', 'status', 'priority',
        'queue_position', 'wait_time_seconds',
        'conversation_context', 'metadata',
        'queued_at', 'assigned_at', 'accepted_at', 'resolved_at', 'closed_at',
        'rating', 'rating_comment', 'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'conversation_context' => 'array',
            'metadata' => 'array',
            'queue_position' => 'integer',
            'wait_time_seconds' => 'integer',
            'rating' => 'integer',
            'queued_at' => 'datetime',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'rated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * AI客服对话（RAG）
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(RagConversation::class);
    }

    /**
     * 在线客服对话
     */
    public function liveChatConversation(): BelongsTo
    {
        return $this->belongsTo(LiveChatConversation::class, 'live_chat_conversation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(HandoffAction::class);
    }

    /**
     * 获取来源对话（自动判断 AI客服 or 在线客服）
     */
    public function sourceConversation(): ?Model
    {
        return $this->liveChatConversation ?? $this->conversation;
    }

    /**
     * 转接原因标签
     */
    public function reasonLabel(): string
    {
        return match ($this->reason) {
            'low_confidence' => 'AI置信度过低',
            'user_request' => '用户要求转人工',
            'sensitive_topic' => '敏感话题',
            'error_limit' => '连续错误',
            'complex_query' => '复杂查询',
            default => $this->reason,
        };
    }

    /**
     * 等待时间（格式化）
     */
    public function waitTimeFormatted(): string
    {
        if (!$this->wait_time_seconds) return '—';
        $m = intdiv($this->wait_time_seconds, 60);
        $s = $this->wait_time_seconds % 60;
        return $m > 0 ? "{$m}分{$s}秒" : "{$s}秒";
    }

    /**
     * 分配给客服
     */
    public function assignTo(User $agent): void
    {
        $this->update([
            'assigned_to' => $agent->id,
            'status' => 'assigned',
            'assigned_at' => now(),
            'wait_time_seconds' => (int) now()->diffInSeconds($this->queued_at ?? $this->created_at),
        ]);
    }

    /**
     * 客服接受
     */
    public function accept(): void
    {
        $this->update([
            'status' => 'in_progress',
            'accepted_at' => now(),
        ]);
    }

    /**
     * 解决/关闭
     */
    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    /**
     * 关闭
     */
    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * 重新打开
     */
    public function reopen(): void
    {
        $this->update([
            'status' => 'in_progress',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['queued', 'assigned', 'in_progress']);
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('assigned_to', $agentId)->whereIn('status', ['assigned', 'in_progress']);
    }
}
