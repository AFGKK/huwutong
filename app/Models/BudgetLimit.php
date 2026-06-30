<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BudgetLimit extends Model
{
    protected $fillable = [
        'budgetable_type',
        'budgetable_id',
        'period',
        'budget_amount',
        'currency',
        'spent_amount',
        'pending_amount',
        'status',
        'period_start_at',
        'period_end_at',
        'last_alert_at',
        'notifications_enabled',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'pending_amount' => 'decimal:2',
            'period_start_at' => 'datetime',
            'period_end_at' => 'datetime',
            'last_alert_at' => 'datetime',
            'notifications_enabled' => 'boolean',
        ];
    }

    public function budgetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(BudgetOverride::class);
    }

    public function usagePercentage(): float
    {
        if ($this->budget_amount <= 0) return 0;
        return round(($this->spent_amount + $this->pending_amount) / $this->budget_amount * 100, 2);
    }

    public function isExceeded(): bool
    {
        return $this->usagePercentage() >= 100;
    }

    public function remaining(): float
    {
        return max(0, $this->budget_amount - $this->spent_amount - $this->pending_amount);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('budgetable_type', 'customer')->where('budgetable_id', $customerId);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('budgetable_type', 'tenant')->where('budgetable_id', $tenantId);
    }
}
