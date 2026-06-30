<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatAssignment extends Model
{
    protected $table = 'seat_assignments';

    protected $fillable = [
        'license_id', 'tenant_id', 'device_id', 'customer_id',
        'seat_identifier', 'label', 'status',
        'assigned_at', 'last_active_at', 'released_at',
        'assigned_by', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'last_active_at' => 'datetime',
            'released_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
