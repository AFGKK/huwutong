<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DunningQueue extends Model
{
    protected $table = 'dunning_queue';

    protected $fillable = [
        'subscription_id', 'invoice_id', 'customer_id', 'tenant_id',
        'dunning_strategy_id',
        'attempt_count', 'current_stage', 'status',
        'amount_due', 'currency',
        'next_action_at', 'last_action_at', 'enqueued_at', 'resolved_at',
        'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'current_stage' => 'integer',
            'amount_due' => 'decimal:2',
            'next_action_at' => 'datetime',
            'last_action_at' => 'datetime',
            'enqueued_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(DunningStrategy::class, 'dunning_strategy_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DunningLog::class, 'dunning_queue_id');
    }

    /**
     * 是否仍在活跃催缴中
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'in_progress']);
    }

    /**
     * 升级到下一阶段
     */
    public function advanceToNextStage(): void
    {
        $this->increment('current_stage');
        $this->increment('attempt_count');
        $this->status = 'in_progress';
        $this->save();
    }
}
