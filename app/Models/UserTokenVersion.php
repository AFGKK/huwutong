<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用户 Token 版本号
 *
 * 当用户修改密码、权限变更或被管理员强制下线时，
 * 递增版本号使该用户的所有老版本 Token 自动失效。
 *
 * Token 创建时会记录当时的版本号；
 * 每次请求时比较版本号，不匹配则拒绝。
 *
 * @mixin IdeHelperUserTokenVersion
 */
class UserTokenVersion extends Model
{
    protected $fillable = [
        'user_id', 'version', 'last_bumped_at',
    ];

    protected function casts(): array
    {
        return [
            'last_bumped_at' => 'datetime',
        ];
    }

    /**
     * 获取用户当前版本，不存在则创建
     */
    public static function getCurrentVersion(int $userId): int
    {
        $record = static::firstOrCreate(
            ['user_id' => $userId],
            ['version' => 1, 'last_bumped_at' => now()]
        );

        return $record->version;
    }

    /**
     * 递增版本号（使老 Token 失效）
     */
    public static function bumpVersion(int $userId): int
    {
        $record = static::firstOrCreate(
            ['user_id' => $userId],
            ['version' => 1, 'last_bumped_at' => now()]
        );

        $record->increment('version');
        $record->update(['last_bumped_at' => now()]);

        return $record->version;
    }
}
