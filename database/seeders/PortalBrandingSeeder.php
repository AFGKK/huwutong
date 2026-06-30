<?php

namespace Database\Seeders;

use App\Models\PortalBrandingConfig;
use Illuminate\Database\Seeder;

class PortalBrandingSeeder extends Seeder
{
    public function run(): void
    {
        if (PortalBrandingConfig::where('is_default', true)->exists()) {
            $this->command->info('Default portal branding already seeded, skipping.');
            return;
        }

        PortalBrandingConfig::create([
            'tenant_id' => null,
            'locale' => 'zh-CN',
            'brand_name' => '互物通',
            'brand_slogan' => '智能、高效的 License 管理平台',
            'logo_url' => null,
            'favicon_url' => null,
            'primary_color' => '#409eff',
            'secondary_color' => '#67c23a',
            'background_color' => '#f5f7fa',
            'text_color' => '#303133',
            'link_color' => '#409eff',
            'header_bg_color' => '#ffffff',
            'sidebar_bg_color' => '#304156',
            'sidebar_text_color' => '#bfcbd9',
            'button_radius' => '4px',
            'font_family' => null,
            'custom_css' => null,
            'header_html' => null,
            'footer_html' => null,
            'login_page_title' => '欢迎登录互物通',
            'login_page_subtitle' => '请使用您的账号登录系统',
            'login_bg_image' => null,
            'footer_text' => '© 2026 互物通. All rights reserved.',
            'links' => [
                ['label' => '帮助中心', 'url' => '/help'],
                ['label' => 'API 文档', 'url' => '/api-docs'],
                ['label' => '服务状态', 'url' => '/status'],
            ],
            'social_links' => [
                ['platform' => 'github', 'url' => 'https://github.com/huwutong'],
                ['platform' => 'wechat', 'url' => null],
            ],
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->command->info('Seeded default portal branding configuration.');
    }
}
