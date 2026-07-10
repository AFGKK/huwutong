<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCookieConsentConfig
 */
class CookieConsentConfig extends Model
{
    protected $table = 'cookie_consent_configs';

    protected $fillable = [
        'is_active',
        'position',
        'title',
        'description',
        'accept_all_text',
        'reject_all_text',
        'customize_text',
        'privacy_policy_url',
        'privacy_policy_text',
        'categories',
        'consent_lifetime_days',
        'theme',
        'layout',
        'show_floating_button',
        'additional_css',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'categories' => 'array',
            'show_floating_button' => 'boolean',
            'additional_css' => 'array',
        ];
    }

    /**
     * 默认 Cookie 分类配置
     */
    public static function defaultCategories(): array
    {
        return [
            [
                'id' => 'necessary',
                'name' => '必要 Cookies',
                'description' => '网站运行必需的 Cookie，无法关闭',
                'required' => true,
                'default' => true,
            ],
            [
                'id' => 'functional',
                'name' => '功能 Cookies',
                'description' => '记住您的偏好设置，提升使用体验',
                'required' => false,
                'default' => true,
            ],
            [
                'id' => 'analytics',
                'name' => '分析 Cookies',
                'description' => '收集匿名使用数据，帮助我们改进产品',
                'required' => false,
                'default' => false,
            ],
            [
                'id' => 'marketing',
                'name' => '营销 Cookies',
                'description' => '用于个性化广告和营销内容推送',
                'required' => false,
                'default' => false,
            ],
        ];
    }
}
