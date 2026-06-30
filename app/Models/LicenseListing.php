<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseListing extends Model
{
    use SoftDeletes;

    protected $table = 'license_listings';

    protected $fillable = [
        'license_id', 'seller_customer_id', 'tenant_id',
        'price', 'commission', 'status', 'notes', 'review_notes',
        'reviewed_by', 'reviewed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function license(): BelongsTo { return $this->belongsTo(License::class); }
    public function seller(): BelongsTo { return $this->belongsTo(Customer::class, 'seller_customer_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function transaction(): HasMany { return $this->hasMany(LicenseTransaction::class, 'listing_id'); }
}
