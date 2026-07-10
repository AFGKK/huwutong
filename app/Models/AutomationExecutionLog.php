<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperAutomationExecutionLog
 */
class AutomationExecutionLog extends Model
{
    protected $table = 'automation_execution_logs';

    protected $fillable = [
        'rule_id', 'trigger_source', 'trigger_data', 'conditions_result',
        'status', 'action_count', 'successful_actions', 'failed_actions',
        'error_message', 'execution_time_ms', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_data' => 'array',
            'conditions_result' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo { return $this->belongsTo(AutomationRule::class, 'rule_id'); }
    public function actionLogs(): HasMany { return $this->hasMany(AutomationActionLog::class, 'execution_id'); }
}
