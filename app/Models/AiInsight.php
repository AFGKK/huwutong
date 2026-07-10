<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAiInsight
 */
class AiInsight extends Model
{
    protected $table = 'ai_insights';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'conversation_id',
        'message_id',
        'type',
        'title',
        'content',
        'context',
        'status',
        'sent_at',
        'read_at',
        'dismissed_at',
        'source',
    ];

    protected $casts = [
        'context' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    // ── 关联 ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── 作用域 ──

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnread($query)
    {
        return $query->whereIn('status', ['pending', 'sent']);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    // ── 辅助 ──

    public function markAsSent(): void
    {
        $this->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function markAsRead(): void
    {
        $this->update(['status' => 'read', 'read_at' => now()]);
    }

    public function markAsDismissed(): void
    {
        $this->update(['status' => 'dismissed', 'dismissed_at' => now()]);
    }
}
