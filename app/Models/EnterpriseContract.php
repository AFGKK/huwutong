<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnterpriseContract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contract_number', 'name', 'customer_id',
        'status', 'total_value', 'currency', 'discount_rate', 'negotiated_amount',
        'start_date', 'end_date', 'billing_cycle_days',
        'licensed_items', 'terms', 'special_terms',
        'approval_status', 'approval_notes', 'approved_by', 'approved_at',
        'signed_document_path', 'signed_document_name',
        'auto_renew', 'renewal_notice_days', 'renewed_contract_id',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'negotiated_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'billing_cycle_days' => 'integer',
            'licensed_items' => 'array',
            'terms' => 'array',
            'special_terms' => 'array',
            'auto_renew' => 'boolean',
            'renewal_notice_days' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    const STATUSES = ['draft', 'pending_approval', 'active', 'expired', 'terminated'];
    const APPROVAL_STATUSES = ['pending', 'approved', 'rejected'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function renewedContract()
    {
        return $this->belongsTo(self::class, 'renewed_contract_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->start_date <= now()->toDateString()
            && $this->end_date >= now()->toDateString();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString());
    }
}
