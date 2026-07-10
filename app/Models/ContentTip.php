<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperContentTip
 */
class ContentTip extends Model
{
    protected $fillable = [
        'tipper_id', 'receiver_id',
        'tippable_type', 'tippable_id',
        'points', 'message',
    ];

    protected $casts = [
        'points' => 'decimal:2',
    ];

    public function tipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tipper_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function tippable(): MorphTo
    {
        return $this->morphTo();
    }
}
