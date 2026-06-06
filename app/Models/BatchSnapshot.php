<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 批量操作快照（用于撤销回滚）
 */
class BatchSnapshot extends Model
{
    protected $fillable = [
        'batch_job_id', 'targetable_type', 'targetable_id',
        'field', 'old_value', 'new_value',
    ];

    public function batchJob(): BelongsTo
    {
        return $this->belongsTo(BatchJob::class);
    }

    public function targetable()
    {
        return $this->morphTo();
    }
}
