<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTaxComplianceReport
 */
class TaxComplianceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'report_type', 'status',
        'country', 'period', 'period_start', 'period_end',
        'total_sales', 'total_tax_collected', 'total_tax_payable',
        'total_exempt_sales', 'total_reverse_charge',
        'breakdown', 'notes', 'filed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_sales' => 'decimal:2',
            'total_tax_collected' => 'decimal:2',
            'total_tax_payable' => 'decimal:2',
            'total_exempt_sales' => 'decimal:2',
            'total_reverse_charge' => 'decimal:2',
            'breakdown' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'filed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    const REPORT_TYPES = [
        'vat_return' => 'VAT 申报',
        'gst_return' => 'GST 申报',
        'sales_tax' => '销售税申报',
        'cross_border' => '跨境交易报告',
        'liability_summary' => '税务负债汇总',
    ];

    const STATUSES = [
        'draft' => '草稿',
        'final' => '待提交',
        'filed' => '已申报',
    ];
}
