<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMeteredAutoSwitchRule
 */
class MeteredAutoSwitchRule extends Model
{
    protected $table = 'metered_auto_switch_rules';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'name', 'metric_key',
        'condition_type', 'condition_value', 'condition_days',
        'action', 'target_plan_slug', 'require_confirmation',
        'is_active', 'last_evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'require_confirmation' => 'boolean',
            'is_active' => 'boolean',
            'last_evaluated_at' => 'datetime',
        ];
    }

    const CONDITION_TYPES = [
        'usage_consecutive' => '连续用量超标',
        'usage_average' => '平均用量超标',
        'spend_threshold' => '消费金额超标',
    ];
    const ACTIONS = ['upgrade' => '升级', 'downgrade' => '降级'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
}
