<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperMeteredBillingAlert
 */
class MeteredBillingAlert extends Model
{
    protected $table = 'metered_billing_alerts';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'customer_id', 'metric_key', 'name',
        'threshold_value', 'threshold_type', 'percentage', 'direction',
        'window_type', 'notify_channels', 'is_active', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'notify_channels' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    const DIRECTIONS = ['above' => '超过', 'below' => '低于'];
    const THRESHOLD_TYPES = ['quantity' => '数量', 'amount' => '金额', 'percentage' => '百分比'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function histories(): HasMany { return $this->hasMany(MeteredAlertHistory::class, 'alert_id'); }
}
