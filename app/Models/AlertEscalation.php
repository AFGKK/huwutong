<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAlertEscalation
 */
class AlertEscalation extends Model
{
    protected $table = 'alert_escalations';

    protected $fillable = [
        'tenant_id', 'name', 'alert_rule_id',
        'escalation_level', 'after_minutes',
        'notify_type', 'notify_target', 'message_template',
        'escalate_action', 'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'notify_target' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    const LEVELS = [1, 2, 3];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function rule(): BelongsTo { return $this->belongsTo(AlertRule::class, 'alert_rule_id'); }
}
