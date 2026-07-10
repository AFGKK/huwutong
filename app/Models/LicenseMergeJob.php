<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLicenseMergeJob
 */
class LicenseMergeJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'source_customer_id',
        'target_customer_id',
        'status',
        'total_licenses',
        'merged_licenses',
        'skipped_licenses',
        'failed_licenses',
        'total_devices',
        'migrated_devices',
        'summary',
        'errors',
        'conflict_resolution',
        'merge_audit',
        'merged_by',
        'merged_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'errors' => 'array',
            'conflict_resolution' => 'array',
            'merge_audit' => 'array',
            'merged_at' => 'datetime',
            'total_licenses' => 'integer',
            'merged_licenses' => 'integer',
            'skipped_licenses' => 'integer',
            'failed_licenses' => 'integer',
            'total_devices' => 'integer',
            'migrated_devices' => 'integer',
        ];
    }

    const STATUSES = [
        'pending' => '待处理',
        'previewed' => '已预览',
        'completed' => '已完成',
        'failed' => '失败',
        'rolled_back' => '已回滚',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'source_customer_id');
    }

    public function targetCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'target_customer_id');
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    public function isProcessable(): bool
    {
        return in_array($this->status, ['pending', 'previewed']);
    }
}
