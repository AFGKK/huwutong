<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperUserOnlineStatus
 */
class UserOnlineStatus extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'is_online', 'last_seen_at', 'device_info'];
    protected function casts(): array { return ['is_online' => 'boolean', 'last_seen_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
