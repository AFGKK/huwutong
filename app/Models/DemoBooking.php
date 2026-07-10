<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperDemoBooking
 */
class DemoBooking extends Model
{
    protected $fillable = [
        'company_name', 'contact_name', 'email', 'phone',
        'employee_count', 'product_interest', 'message', 'source',
        'status', 'admin_notes', 'assigned_to',
        'contacted_at', 'calendly_scheduled_at', 'calendly_event_uri',
    ];

    protected function casts(): array
    {
        return [
            'contacted_at' => 'datetime',
            'calendly_scheduled_at' => 'datetime',
        ];
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
