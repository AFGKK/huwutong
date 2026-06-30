<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageSnapshot extends Model
{
    protected $table = 'tenant_usage_snapshots';

    protected $fillable = [
        'tenant_id', 'metric_key', 'current_usage',
        'quota_limit', 'usage_percent', 'period', 'snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
            'usage_percent' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
