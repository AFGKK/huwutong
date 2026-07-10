<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperBiSyncLog
 */
class BiSyncLog extends Model
{
    protected $table = 'bi_sync_logs';

    protected $fillable = [
        'bi_dataset_id', 'status', 'total_records', 'synced_records',
        'error_message', 'details', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function dataset(): BelongsTo { return $this->belongsTo(BiDataset::class, 'bi_dataset_id'); }
}
