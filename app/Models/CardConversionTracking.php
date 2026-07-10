<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCardConversionTracking
 */
class CardConversionTracking extends Model
{
    protected $table = 'card_conversion_tracking';

    protected $fillable = [
        'trace_id',
        'card_type',
        'message_id',
        'sender_id',
        'receiver_id',
        'event',
        'callback_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'message_id' => 'integer',
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
    ];
}
