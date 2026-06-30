<?php

namespace App\Enums;

enum AiMemoryType: string
{
    case Preference = 'preference';      // 用户偏好（如"他喜欢暗色主题"）
    case Fact = 'fact';                  // 用户相关事实（如"他是PHP开发者"）
    case Context = 'context';            // 上下文参考（如"他正在开发电商项目"）
    case Insight = 'insight';            // AI洞察（如"他对性能优化很重视"）
    case Behavior = 'behavior';          // 行为模式（如"他习惯使用Redis缓存"）

    public function label(): string
    {
        return match ($this) {
            self::Preference => '偏好',
            self::Fact => '事实',
            self::Context => '上下文',
            self::Insight => '洞察',
            self::Behavior => '行为',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
