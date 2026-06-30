<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceLifecycleEvent extends Model
{
    protected $table = 'device_lifecycle_events';

    protected $fillable = [
        'device_id', 'tenant_id', 'event_type', 'stage',
        'trust_score_before', 'trust_score_after', 'trust_score_change',
        'metadata', 'reason', 'triggered_by', 'triggered_by_user',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user');
    }
}
