<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 演示 Demo 配置模型
 *
 * 管理交互式产品演示的配置选项
 * - 演示时长、是否启用、种子数据定制
 *
 * @m3-70 InteractiveDemo
 */
class DemoConfig extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 获取配置值
     */
    public static function getConfig(string $key, mixed $default = null): mixed
    {
        $config = static::where('key', $key)->where('is_active', true)->first();

        if (!$config) {
            return $default;
        }

        $value = $config->value;

        // 如果是简单值，直接返回
        if (is_array($value) && count($value) === 1 && isset($value[0]) && !is_array($value[0])) {
            return $value[0];
        }

        return $value;
    }

    /**
     * 设置配置值
     */
    public static function setConfig(string $key, mixed $value, string $description = ''): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? $value : [$value],
                'description' => $description,
                'is_active' => true,
            ],
        );
    }
}
