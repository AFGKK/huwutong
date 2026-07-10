<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 批量操作任务
 *
 * 支持批量激活/续期/导出/挂起/吊销/删除/变更计划等操作
 * 包含筛选条件、操作参数、执行进度、结果统计
 *
 * @mixin IdeHelperBatchJob
 */
class BatchJob extends Model
{
    // 操作类型
    const TYPE_BATCH_ACTIVATE = 'batch_activate';
    const TYPE_BATCH_RENEW = 'batch_renew';
    const TYPE_BATCH_EXPORT = 'batch_export';
    const TYPE_BATCH_SUSPEND = 'batch_suspend';
    const TYPE_BATCH_REVOKE = 'batch_revoke';
    const TYPE_BATCH_DELETE = 'batch_delete';
    const TYPE_BATCH_CHANGE_PLAN = 'batch_change_plan';
    const TYPE_BATCH_CHANGE_STATUS = 'batch_change_status';

    // 目标模型
    const TARGET_LICENSES = 'licenses';
    const TARGET_SUBSCRIPTIONS = 'subscriptions';
    const TARGET_CUSTOMERS = 'customers';
    const TARGET_INVOICES = 'invoices';
    const TARGET_TICKETS = 'tickets';

    // 状态
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'target_model',
        'filters', 'ids', 'params',
        'total_count', 'success_count', 'fail_count',
        'status', 'error_summary', 'result_summary',
        'export_path',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'ids' => 'array',
            'params' => 'array',
            'result_summary' => 'array',
            'total_count' => 'integer',
            'success_count' => 'integer',
            'fail_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BatchJobItem::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(BatchSnapshot::class);
    }

    /**
     * 是否可撤销
     */
    public function isReversible(): bool
    {
        return in_array($this->type, [
            self::TYPE_BATCH_ACTIVATE,
            self::TYPE_BATCH_RENEW,
            self::TYPE_BATCH_SUSPEND,
            self::TYPE_BATCH_REVOKE,
            self::TYPE_BATCH_CHANGE_STATUS,
        ]);
    }

    /**
     * 是否已完成
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }
}
