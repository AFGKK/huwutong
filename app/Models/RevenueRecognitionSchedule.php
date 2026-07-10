<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperRevenueRecognitionSchedule
 */
class RevenueRecognitionSchedule extends Model
{
    protected $fillable = [
        'tenant_id', 'invoice_id', 'subscription_id',
        'revenue_type', 'billing_period',
        'total_amount', 'recognized_amount', 'deferred_amount',
        'currency', 'start_date', 'end_date',
        'total_periods', 'recognized_periods',
        'recognition_method', 'status',
        'last_recognized_at', 'completed_at', 'cancelled_at',
        'cancel_reason', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'recognized_amount' => 'decimal:2',
            'deferred_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'total_periods' => 'integer',
            'recognized_periods' => 'integer',
            'last_recognized_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RevenueRecognitionLine::class, 'schedule_id');
    }

    public function getProgressAttribute(): float
    {
        if ($this->total_amount <= 0) {
            return 0;
        }
        return round(($this->recognized_amount / $this->total_amount) * 100, 1);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
