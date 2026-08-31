<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * P1: 补齐系统设置 ↔ 前台联动缺口键
 * - tracking / verification / custom HTML
 * - 公安备案 URL
 * - OAuth 四平台启用开关与凭据占位
 * - 短信驱动选项收敛为 log|aliyun
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // 追踪
            ['group' => 'tracking', 'key' => 'seo_google_analytics', 'value' => '', 'type' => 'text', 'description' => 'Google Analytics Measurement ID (G-XXXX)', 'is_public' => false],
            ['group' => 'tracking', 'key' => 'tracking_baidu_id', 'value' => '', 'type' => 'text', 'description' => '百度统计站点 ID', 'is_public' => false],
            ['group' => 'tracking', 'key' => 'tracking_meta_pixel', 'value' => '', 'type' => 'text', 'description' => 'Meta Pixel ID', 'is_public' => false],

            // 站点验证
            ['group' => 'verification', 'key' => 'verify_google', 'value' => '', 'type' => 'text', 'description' => 'Google Search Console 验证码', 'is_public' => false],
            ['group' => 'verification', 'key' => 'verify_baidu', 'value' => '', 'type' => 'text', 'description' => '百度站长验证码', 'is_public' => false],
            ['group' => 'verification', 'key' => 'verify_bing', 'value' => '', 'type' => 'text', 'description' => 'Bing Webmaster 验证码', 'is_public' => false],

            // 自定义 HTML
            ['group' => 'general', 'key' => 'custom_head_html', 'value' => '', 'type' => 'textarea', 'description' => '自定义 Head HTML（统计脚本等）', 'is_public' => false],
            ['group' => 'general', 'key' => 'custom_footer_html', 'value' => '', 'type' => 'textarea', 'description' => '自定义页脚 HTML', 'is_public' => false],

            // 公安备案 URL（与 police/gongan 号配套）
            ['group' => 'brand', 'key' => 'police_beian_url', 'value' => 'https://www.beian.gov.cn/', 'type' => 'text', 'description' => '公安备案链接', 'is_public' => true],
            ['group' => 'brand', 'key' => 'gongan_beian_url', 'value' => 'https://www.beian.gov.cn/', 'type' => 'text', 'description' => '公安备案链接（别名）', 'is_public' => true],

            // OAuth 四平台（已实现跳转）
            ['group' => 'oauth', 'key' => 'oauth_wechat_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用微信登录', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_qq_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用QQ登录', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_google_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用Google登录', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_github_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用GitHub登录', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_wechat_app_id', 'value' => '', 'type' => 'text', 'description' => '微信 AppID', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_wechat_app_secret', 'value' => '', 'type' => 'password', 'description' => '微信 AppSecret', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_qq_app_id', 'value' => '', 'type' => 'text', 'description' => 'QQ AppID', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_qq_app_key', 'value' => '', 'type' => 'password', 'description' => 'QQ AppKey', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_google_client_id', 'value' => '', 'type' => 'text', 'description' => 'Google Client ID', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_google_client_secret', 'value' => '', 'type' => 'password', 'description' => 'Google Client Secret', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_github_client_id', 'value' => '', 'type' => 'text', 'description' => 'GitHub Client ID', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_github_client_secret', 'value' => '', 'type' => 'password', 'description' => 'GitHub Client Secret', 'is_public' => false],
        ];

        foreach ($rows as $row) {
            $existing = SiteSetting::where('key', $row['key'])->first();
            if ($existing) {
                // 已存在：补分组/描述；启用开关若为关闭则打开四平台
                $patch = ['group' => $row['group'], 'type' => $row['type'], 'description' => $row['description']];
                if (in_array($row['key'], [
                    'oauth_wechat_enabled', 'oauth_qq_enabled', 'oauth_google_enabled', 'oauth_github_enabled',
                ], true) && (string) $existing->value !== '1') {
                    $patch['value'] = '1';
                }
                $existing->update($patch);
            } else {
                SiteSetting::create($row + ['is_public' => $row['is_public'] ?? false]);
            }
        }

        // 关闭未实现的 Apple / 支付宝假开关
        foreach (['oauth_apple_enabled', 'oauth_alipay_enabled'] as $key) {
            SiteSetting::where('key', $key)->update(['value' => '0']);
        }

        // 短信驱动选项收敛
        $sms = SiteSetting::where('key', 'sms_driver')->first();
        if ($sms) {
            $opts = ['log', 'aliyun'];
            $sms->options = $opts;
            if (! in_array((string) $sms->value, $opts, true)) {
                $sms->value = 'log';
            }
            $sms->save();
        }
    }

    public function down(): void
    {
        // 不删除已写入的运营配置，避免回滚丢数据
    }
};
