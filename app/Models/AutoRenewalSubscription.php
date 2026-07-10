<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAutoRenewalSubscription
 */
class AutoRenewalSubscription extends Model
{
    use SoftDeletes;

    protected $table = 'auto_renewal_subscriptions';

    protected $fillable = [
        'tenant_id', 'customer_id', 'auto_renewal_plan_id', 'license_id',
        'status', 'current_period_starts_at', 'current_period_ends_at',
        'trial_ends_at', 'paused_at', 'cancelled_at',
        'failed_attempts', 'last_renewal_at', 'next_renewal_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_renewal_at' => 'datetime',
            'next_renewal_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function plan(): BelongsTo { return $this->belongsTo(AutoRenewalPlan::class, 'auto_renewal_plan_id'); }
    public function license(): BelongsTo { return $this->belongsTo(License::class); }
    public function attempts(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AutoRenewalAttempt::class, 'auto_renewal_subscription_id'); }
}
