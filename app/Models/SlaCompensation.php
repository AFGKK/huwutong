<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSlaCompensation
 */
class SlaCompensation extends Model
{
    use HasFactory;

    protected $table = 'sla_compensations';

    protected $fillable = [
        'sla_contract_id', 'sla_breach_id', 'tenant_id', 'customer_id',
        'compensation_type', 'severity', 'amount', 'currency',
        'reason', 'calculation_method', 'status',
        'approved_at', 'approved_by', 'issued_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    const TYPES = ['credit', 'discount', 'extension', 'refund'];
    const SEVERITIES = ['minor', 'major', 'critical'];
    const STATUSES = ['pending', 'approved', 'issued', 'rejected'];

    const TYPE_LABELS = [
        'credit' => '信用额度',
        'discount' => '折扣',
        'extension' => '服务延长',
        'refund' => '退款',
    ];

    // 基于违约严重度的默认补偿金额（CREDIT）
    const SEVERITY_AMOUNTS = [
        'minor' => 50,
        'major' => 200,
        'critical' => 500,
    ];

    // 基于违约严重度的服务延长天数
    const SEVERITY_EXTENSION_DAYS = [
        'minor' => 3,
        'major' => 7,
        'critical' => 15,
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SlaContract::class, 'sla_contract_id');
    }

    public function breach(): BelongsTo
    {
        return $this->belongsTo(SlaBreach::class, 'sla_breach_id');
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
}
