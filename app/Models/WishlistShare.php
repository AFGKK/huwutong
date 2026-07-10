<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperWishlistShare
 */
class WishlistShare extends Model
{
    protected $table = 'wishlist_shares';

    protected $fillable = [
        'wishlist_group_id', 'user_id', 'share_token',
        'share_type', 'shared_with', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'shared_with' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WishlistGroup::class, 'wishlist_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
