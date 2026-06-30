<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AutomationWebhook extends Model
{
    protected $table = 'automation_webhooks';

    protected $fillable = [
        'tenant_id', 'name', 'url', 'method', 'headers',
        'body_template', 'auth_type', 'auth_config',
        'retry_config', 'timeout_config',
        'description', 'is_active', 'success_count', 'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'body_template' => 'array',
            'auth_config' => 'array',
            'retry_config' => 'array',
            'timeout_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(AutomationRule::class, 'automation_rule_webhook', 'webhook_id', 'rule_id');
    }
}
