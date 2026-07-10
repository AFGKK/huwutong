<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperUserForumLevel
 */
class UserForumLevel extends Model
{
    protected $table = 'user_forum_levels';
    protected $fillable = ['user_id', 'exp', 'level'];

    // 升级所需经验值对照表
    const LEVEL_EXP = [0, 0, 50, 150, 300, 500, 800, 1200, 1800, 2500, 3500];

    // 经验规则
    const EXP_POST = 10;
    const EXP_COMMENT = 3;
    const EXP_LIKE_RECEIVED = 2;
    const EXP_LOGIN = 1;

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function getLevel(int $exp): int
    {
        $level = 1;
        foreach (self::LEVEL_EXP as $l => $need) {
            if ($exp >= $need) $level = $l;
        }
        return min($level, count(self::LEVEL_EXP) - 1);
    }

    public static function getLevelExp(int $level): int
    {
        return self::LEVEL_EXP[$level] ?? 999999;
    }

    public static function getNextLevelExp(int $currentLevel): int
    {
        $next = $currentLevel + 1;
        return self::LEVEL_EXP[$next] ?? self::LEVEL_EXP[count(self::LEVEL_EXP) - 1];
    }

    public static function earn(int $userId, int $amount, string $reason): void
    {
        $record = self::firstOrCreate(['user_id' => $userId]);
        $record->exp += $amount;
        $newLevel = self::getLevel($record->exp);
        $leveledUp = $newLevel > $record->level;
        $record->level = $newLevel;
        $record->save();

        // 记录经验变动日志
        ForumExpLog::create([
            'user_id' => $userId,
            'amount' => $amount,
            'reason' => $reason,
            'exp_before' => $record->exp - $amount,
            'exp_after' => $record->exp,
            'level_before' => $newLevel - ($leveledUp ? 1 : 0),
            'level_after' => $newLevel,
        ]);
    }

    public function progress(): array
    {
        $currentLevelExp = self::getLevelExp($this->level);
        $nextLevelExp = self::getNextLevelExp($this->level);
        $expInLevel = $this->exp - $currentLevelExp;
        $expNeeded = $nextLevelExp - $currentLevelExp;

        return [
            'level' => $this->level,
            'exp' => $this->exp,
            'exp_in_level' => $expInLevel,
            'exp_needed' => $expNeeded > 0 ? $expNeeded : 1,
            'progress' => $expNeeded > 0 ? min(100, round(($expInLevel / $expNeeded) * 100)) : 100,
        ];
    }

    public static function getBadge(int $level): string
    {
        return ['', '🌱', '🌿', '🌳', '⭐', '🌟', '🏆', '👑', '💎', '🔥', '⚡'][$level] ?? '🌱';
    }
}
