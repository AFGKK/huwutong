<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperLicenseTransaction
 */
class LicenseTransaction extends Model
{
    protected $table = 'license_transactions';

    protected $fillable = [
        'listing_id', 'buyer_customer_id', 'license_id', 'tenant_id',
        'price', 'commission', 'seller_payout', 'status',
        'transaction_id', 'snapshot', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission' => 'decimal:2',
            'seller_payout' => 'decimal:2',
            'snapshot' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo { return $this->belongsTo(LicenseListing::class, 'listing_id'); }
    public function buyer(): BelongsTo { return $this->belongsTo(Customer::class, 'buyer_customer_id'); }
    public function license(): BelongsTo { return $this->belongsTo(License::class); }
    public function disputes(): HasMany { return $this->hasMany(LicenseDispute::class, 'transaction_id'); }
    public function rating(): BelongsTo { return $this->belongsTo(SellerRating::class, 'id', 'transaction_id'); }
}
