<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperOwnershipTransferRequest
 */
class OwnershipTransferRequest extends Model
{
    protected $fillable = [
        'reference', 'tenant_id', 'transferable_type', 'transferable_id',
        'source_customer_id', 'target_customer_id', 'status',
        'transfer_fee', 'fee_currency',
        'source_info', 'migration_summary', 'audit_log',
        'source_notes', 'target_notes', 'admin_notes',
        'requested_by', 'source_confirmed_by', 'source_confirmed_at',
        'target_confirmed_by', 'target_confirmed_at',
        'approved_by', 'approved_at', 'cancelled_by', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'source_info' => 'array',
            'migration_summary' => 'array',
            'audit_log' => 'array',
            'transfer_fee' => 'decimal:2',
            'source_confirmed_at' => 'datetime',
            'target_confirmed_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function sourceCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'source_customer_id');
    }

    public function targetCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'target_customer_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function sourceConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_confirmed_by');
    }

    public function targetConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_confirmed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function transferRecords(): HasMany
    {
        return $this->hasMany(OwnershipTransferRecord::class, 'transfer_request_id');
    }

    /**
     * 获取转移对象（多态）
     */
    public function transferable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('transferable', 'transferable_type', 'transferable_id');
    }

    public function isProcessable(): bool
    {
        return in_array($this->status, ['pending_source', 'pending_target']);
    }

    public function isConfirmed(): bool
    {
        return $this->source_confirmed_at && $this->target_confirmed_at;
    }
}
