<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementCycle extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'period_type',
        'period_start',
        'period_end',
        'settlement_date',
        'payout_date',
        'status',
        'total_commission',
        'total_fee',
        'total_payout',
        'agent_count',
        'settlement_count',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'settlement_date' => 'date',
            'payout_date' => 'date',
            'total_commission' => 'decimal:2',
            'total_fee' => 'decimal:2',
            'total_payout' => 'decimal:2',
        ];
    }

    public const PERIOD_TYPES = ['weekly', 'bi-weekly', 'monthly'];
    public const STATUSES = ['pending', 'processing', 'settled', 'paid', 'cancelled'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(SettlementBatch::class, 'settlement_cycle_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class, 'settlement_cycle_id');
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }

    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }
}
