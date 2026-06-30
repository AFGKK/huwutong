<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 数据迁移记录
 *
 * @m3-60 DataResidency
 */
class DataMigration extends Model
{
    protected $fillable = [
        'tenant_id',
        'source_region',
        'target_region',
        'data_classification',
        'status',           // pending, running, completed, failed, rolled_back
        'total_items',
        'processed_items',
        'failed_items',
        'started_at',
        'completed_at',
        'performed_by',
        'audit_log',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'audit_log' => 'array',
        ];
    }
}
