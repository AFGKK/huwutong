<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutoRenewalPlan extends Model
{
    use SoftDeletes;

    protected $table = 'auto_renewal_plans';

    protected $fillable = [
        'tenant_id', 'product_id', 'name', 'billing_period', 'price',
        'currency', 'trial_days', 'grace_days', 'max_retries',
        'upgrade_paths', 'downgrade_paths', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'upgrade_paths' => 'array',
            'downgrade_paths' => 'array',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AutoRenewalSubscription::class, 'auto_renewal_plan_id'); }
}
