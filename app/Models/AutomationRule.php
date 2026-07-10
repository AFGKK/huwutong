<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperAutomationRule
 */
class AutomationRule extends Model
{
    protected $table = 'automation_rules';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'category',
        'trigger_type', 'trigger_config', 'conditions', 'condition_logic',
        'actions', 'action_execution',
        'status', 'priority', 'cooldown_minutes',
        'max_executions_per_hour', 'max_executions_per_day',
        'execution_count', 'success_count', 'failure_count',
        'last_executed_at', 'tags', 'is_template', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'conditions' => 'array',
            'actions' => 'array',
            'tags' => 'array',
            'last_executed_at' => 'datetime',
            'is_template' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    const CATEGORIES = ['license', 'billing', 'customer', 'security', 'system', 'custom'];
    const TRIGGER_TYPES = ['event', 'schedule', 'webhook', 'condition'];
    const STATUSES = ['draft', 'active', 'paused', 'archived'];
    const ACTION_EXECUTIONS = ['sequential', 'parallel', 'first_success'];
    const CONDITION_LOGIC = ['all', 'any'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function executions(): HasMany { return $this->hasMany(AutomationExecutionLog::class, 'rule_id'); }
    public function webhooks(): BelongsToMany { return $this->belongsToMany(AutomationWebhook::class, 'automation_rule_webhook', 'rule_id', 'webhook_id'); }
}
