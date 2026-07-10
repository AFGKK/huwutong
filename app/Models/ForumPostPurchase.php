<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperForumPostPurchase
 */
class ForumPostPurchase extends Model
{
    protected $table = 'forum_post_purchases';
    protected $fillable = ['post_id', 'user_id', 'price', 'price_type', 'status', 'paid_at'];

    protected $casts = ['price' => 'decimal:2', 'paid_at' => 'datetime'];

    public function post(): BelongsTo { return $this->belongsTo(ForumPost::class, 'post_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
