<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperWishlistItem
 */
class WishlistItem extends Model
{
    protected $table = 'wishlist_items';

    protected $fillable = [
        'user_id', 'product_id', 'group_id', 'note',
        'notify_on_sale', 'notify_on_stock', 'quantity',
        'target_price', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_sale' => 'boolean',
            'notify_on_stock' => 'boolean',
            'quantity' => 'integer',
            'target_price' => 'decimal:2',
            'priority' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WishlistGroup::class, 'group_id');
    }
}
