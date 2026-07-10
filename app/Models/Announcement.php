<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAnnouncement
 */
class Announcement extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'title', 'content'];

    public function conversation(): BelongsTo { return $this->belongsTo(UserConversation::class, 'conversation_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function reads() { return $this->hasMany(AnnouncementRead::class, 'announcement_id'); }

    public function isReadBy(int $userId): bool
    {
        return $this->reads()->where('user_id', $userId)->exists();
    }

    public function readCount(): int
    {
        return $this->reads()->count();
    }
}
