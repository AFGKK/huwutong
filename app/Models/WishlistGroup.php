<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WishlistGroup extends Model
{
    protected $table = 'wishlist_groups';

    protected $fillable = [
        'user_id', 'name', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class, 'group_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(WishlistShare::class, 'wishlist_group_id');
    }
}
