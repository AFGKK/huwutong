<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DunningLog extends Model
{
    protected $table = 'dunning_logs';

    protected $fillable = [
        'dunning_queue_id', 'subscription_id', 'invoice_id',
        'attempt_number', 'action_taken', 'channel', 'success',
        'request_data', 'response_data', 'error_message',
        'next_stage_planned', 'next_action_planned_at', 'actioned_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'success' => 'boolean',
            'next_action_planned_at' => 'datetime',
            'actioned_at' => 'datetime',
        ];
    }

    public function dunningQueue(): BelongsTo
    {
        return $this->belongsTo(DunningQueue::class, 'dunning_queue_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
