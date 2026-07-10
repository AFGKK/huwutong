<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMeteredAlertHistory
 */
class MeteredAlertHistory extends Model
{
    protected $table = 'metered_alert_histories';

    protected $fillable = [
        'alert_id', 'tenant_id', 'metric_key', 'current_value',
        'threshold_value', 'channel', 'status', 'message', 'triggered_at',
    ];

    protected function casts(): array
    {
        return ['triggered_at' => 'datetime'];
    }

    const STATUSES = ['sent' => '已发送', 'failed' => '发送失败', 'read' => '已读'];

    public function alert(): BelongsTo { return $this->belongsTo(MeteredBillingAlert::class, 'alert_id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
