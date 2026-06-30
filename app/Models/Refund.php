<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'license_id',
        'invoice_id',
        'customer_id',
        'processed_by',
        'refund_no',
        'amount',
        'currency',
        'reason',
        'customer_notes',
        'attachments',
        'reject_reason',
        'status',
        'refund_type',
        'payment_refund_id',
        'payment_method',
        'risk_assessment_id',
        'auto_decision',
        'approved_by',
        'approved_at',
        'customer_requested_at',
        'failure_reason',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(RefundRiskAssessment::class, 'risk_assessment_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
