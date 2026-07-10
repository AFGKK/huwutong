<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCsmHealthScore
 */
class CsmHealthScore extends Model
{
    protected $table = 'csm_health_scores';

    protected $fillable = [
        'customer_id', 'tenant_id',
        'health_score', 'health_level',
        'factors', 'summary', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'factors' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    const LEVELS = ['healthy' => '健康', 'attention' => '关注', 'at_risk' => '风险', 'churned' => '流失'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
