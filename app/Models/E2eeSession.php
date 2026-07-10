<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperE2eeSession
 */
class E2eeSession extends Model
{
    protected $fillable = ['user_id', 'conversation_id', 'session_key', 'ratchet_step', 'status'];
    protected $casts = ['ratchet_step' => 'integer'];
    protected $table = 'e2ee_sessions';

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(UserConversation::class, 'conversation_id'); }
}
