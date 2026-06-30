<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResaleListing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'license_id', 'seller_customer_id', 'reference',
        'title', 'description', 'asking_price', 'currency',
        'commission_rate', 'status',
        'reviewed_by', 'reviewed_at', 'review_notes',
        'listed_at', 'sold_at', 'expires_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'asking_price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'listed_at' => 'datetime',
            'sold_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ─── 状态常量 ───
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_ACTIVE = 'active';
    const STATUS_SOLD = 'sold';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function sellerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'seller_customer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ResaleTransaction::class, 'listing_id');
    }

    /**
     * 计算预计佣金金额
     */
    public function getEstimatedCommissionAttribute(): float
    {
        return round($this->asking_price * $this->commission_rate / 100, 2);
    }

    /**
     * 计算卖家预计收入
     */
    public function getEstimatedPayoutAttribute(): float
    {
        return round($this->asking_price - $this->estimated_commission, 2);
    }

    /**
     * 是否可供购买
     */
    public function isAvailable(): bool
    {
        return in_array($this->status, [self::STATUS_PUBLISHED, self::STATUS_ACTIVE]);
    }
}
