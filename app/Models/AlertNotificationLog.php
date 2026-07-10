<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAlertNotificationLog
 */
class AlertNotificationLog extends Model
{
    protected $table = 'alert_notification_logs';

    protected $fillable = [
        'alert_event_id', 'alert_channel_id',
        'channel_type', 'status', 'response', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo { return $this->belongsTo(AlertEvent::class, 'alert_event_id'); }
    public function channel(): BelongsTo { return $this->belongsTo(AlertChannel::class, 'alert_channel_id'); }
}
