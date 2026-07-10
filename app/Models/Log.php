<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperLog
 */
class Log extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'license_id', 'customer_id', 'device_id', 'product_id',
        'type', 'action', 'description', 'payload', 'ip_address', 'user_agent',
        'merkle_hash', 'merkle_parent_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    // ─── Relations ───

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** 审计日志标签 */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(AuditLogTag::class, 'audit_log_tag_log', 'log_id', 'tag_id');
    }

    /** 审计日志备注 */
    public function annotations(): HasMany
    {
        return $this->hasMany(AuditLogAnnotation::class, 'log_id');
    }

    // ─── Scopes ───

    /**
     * 按类型筛选
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * 按动作筛选
     */
    public function scopeOfAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * 按动作前缀筛选（如 license.*）
     */
    public function scopeOfActionPrefix(Builder $query, string $prefix): Builder
    {
        return $query->where('action', 'like', $prefix . '%');
    }

    /**
     * 按用户筛选
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 按租户筛选
     */
    public function scopeByTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * 日期范围筛选
     */
    public function scopeDateRange(Builder $query, string $from, ?string $to = null): Builder
    {
        $query->where('created_at', '>=', $from);
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        return $query;
    }

    /**
     * 描述全文搜索
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where('description', 'like', '%' . $keyword . '%');
    }

    /**
     * 按关联对象筛选（license / customer / device / product）
     */
    public function scopeRelatedTo(Builder $query, string $relation, int $id): Builder
    {
        $allowed = ['license_id', 'customer_id', 'device_id', 'product_id'];
        $column = $relation . '_id';
        if (in_array($column, $allowed)) {
            return $query->where($column, $id);
        }
        return $query;
    }

    /**
     * JSON payload 字段筛选
     *
     * 示例: payload->license_key = 'abc'
     */
    public function scopeWherePayload(Builder $query, string $key, $value): Builder
    {
        return $query->where('payload->' . $key, $value);
    }

    /**
     * 最近的记录优先
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}
