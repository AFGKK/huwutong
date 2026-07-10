<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperDataRetentionAudit
 */
class DataRetentionAudit extends Model
{
    protected $table = 'data_retention_audits';

    protected $fillable = [
        'type', 'retention_days',
        'total_logs_before', 'pruned_count', 'total_logs_after',
        'status', 'notes', 'initiated_by', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
