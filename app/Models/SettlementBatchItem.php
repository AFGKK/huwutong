<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementBatchItem extends Model
{
    protected $fillable = [
        'settlement_batch_id',
        'settleable_type',
        'settleable_id',
        'amount',
        'fee',
        'net_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public const STATUSES = ['pending', 'included', 'paid', 'failed', 'refunded'];

    public function settlementBatch(): BelongsTo
    {
        return $this->belongsTo(SettlementBatch::class);
    }

    public function settleable()
    {
        return $this->morphTo();
    }
}
