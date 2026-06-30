<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateBudgetTopup extends Model
{
    protected $table = 'affiliate_budget_topups';

    protected $fillable = [
        'campaign_id', 'user_id', 'amount', 'status',
        'payment_method', 'transaction_id', 'paid_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AffiliateCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
