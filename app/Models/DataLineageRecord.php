<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DataLineageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'trackable_type',
        'trackable_id',
        'trackable_label',
        'data_category',
        'sensitivity',
        'event_type',
        'event_label',
        'source_system',
        'source_ip',
        'source_user_agent',
        'actor_id',
        'actor_type',
        'target_system',
        'changes',
        'metadata',
        'parent_record_id',
        'trace_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public const DATA_CATEGORIES = [
        'license_key' => 'License Key',
        'pii' => '客户个人信息',
        'device_fingerprint' => '设备指纹',
        'subscription' => '订阅信息',
        'payment' => '支付信息',
        'api_key' => 'API Key',
        'configuration' => '系统配置',
    ];

    public const SENSITIVITY_LEVELS = [
        'public' => '公开',
        'internal' => '内部',
        'confidential' => '机密',
        'restricted' => '限制',
    ];

    public const EVENT_TYPES = [
        'created' => '创建',
        'read' => '读取',
        'updated' => '更新',
        'exported' => '导出',
        'archived' => '归档',
        'deleted' => '删除',
        'activated' => '激活',
        'validated' => '验证',
        'revoked' => '撤销',
        'drifted' => '指纹漂移',
        'transferred' => '转移',
        'merged' => '合并',
        'restored' => '恢复',
    ];

    // ── 关系 ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_record_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_record_id');
    }

    // ── 作用域 ──

    public function scopeByTrackable($query, string $type, string $id)
    {
        return $query->where('trackable_type', $type)->where('trackable_id', $id);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('data_category', $category);
    }

    public function scopeByEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByTrace($query, string $traceId)
    {
        return $query->where('trace_id', $traceId);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByActor($query, $actorId, ?string $actorType = null)
    {
        $query->where('actor_id', $actorId);
        if ($actorType) {
            $query->where('actor_type', $actorType);
        }
        return $query;
    }

    /**
     * 获取关联的可溯源模型
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo(null, 'trackable_type', 'trackable_id');
    }
}
