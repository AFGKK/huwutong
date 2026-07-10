<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAutomationActionLog
 */
class AutomationActionLog extends Model
{
    protected $table = 'automation_action_logs';

    protected $fillable = [
        'execution_id', 'rule_id', 'action_index', 'action_type',
        'action_config', 'input_data', 'output_data',
        'status', 'duration_ms', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'action_config' => 'array',
            'input_data' => 'array',
            'output_data' => 'array',
        ];
    }

    public function execution(): BelongsTo { return $this->belongsTo(AutomationExecutionLog::class, 'execution_id'); }
    public function rule(): BelongsTo { return $this->belongsTo(AutomationRule::class, 'rule_id'); }
}
