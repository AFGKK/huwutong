<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperBiDataset
 */
class BiDataset extends Model
{
    protected $table = 'bi_datasets';

    protected $fillable = [
        'tenant_id', 'bi_connection_id', 'name', 'source_table',
        'sync_frequency', 'status', 'field_mapping', 'filters', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'field_mapping' => 'array',
            'filters' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    const SOURCE_TABLES = [
        'licenses'      => 'License 数据',
        'customers'     => '客户数据',
        'orders'        => '订单数据',
        'invoices'      => '发票数据',
        'subscriptions' => '订阅数据',
    ];

    const FREQUENCIES = [
        'manual'  => '手动',
        'hourly'  => '每小时',
        'daily'   => '每天',
        'weekly'  => '每周',
        'monthly' => '每月',
    ];

    public function connection(): BelongsTo { return $this->belongsTo(BiConnection::class, 'bi_connection_id'); }
    public function syncLogs(): HasMany { return $this->hasMany(BiSyncLog::class, 'bi_dataset_id'); }
}
