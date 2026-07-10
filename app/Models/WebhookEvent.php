<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperWebhookEvent
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'tenant_id', 'webhook_endpoint_id', 'event_type', 'payload', 'status',
        'attempts', 'next_retry_at', 'is_simulated', 'description',
        'status_code', 'response_body',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_retry_at' => 'datetime',
            'attempts' => 'integer',
            'is_simulated' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function webhookEndpoint(): BelongsTo
    {
        return $this->endpoint();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(EventDelivery::class);
    }
}
