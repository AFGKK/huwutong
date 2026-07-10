<?php

namespace App\Models;

use App\Enums\AiMemorySource;
use App\Enums\AiMemoryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAiMemory
 */
class AiMemory extends Model
{
    use SoftDeletes;

    protected $table = 'ai_memories';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'key',
        'content',
        'type',
        'source',
        'confidence',
        'priority',
        'category',
        'tags',
        'memorable_type',
        'memorable_id',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'tags' => 'array',
        'confidence' => 'float',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
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

    public function memorable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── 作用域 ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeImportant($query, float $minConfidence = 0.6)
    {
        return $query->where('confidence', '>=', $minConfidence)
            ->orderBy('priority', 'desc')
            ->orderBy('confidence', 'desc');
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now());
    }

    // ── 辅助方法 ──

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function typeLabel(): string
    {
        $enum = AiMemoryType::tryFrom($this->type);
        return $enum?->label() ?? $this->type;
    }

    public function sourceLabel(): string
    {
        $enum = AiMemorySource::tryFrom($this->source);
        return $enum?->label() ?? $this->source;
    }

    public function summarize(int $maxLength = 100): string
    {
        return mb_strlen($this->content) > $maxLength
            ? mb_substr($this->content, 0, $maxLength) . '…'
            : $this->content;
    }
}
