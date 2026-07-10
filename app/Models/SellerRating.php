<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSellerRating
 */
class SellerRating extends Model
{
    protected $table = 'seller_ratings';

    protected $fillable = [
        'transaction_id', 'seller_customer_id', 'buyer_customer_id',
        'rating', 'comment',
    ];

    public function transaction(): BelongsTo { return $this->belongsTo(LicenseTransaction::class, 'transaction_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Customer::class, 'seller_customer_id'); }
    public function buyer(): BelongsTo { return $this->belongsTo(Customer::class, 'buyer_customer_id'); }
}
