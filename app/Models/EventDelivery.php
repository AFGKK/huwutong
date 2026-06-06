<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventDelivery extends Model
{
    protected $fillable = [
        'webhook_event_id', 'url', 'attempt', 'status',
        'response_code', 'response_body', 'error_message', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhookEvent()
    {
        return $this->belongsTo(WebhookEvent::class);
    }
}
