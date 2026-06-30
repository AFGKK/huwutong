<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Watchlist extends Model
{
    protected $table = 'watchlist';

    protected $fillable = [
        'user_id',
        'watchable_type',
        'watchable_id',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function watchable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 检查用户是否关注了某实体
     */
    public static function isWatching(int $userId, Model $subject): bool
    {
        return static::where('user_id', $userId)
            ->where('watchable_type', $subject->getMorphClass())
            ->where('watchable_id', $subject->getKey())
            ->exists();
    }

    /**
     * 切换关注状态
     */
    public static function toggle(int $userId, Model $subject, ?string $reason = 'manual'): bool
    {
        $existing = static::where('user_id', $userId)
            ->where('watchable_type', $subject->getMorphClass())
            ->where('watchable_id', $subject->getKey())
            ->first();

        if ($existing) {
            $existing->delete();
            return false; // 已取消关注
        }

        static::create([
            'user_id' => $userId,
            'watchable_type' => $subject->getMorphClass(),
            'watchable_id' => $subject->getKey(),
            'reason' => $reason,
        ]);
        return true; // 已关注
    }
}
