<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTaxExemptCertificate
 */
class TaxExemptCertificate extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'certificate_type',
        'certificate_number', 'issuing_country', 'reason',
        'status', 'valid_from', 'valid_until',
        'document_file', 'notes', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * 检查证书是否有效（状态 + 日期）
     */
    public function isValid(): bool
    {
        return $this->status === 'approved'
            && $this->valid_from <= now()
            && $this->valid_until >= now();
    }
}
