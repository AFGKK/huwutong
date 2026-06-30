<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookReplay extends Model
{
    protected $fillable = [
        'tenant_id',
        'webhook_event_id',
        'webhook_endpoint_id',
        'status',
        'attempt_count',
        'response_code',
        'response_body',
        'error_message',
        'triggered_by',
        'replayed_by',
        'replayed_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'response_code' => 'integer',
        'replayed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }

    public function webhookEndpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class);
    }
}
