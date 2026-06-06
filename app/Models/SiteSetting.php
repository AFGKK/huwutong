<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
     * 批量更新设置
     */
    public static function batchUpdate(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::where('key', $key)->update(['value' => $value]);
        }
    }
}
