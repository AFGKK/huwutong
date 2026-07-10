<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperProductReview
 */
class ProductReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'customer_id', 'user_id',
        'rating', 'content', 'images', 'tags',
        'status', 'admin_reply', 'reply_at', 'replied_by',
        'is_anonymous', 'is_verified_purchase', 'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'images' => 'array',
            'tags' => 'array',
            'is_anonymous' => 'boolean',
            'is_verified_purchase' => 'boolean',
            'reply_at' => 'datetime',
        ];
    }

    const STATUSES = ['pending', 'approved', 'rejected'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeWithRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }
}
