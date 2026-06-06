<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // 基本信息
            ['group' => 'general', 'key' => 'site_name', 'value' => 'HWT License', 'type' => 'text', 'description' => '网站名称', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_description', 'value' => '企业级授权管理系统', 'type' => 'text', 'description' => '网站描述', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_keywords', 'value' => 'License,授权,软件保护,许可管理', 'type' => 'text', 'description' => 'SEO 关键词', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_slogan', 'value' => '保护您的软件资产', 'type' => 'text', 'description' => '网站标语', 'is_public' => true],

            // 品牌
            ['group' => 'brand', 'key' => 'logo_url', 'value' => '', 'type' => 'image', 'description' => 'Logo 图片 URL', 'is_public' => true],
            ['group' => 'brand', 'key' => 'favicon_url', 'value' => '', 'type' => 'image', 'description' => 'Favicon URL', 'is_public' => true],
            ['group' => 'brand', 'key' => 'primary_color', 'value' => '#409EFF', 'type' => 'color', 'description' => '主题色', 'is_public' => true],
            ['group' => 'brand', 'key' => 'footer_copyright', 'value' => '© 2026 HWT License. All rights reserved.', 'type' => 'text', 'description' => '页脚版权信息', 'is_public' => true],

            // ICP
            ['group' => 'brand', 'key' => 'icp_beian', 'value' => '', 'type' => 'text', 'description' => 'ICP 备案号', 'is_public' => true],
            ['group' => 'brand', 'key' => 'icp_beian_url', 'value' => 'https://beian.miit.gov.cn/', 'type' => 'text', 'description' => 'ICP 备案链接', 'is_public' => true],
            ['group' => 'brand', 'key' => 'police_beian', 'value' => '', 'type' => 'text', 'description' => '公安备案号', 'is_public' => true],

            // 联系方式
            ['group' => 'contact', 'key' => 'contact_email', 'value' => 'support@huwutong.com', 'type' => 'text', 'description' => '联系邮箱', 'is_public' => true],
            ['group' => 'contact', 'key' => 'contact_phone', 'value' => '', 'type' => 'text', 'description' => '联系电话', 'is_public' => true],
            ['group' => 'contact', 'key' => 'contact_address', 'value' => '', 'type' => 'textarea', 'description' => '公司地址', 'is_public' => true],

            // SEO
            ['group' => 'seo', 'key' => 'seo_title', 'value' => 'HWT License - 企业级授权管理系统', 'type' => 'text', 'description' => 'SEO 标题', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_description', 'value' => 'HWT License 提供企业级软件授权、许可证管理、设备绑定和安全保护解决方案。', 'type' => 'textarea', 'description' => 'SEO 描述', 'is_public' => true],

            // 社交
            ['group' => 'social', 'key' => 'social_github', 'value' => '', 'type' => 'text', 'description' => 'GitHub 链接', 'is_public' => true],
            ['group' => 'social', 'key' => 'social_twitter', 'value' => '', 'type' => 'text', 'description' => 'Twitter 链接', 'is_public' => true],
            ['group' => 'social', 'key' => 'social_wechat', 'value' => '', 'type' => 'text', 'description' => '微信公众号', 'is_public' => true],
        ];

        foreach ($settings as $setting) {
            SiteSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        // 初始化默认页面
        $pages = [
            ['slug' => 'about', 'title' => '关于我们', 'content' => '<h1>关于 HWT License</h1><p>企业级授权管理系统，保护您的软件资产。</p>', 'status' => 'draft'],
            ['slug' => 'privacy', 'title' => '隐私政策', 'content' => '<h1>隐私政策</h1><p>我们重视您的隐私...</p>', 'status' => 'draft'],
            ['slug' => 'terms', 'title' => '服务条款', 'content' => '<h1>服务条款</h1><p>使用我们的服务即表示您同意以下条款...</p>', 'status' => 'draft'],
            ['slug' => 'contact', 'title' => '联系我们', 'content' => '<h1>联系我们</h1><p>如有任何问题，请发送邮件至 support@huwutong.com</p>', 'status' => 'draft'],
        ];

        foreach ($pages as $page) {
            Page::firstOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }
}
