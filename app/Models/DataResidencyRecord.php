<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 数据本地化区域配置模型
 *
 * 记录每个租户的数据存储区域分配和迁移记录
 *
 * @m3-60 DataResidency
 * @mixin IdeHelperDataResidencyRecord
 */
class DataResidencyRecord extends Model
{
    protected $fillable = [
        'tenant_id',
        'region_code',
        'data_classification',
        'storage_driver',
        'bucket',
        'cdn_domain',
        'encryption_enabled',
        'retention_days',
        'status',       // active, migrating, failed
        'migration_id',
        'migrated_at',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'encryption_enabled' => 'boolean',
            'retention_days' => 'integer',
            'migrated_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeByRegion($query, string $region)
    {
        return $query->where('region_code', $region);
    }

    public function scopeByClassification($query, string $classification)
    {
        return $query->where('data_classification', $classification);
    }
}
