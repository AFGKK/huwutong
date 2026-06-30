<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OemSubscriptionChange extends Model
{
    protected $table = 'oem_subscription_changes';

    protected $fillable = [
        'oem_subscription_id', 'change_type', 'from_tier', 'to_tier',
        'price', 'reason', 'operated_by',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OemSubscription::class, 'oem_subscription_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operated_by');
    }
}
