<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperForumPostEarning
 */
class ForumPostEarning extends Model
{
    protected $table = 'forum_post_earnings';

    protected $fillable = [
        'post_id', 'buyer_id', 'author_id',
        'price', 'price_type', 'platform_fee', 'net_amount',
        'status', 'purchase_table', 'purchase_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'post_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
