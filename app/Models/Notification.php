<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperNotification
 */
class Notification extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'type',
        'title',
        'content',
        'payload',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
