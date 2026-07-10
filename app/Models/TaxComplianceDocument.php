<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTaxComplianceDocument
 */
class TaxComplianceDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'document_type', 'country',
        'title', 'reference_number', 'document_date', 'due_date',
        'status', 'file_path', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    const DOCUMENT_TYPES = [
        'tax_return' => '纳税申报表',
        'filing_receipt' => '申报回执',
        'correspondence' => '税局通信',
        'certificate' => '税务证明',
        'audit_letter' => '审计函件',
    ];

    const STATUSES = [
        'pending' => '待处理',
        'completed' => '已完成',
        'overdue' => '逾期',
        'archived' => '已归档',
    ];
}
