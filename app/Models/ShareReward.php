<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareReward extends Model
{
    protected $fillable = [
        'user_id', 'content_type', 'content_id',
        'platform', 'points_awarded',
    ];

    protected $casts = [
        'points_awarded' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
