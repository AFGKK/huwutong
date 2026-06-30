<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChannelMessage extends Model
{
    use SoftDeletes;
    protected $fillable = ['channel_id', 'user_id', 'content', 'message_type', 'attachments', 'metadata', 'reply_to_id', 'is_pinned', 'is_recalled'];
    protected $casts = ['attachments' => 'array', 'metadata' => 'array', 'is_pinned' => 'boolean', 'is_recalled' => 'boolean'];
    protected $table = 'channel_messages';

    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function channelReplyTo(): BelongsTo { return $this->belongsTo(self::class, 'reply_to_id'); }
}
