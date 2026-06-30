<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertEscalationLog extends Model
{
    protected $table = 'alert_escalation_logs';

    protected $fillable = [
        'alert_event_id', 'alert_escalation_id',
        'escalation_level', 'notify_type', 'status', 'response',
    ];

    public function event(): BelongsTo { return $this->belongsTo(AlertEvent::class, 'alert_event_id'); }
    public function escalation(): BelongsTo { return $this->belongsTo(AlertEscalation::class, 'alert_escalation_id'); }
}
