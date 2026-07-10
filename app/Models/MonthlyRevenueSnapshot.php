<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperMonthlyRevenueSnapshot
 */
class MonthlyRevenueSnapshot extends Model
{
    protected $fillable = [
        'tenant_id', 'year_month',
        'invoiced_revenue', 'recognized_revenue', 'deferred_revenue',
        'refunds',
        'net_new_arr', 'expansion_arr', 'contraction_arr', 'churned_arr',
        'active_subscriptions', 'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'invoiced_revenue' => 'decimal:2',
            'recognized_revenue' => 'decimal:2',
            'deferred_revenue' => 'decimal:2',
            'refunds' => 'decimal:2',
            'net_new_arr' => 'decimal:2',
            'expansion_arr' => 'decimal:2',
            'contraction_arr' => 'decimal:2',
            'churned_arr' => 'decimal:2',
            'active_subscriptions' => 'integer',
            'breakdown' => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
