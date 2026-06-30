<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 支付 Webhook 日志
 *
 * 记录所有 incoming 支付网关回调事件，支持幂等性和审计追踪。
 */
class PaymentWebhookLog extends Model
{
    protected $fillable = [
        'gateway', 'event_type', 'event_id', 'status',
        'payload', 'response', 'error_message',
        'processable_type', 'processable_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function processable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['received', 'processing']);
    }

    public function markCompleted(string $response = ''): void
    {
        $this->update(['status' => 'completed', 'response' => $response]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['status' => 'failed', 'error_message' => $error]);
    }
}
