<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperGroupInvite
 */
class GroupInvite extends Model
{
    protected $fillable = ['conversation_id', 'created_by', 'token', 'expires_at', 'max_uses', 'use_count', 'is_active'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'max_uses' => 'integer',
            'use_count' => 'integer',
        ];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(UserConversation::class, 'conversation_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses > 0 && $this->use_count >= $this->max_uses) return false;
        return true;
    }

    public static function generateToken(): string
    {
        return Str::random(32);
    }
}
