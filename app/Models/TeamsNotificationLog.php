<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $teams_webhook_id
 * @property string $notification_type
 * @property string $title
 * @property string|null $message
 * @property string $status
 * @property int|null $http_status
 * @property string|null $error_message
 * @property string|null $card_id
 * @property array|null $payload
 * @property \Carbon\Carbon $created_at
 */
class TeamsNotificationLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'teams_webhook_id',
        'notification_type',
        'title',
        'message',
        'status',
        'http_status',
        'error_message',
        'card_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(TeamsWebhook::class, 'teams_webhook_id');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    public function scopeByTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
