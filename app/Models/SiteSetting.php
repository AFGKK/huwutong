<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperSiteSetting
 */
class SiteSetting extends Model
{
    protected $fillable = [
        'group', 'key', 'value', 'type', 'options', 'description', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /**
     * 按分组获取设置
     */
    public static function getGrouped(): array
    {
        return self::orderBy('group')->orderBy('key')
            ->get()
            ->groupBy('group')
            ->toArray();
    }

    /**
     * 获取公开设置
     */
    public static function getPublic(): array
    {
        return self::where('is_public', true)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * 获取微信小程序配置
     */
    public static function getWechatMiniProgramConfig(): array
    {
        return [
            'appid' => self::where('key', 'wechat_mini_program_appid')->value('value') ?? '',
            'secret' => self::where('key', 'wechat_mini_program_secret')->value('value') ?? '',
            'subscribe_template_id' => self::where('key', 'wechat_mini_subscribe_template_id')->value('value') ?? '',
        ];
    }

    /**
     * 批量更新设置
     */
    public static function batchUpdate(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::where('key', $key)->update(['value' => $value]);
        }
    }
}
