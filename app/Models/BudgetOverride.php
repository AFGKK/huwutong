<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetOverride extends Model
{
    protected $fillable = [
        'budget_limit_id',
        'requested_amount',
        'override_percentage',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'override_percentage' => 'decimal:2',
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function budgetLimit(): BelongsTo
    {
        return $this->belongsTo(BudgetLimit::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function isValid(): bool
    {
        return $this->status === 'approved'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
