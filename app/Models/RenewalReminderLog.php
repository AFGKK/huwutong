<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperRenewalReminderLog
 */
class RenewalReminderLog extends Model
{
    use HasFactory;

    protected $table = 'renewal_reminder_logs';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'customer_id',
        'channel', 'template_name', 'subject',
        'status', 'error', 'sent_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
