<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformFee extends Model
{
    protected $fillable = [
        'tenant_id',
        'feeable_type',
        'feeable_id',
        'fee_type',
        'name',
        'amount',
        'rate',
        'currency',
        'status',
        'metadata',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'metadata' => 'json',
            'collected_at' => 'datetime',
        ];
    }

    public const FEE_TYPES = ['gateway', 'platform', 'commission', 'withdrawal', 'refund'];
    public const STATUSES = ['pending', 'collected', 'waived', 'refunded'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function feeable()
    {
        return $this->morphTo();
    }

    public function scopeByType($query, $type)
    {
        return $query->where('fee_type', $type);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
