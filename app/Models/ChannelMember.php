<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperChannelMember
 */
class ChannelMember extends Model
{
    protected $fillable = ['channel_id', 'user_id', 'role', 'last_read_at', 'is_muted'];
    protected $casts = ['last_read_at' => 'datetime', 'is_muted' => 'boolean'];
    protected $table = 'channel_members';

    public function channel(): BelongsTo { return $this->belongsTo(Channel::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
