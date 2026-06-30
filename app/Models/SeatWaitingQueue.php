<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatWaitingQueue extends Model
{
    protected $table = 'seat_waiting_queue';

    protected $fillable = [
        'license_id', 'tenant_id',
        'seat_identifier', 'label', 'device_fingerprint',
        'status', 'queue_position', 'max_wait_minutes', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
