<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacheInvalidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'invalidation_key',
        'type',
        'context',
        'status',
        'attempts',
        'published_at',
        'last_attempt_at',
        'last_error',
        'channel',
        'group_hash',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'attempts' => 'integer',
            'published_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    const TYPES = [
        'license_status' => 'License 状态变更',
        'feature_flag' => 'Feature Flag 变更',
        'product_config' => '产品配置变更',
        'heartbeat' => '心跳检查',
    ];

    const CHANNELS = [
        'reverb' => 'WebSocket (Reverb)',
        'webhook' => 'Webhook 回调',
        'sse' => 'Server-Sent Events',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PUBLISHED = 'published';
    const STATUS_FAILED = 'failed';
    const STATUS_MERGED = 'merged';

    // ─── 关系 ─────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // ─── 作用域 ────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
