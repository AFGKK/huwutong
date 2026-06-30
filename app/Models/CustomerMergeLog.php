<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMergeLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'source_customer_id',
        'target_customer_id',
        'status',
        'conflict_resolution',
        'summary',
        'errors',
        'merged_by',
        'merged_at',
        'reversed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'conflict_resolution' => 'array',
            'summary' => 'array',
            'errors' => 'array',
            'merged_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

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
}
