<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrossBorderMonthlyReport extends Model
{
    use HasFactory;

    protected $table = 'cross_border_monthly_reports';

    protected $fillable = [
        'tenant_id', 'report_month', 'currency',
        'total_revenue', 'total_revenue_cny',
        'total_refunds', 'total_fees', 'total_fees_cny',
        'net_revenue', 'transaction_count', 'customer_count',
        'top_countries', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_revenue' => 'decimal:2',
            'total_revenue_cny' => 'decimal:2',
            'total_refunds' => 'decimal:2',
            'total_fees' => 'decimal:2',
            'total_fees_cny' => 'decimal:2',
            'net_revenue' => 'decimal:2',
            'top_countries' => 'array',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
