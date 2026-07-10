<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 批量操作子项
 *
 * @mixin IdeHelperBatchJobItem
 */
class BatchJobItem extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'batch_job_id', 'targetable_type', 'targetable_id',
        'status', 'error_message', 'result_data',
    ];

    protected function casts(): array
    {
        return [
            'result_data' => 'array',
        ];
    }

    public function batchJob(): BelongsTo
    {
        return $this->belongsTo(BatchJob::class);
    }

    public function targetable()
    {
        return $this->morphTo();
    }
}
