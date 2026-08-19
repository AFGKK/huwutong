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
            ['group' => 'general', 'key' => 'site_name', 'value' => '互物通', 'type' => 'text', 'description' => '网站名称', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_description', 'value' => '企业级软件授权与 License 管理平台', 'type' => 'text', 'description' => '网站描述', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_keywords', 'value' => '互物通,License,授权,软件保护,许可管理,SaaS', 'type' => 'text', 'description' => 'SEO 关键词', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_slogan', 'value' => '让软件授权管理变得简单、安全、可靠', 'type' => 'text', 'description' => '网站标语', 'is_public' => true],

            // 品牌
            ['group' => 'brand', 'key' => 'logo_url', 'value' => '/images/logo.svg', 'type' => 'image', 'description' => 'Logo 图片 URL', 'is_public' => true],
            ['group' => 'brand', 'key' => 'favicon_url', 'value' => '/images/favicon.svg', 'type' => 'image', 'description' => 'Favicon URL', 'is_public' => true],
            ['group' => 'brand', 'key' => 'primary_color', 'value' => '#0f172a', 'type' => 'color', 'description' => '主题色（官网 / 品牌）', 'is_public' => true],
            ['group' => 'brand', 'key' => 'page_primary_color', 'value' => '#0f172a', 'type' => 'color', 'description' => '官网页面主题色（与 primary_color 同步）', 'is_public' => true],
            ['group' => 'brand', 'key' => 'footer_copyright', 'value' => '© 2026 互物通. All rights reserved.', 'type' => 'text', 'description' => '页脚版权信息', 'is_public' => true],

            // ICP
            ['group' => 'brand', 'key' => 'icp_beian', 'value' => '', 'type' => 'text', 'description' => 'ICP 备案号', 'is_public' => true],
            ['group' => 'brand', 'key' => 'icp_beian_url', 'value' => 'https://beian.miit.gov.cn/', 'type' => 'text', 'description' => 'ICP 备案链接', 'is_public' => true],
            ['group' => 'brand', 'key' => 'police_beian', 'value' => '', 'type' => 'text', 'description' => '公安备案号', 'is_public' => true],
            ['group' => 'brand', 'key' => 'police_beian_url', 'value' => 'https://www.beian.gov.cn/', 'type' => 'text', 'description' => '公安备案链接', 'is_public' => true],
            ['group' => 'brand', 'key' => 'gongan_beian', 'value' => '', 'type' => 'text', 'description' => '公安备案号（别名）', 'is_public' => true],
            ['group' => 'brand', 'key' => 'gongan_beian_url', 'value' => 'https://www.beian.gov.cn/', 'type' => 'text', 'description' => '公安备案链接（别名）', 'is_public' => true],

            // 追踪 / 验证 / 自定义 HTML
            ['group' => 'tracking', 'key' => 'seo_google_analytics', 'value' => '', 'type' => 'text', 'description' => 'Google Analytics Measurement ID (G-XXXX)', 'is_public' => false],
            ['group' => 'tracking', 'key' => 'tracking_baidu_id', 'value' => '', 'type' => 'text', 'description' => '百度统计站点 ID', 'is_public' => false],
            ['group' => 'tracking', 'key' => 'tracking_meta_pixel', 'value' => '', 'type' => 'text', 'description' => 'Meta Pixel ID', 'is_public' => false],
            ['group' => 'verification', 'key' => 'verify_google', 'value' => '', 'type' => 'text', 'description' => 'Google Search Console 验证码', 'is_public' => false],
            ['group' => 'verification', 'key' => 'verify_baidu', 'value' => '', 'type' => 'text', 'description' => '百度站长验证码', 'is_public' => false],
            ['group' => 'verification', 'key' => 'verify_bing', 'value' => '', 'type' => 'text', 'description' => 'Bing Webmaster 验证码', 'is_public' => false],
            ['group' => 'general', 'key' => 'custom_head_html', 'value' => '', 'type' => 'textarea', 'description' => '自定义 Head HTML', 'is_public' => false],
            ['group' => 'general', 'key' => 'custom_footer_html', 'value' => '', 'type' => 'textarea', 'description' => '自定义页脚 HTML', 'is_public' => false],

            // 联系方式
            ['group' => 'contact', 'key' => 'contact_email', 'value' => 'support@huwutong.com', 'type' => 'text', 'description' => '联系邮箱', 'is_public' => true],
            ['group' => 'contact', 'key' => 'contact_phone', 'value' => '', 'type' => 'text', 'description' => '联系电话', 'is_public' => true],
            ['group' => 'contact', 'key' => 'contact_address', 'value' => '上海市浦东新区张江高科技园区', 'type' => 'textarea', 'description' => '公司地址', 'is_public' => true],

            // SEO
            ['group' => 'seo', 'key' => 'seo_title', 'value' => '互物通 - 企业级软件授权管理平台', 'type' => 'text', 'description' => 'SEO 标题', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_description', 'value' => '互物通提供企业级软件授权、License 管理、设备绑定、订阅计费和安全保护解决方案。', 'type' => 'textarea', 'description' => 'SEO 描述', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_enable_sitemap', 'value' => '1', 'type' => 'switch', 'description' => '启用站点地图自动生成', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_sitemap_priority', 'value' => '0.5', 'type' => 'select', 'description' => '站点地图默认优先级', 'options' => ['0.0','0.1','0.2','0.3','0.4','0.5','0.6','0.7','0.8','0.9','1.0'], 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_sitemap_changefreq', 'value' => 'weekly', 'type' => 'select', 'description' => '站点地图默认更新频率', 'options' => ['always','hourly','daily','weekly','monthly','yearly','never'], 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_robots_txt', 'value' => "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /build\nDisallow: /api\nSitemap: https://88.huwutong.com/sitemap.xml", 'type' => 'textarea', 'description' => 'robots.txt 自定义内容', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_canonical_domain', 'value' => 'https://88.huwutong.com', 'type' => 'text', 'description' => '权威域名 (如 https://www.example.com)', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_og_image_default', 'value' => '', 'type' => 'image', 'description' => '默认 Open Graph 分享图片', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_json_ld', 'value' => '', 'type' => 'textarea', 'description' => '全局 JSON-LD 结构化数据', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_robots_meta', 'value' => 'index,follow', 'type' => 'select', 'description' => '默认 Robots Meta 标签', 'options' => ['index,follow','noindex,follow','index,nofollow','noindex,nofollow'], 'is_public' => true],

            // 社交
            ['group' => 'social', 'key' => 'social_github', 'value' => '', 'type' => 'text', 'description' => 'GitHub 链接', 'is_public' => true],
            ['group' => 'social', 'key' => 'social_twitter', 'value' => '', 'type' => 'text', 'description' => 'Twitter 链接', 'is_public' => true],
            ['group' => 'social', 'key' => 'social_wechat', 'value' => '', 'type' => 'text', 'description' => '微信链接（完整 URL 才在页脚显示图标）', 'is_public' => true],
            ['group' => 'social', 'key' => 'social_weibo', 'value' => '', 'type' => 'text', 'description' => '微博链接', 'is_public' => true],

            // 存储配置
            ['group' => 'storage', 'key' => 'upload_max_size', 'value' => '10', 'type' => 'select', 'description' => '上传文件大小限制 (MB)', 'options' => ['2','5','10','20','50','100'], 'is_public' => true],
            ['group' => 'storage', 'key' => 'upload_allowed_types', 'value' => 'jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,zip', 'type' => 'text', 'description' => '允许上传的文件类型 (用逗号分隔)', 'is_public' => true],
            ['group' => 'storage', 'key' => 'cdn_url', 'value' => '', 'type' => 'text', 'description' => 'CDN 域名 (如 https://cdn.example.com)', 'is_public' => true],
            ['group' => 'storage', 'key' => 'cdn_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用 CDN 加速', 'is_public' => true],
            ['group' => 'storage', 'key' => 'cloud_storage_driver', 'value' => 's3', 'type' => 'select', 'description' => '默认云存储驱动', 'options' => ['s3','oss','cos','obs','r2','b2'], 'is_public' => false],
            ['group' => 'storage', 'key' => 'cloud_storage_key', 'value' => '', 'type' => 'password', 'description' => 'Access Key ID', 'is_public' => false],
            ['group' => 'storage', 'key' => 'cloud_storage_secret', 'value' => '', 'type' => 'password', 'description' => 'Secret Access Key', 'is_public' => false],
            ['group' => 'storage', 'key' => 'cloud_storage_region', 'value' => 'us-east-1', 'type' => 'text', 'description' => '区域 / Region', 'is_public' => false],
            ['group' => 'storage', 'key' => 'cloud_storage_bucket', 'value' => '', 'type' => 'text', 'description' => 'Bucket 名称', 'is_public' => false],
            ['group' => 'storage', 'key' => 'cloud_storage_endpoint', 'value' => '', 'type' => 'text', 'description' => '端点 URL (如 https://s3.amazonaws.com 或 http://localhost:9000)', 'is_public' => false],
            ['group' => 'storage', 'key' => 'cloud_storage_use_path_style', 'value' => '0', 'type' => 'switch', 'description' => '使用 Path Style 端点 (MinIO 等需要开启)', 'is_public' => false],
            ['group' => 'storage', 'key' => 'cloud_storage_path_prefix', 'value' => '', 'type' => 'text', 'description' => '文件路径前缀', 'is_public' => false],

            // 短信配置
            ['group' => 'sms', 'key' => 'sms_driver', 'value' => 'log', 'type' => 'select', 'description' => '短信驱动（目前支持 log / aliyun）', 'options' => ['log','aliyun'], 'is_public' => false],
            ['group' => 'sms', 'key' => 'sms_aliyun_key', 'value' => '', 'type' => 'password', 'description' => '阿里云 AccessKey', 'is_public' => false],
            ['group' => 'sms', 'key' => 'sms_aliyun_secret', 'value' => '', 'type' => 'password', 'description' => '阿里云 AccessSecret', 'is_public' => false],
            ['group' => 'sms', 'key' => 'sms_aliyun_sign', 'value' => '', 'type' => 'text', 'description' => '阿里云短信签名', 'is_public' => false],
            ['group' => 'sms', 'key' => 'sms_tencent_app_id', 'value' => '', 'type' => 'text', 'description' => '腾讯云 AppID', 'is_public' => false],
            ['group' => 'sms', 'key' => 'sms_tencent_key', 'value' => '', 'type' => 'password', 'description' => '腾讯云 AppKey', 'is_public' => false],
            ['group' => 'sms', 'key' => 'sms_tencent_sign', 'value' => '', 'type' => 'text', 'description' => '腾讯云短信签名', 'is_public' => false],

            // AI / 大模型配置
            ['group' => 'ai', 'key' => 'llm_default_provider', 'value' => env('LLM_DEFAULT_PROVIDER', filter_var(env('LOCAL_LLM_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? 'ollama' : 'deepseek'), 'type' => 'select', 'description' => '默认 AI 提供商', 'options' => ['deepseek','openai','claude','tongyi','wenxin','glm','ollama'], 'is_public' => false],
            ['group' => 'ai', 'key' => 'deepseek_api_key', 'value' => '', 'type' => 'password', 'description' => 'DeepSeek API Key', 'is_public' => false],
            ['group' => 'ai', 'key' => 'deepseek_api_base', 'value' => 'https://api.deepseek.com', 'type' => 'text', 'description' => 'DeepSeek API 地址', 'is_public' => false],
            ['group' => 'ai', 'key' => 'openai_api_key', 'value' => '', 'type' => 'password', 'description' => 'OpenAI API Key', 'is_public' => false],
            ['group' => 'ai', 'key' => 'openai_api_base', 'value' => 'https://api.openai.com', 'type' => 'text', 'description' => 'OpenAI API 地址', 'is_public' => false],
            ['group' => 'ai', 'key' => 'claude_api_key', 'value' => '', 'type' => 'password', 'description' => 'Claude API Key', 'is_public' => false],
            ['group' => 'ai', 'key' => 'claude_api_base', 'value' => 'https://api.anthropic.com', 'type' => 'text', 'description' => 'Claude API 地址', 'is_public' => false],
            ['group' => 'ai', 'key' => 'tongyi_api_key', 'value' => '', 'type' => 'password', 'description' => '通义千问 API Key', 'is_public' => false],
            ['group' => 'ai', 'key' => 'wenxin_api_key', 'value' => '', 'type' => 'password', 'description' => '文心一言 API Key', 'is_public' => false],
            ['group' => 'ai', 'key' => 'glm_api_key', 'value' => '', 'type' => 'password', 'description' => '智谱 GLM API Key', 'is_public' => false],
            ['group' => 'ai', 'key' => 'ollama_base_url', 'value' => 'http://localhost:11434', 'type' => 'text', 'description' => 'Ollama 本地地址', 'is_public' => false],
            ['group' => 'ai', 'key' => 'llm_temperature', 'value' => '0.7', 'type' => 'text', 'description' => 'AI 回复温度 (0-2)', 'is_public' => false],
            ['group' => 'ai', 'key' => 'llm_max_tokens', 'value' => '4096', 'type' => 'text', 'description' => '最大 Token 数', 'is_public' => false],
            ['group' => 'ai', 'key' => 'ai_chat_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用 AI 对话', 'is_public' => true],
            ['group' => 'ai', 'key' => 'ai_review_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用发送前 AI 预审', 'is_public' => true],
            ['group' => 'ai', 'key' => 'memory_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用长期记忆', 'is_public' => true],
            ['group' => 'ai', 'key' => 'proactive_insight_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用主动洞察', 'is_public' => true],

            // 邮件 (SMTP) 配置
            ['group' => 'mail', 'key' => 'mail_driver', 'value' => 'log', 'type' => 'select', 'description' => '邮件驱动', 'options' => ['log','smtp','mailgun','ses'], 'is_public' => false],
            ['group' => 'mail', 'key' => 'smtp_host', 'value' => '127.0.0.1', 'type' => 'text', 'description' => 'SMTP 主机', 'is_public' => false],
            ['group' => 'mail', 'key' => 'smtp_port', 'value' => '1025', 'type' => 'text', 'description' => 'SMTP 端口', 'is_public' => false],
            ['group' => 'mail', 'key' => 'smtp_encryption', 'value' => 'tls', 'type' => 'select', 'description' => '加密方式', 'options' => ['','tls','ssl'], 'is_public' => false],
            ['group' => 'mail', 'key' => 'smtp_username', 'value' => '', 'type' => 'text', 'description' => 'SMTP 用户名', 'is_public' => false],
            ['group' => 'mail', 'key' => 'smtp_password', 'value' => '', 'type' => 'password', 'description' => 'SMTP 密码', 'is_public' => false],
            ['group' => 'mail', 'key' => 'mail_from_address', 'value' => 'noreply@huwutong.com', 'type' => 'text', 'description' => '发件人邮箱', 'is_public' => false],
            ['group' => 'mail', 'key' => 'mail_from_name', 'value' => '互物通', 'type' => 'text', 'description' => '发件人名称', 'is_public' => false],

            // 支付网关配置
            ['group' => 'payment', 'key' => 'payment_driver', 'value' => 'mock', 'type' => 'select', 'description' => '默认支付驱动', 'options' => ['mock','alipay','wechat','stripe','paypal','yipay'], 'is_public' => false],
            ['group' => 'payment', 'key' => 'alipay_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用支付宝', 'is_public' => false],
            ['group' => 'payment', 'key' => 'alipay_app_id', 'value' => '', 'type' => 'text', 'description' => '支付宝 App ID', 'is_public' => false],
            ['group' => 'payment', 'key' => 'alipay_private_key', 'value' => '', 'type' => 'password', 'description' => '支付宝应用私钥', 'is_public' => false],
            ['group' => 'payment', 'key' => 'alipay_public_key', 'value' => '', 'type' => 'password', 'description' => '支付宝公钥', 'is_public' => false],
            ['group' => 'payment', 'key' => 'alipay_notify_url', 'value' => '', 'type' => 'text', 'description' => '支付宝回调 URL', 'is_public' => false],
            ['group' => 'payment', 'key' => 'wechat_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用微信支付', 'is_public' => false],
            ['group' => 'payment', 'key' => 'wechat_app_id', 'value' => '', 'type' => 'text', 'description' => '微信 AppID', 'is_public' => false],
            ['group' => 'payment', 'key' => 'wechat_mch_id', 'value' => '', 'type' => 'text', 'description' => '微信商户号', 'is_public' => false],
            ['group' => 'payment', 'key' => 'wechat_api_key', 'value' => '', 'type' => 'password', 'description' => '微信 API 密钥', 'is_public' => false],
            ['group' => 'payment', 'key' => 'wechat_notify_url', 'value' => '', 'type' => 'text', 'description' => '微信回调 URL', 'is_public' => false],
            ['group' => 'payment', 'key' => 'stripe_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用 Stripe', 'is_public' => false],
            ['group' => 'payment', 'key' => 'stripe_public_key', 'value' => '', 'type' => 'text', 'description' => 'Stripe 公钥', 'is_public' => false],
            ['group' => 'payment', 'key' => 'stripe_secret_key', 'value' => '', 'type' => 'password', 'description' => 'Stripe 私钥', 'is_public' => false],
            ['group' => 'payment', 'key' => 'stripe_webhook_secret', 'value' => '', 'type' => 'password', 'description' => 'Stripe Webhook Secret', 'is_public' => false],
            ['group' => 'payment', 'key' => 'paypal_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用 PayPal', 'is_public' => false],
            ['group' => 'payment', 'key' => 'paypal_client_id', 'value' => '', 'type' => 'text', 'description' => 'PayPal Client ID', 'is_public' => false],
            ['group' => 'payment', 'key' => 'paypal_client_secret', 'value' => '', 'type' => 'password', 'description' => 'PayPal Client Secret', 'is_public' => false],
            ['group' => 'payment', 'key' => 'paypal_mode', 'value' => 'sandbox', 'type' => 'select', 'description' => 'PayPal 模式', 'options' => ['sandbox','live'], 'is_public' => false],
            ['group' => 'payment', 'key' => 'yipay_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用易支付', 'is_public' => false],
            ['group' => 'payment', 'key' => 'yipay_pid', 'value' => '', 'type' => 'text', 'description' => '易支付商户PID', 'is_public' => false],
            ['group' => 'payment', 'key' => 'yipay_key', 'value' => '', 'type' => 'password', 'description' => '易支付密钥', 'is_public' => false],
            ['group' => 'payment', 'key' => 'yipay_api_url', 'value' => 'https://pay.example.com/', 'type' => 'text', 'description' => '易支付API地址', 'is_public' => false],
            ['group' => 'payment', 'key' => 'yipay_notify_url', 'value' => '', 'type' => 'text', 'description' => '易支付回调URL', 'is_public' => false],
            ['group' => 'payment', 'key' => 'yipay_return_url', 'value' => '', 'type' => 'text', 'description' => '易支付跳转URL', 'is_public' => false],

            // 安全策略
            ['group' => 'security', 'key' => 'security_login_max_attempts', 'value' => '5', 'type' => 'text', 'description' => '登录最大失败次数', 'is_public' => false],
            ['group' => 'security', 'key' => 'security_lockout_minutes', 'value' => '15', 'type' => 'text', 'description' => '账户锁定时长 (分钟)', 'is_public' => false],
            ['group' => 'security', 'key' => 'security_password_min_length', 'value' => '8', 'type' => 'text', 'description' => '密码最小长度', 'is_public' => false],
            ['group' => 'security', 'key' => 'security_require_2fa', 'value' => '0', 'type' => 'switch', 'description' => '强制双因素认证', 'is_public' => false],
            ['group' => 'security', 'key' => 'security_session_timeout', 'value' => '120', 'type' => 'text', 'description' => '会话超时 (分钟)', 'is_public' => false],

            // 系统维护
            ['group' => 'maintenance', 'key' => 'maintenance_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用维护模式', 'is_public' => true],
            ['group' => 'maintenance', 'key' => 'maintenance_message', 'value' => '系统维护中，请稍后再试。', 'type' => 'textarea', 'description' => '维护提示信息', 'is_public' => true],
            ['group' => 'maintenance', 'key' => 'maintenance_allowed_ips', 'value' => '127.0.0.1', 'type' => 'textarea', 'description' => '维护模式白名单 IP (每行一个)', 'is_public' => false],

            // 注册设置
            ['group' => 'registration', 'key' => 'registration_enabled', 'value' => '1', 'type' => 'switch', 'description' => '允许公开注册', 'is_public' => true],
            ['group' => 'registration', 'key' => 'registration_require_email_verify', 'value' => '1', 'type' => 'switch', 'description' => '注册需邮箱验证', 'is_public' => false],
            ['group' => 'registration', 'key' => 'registration_require_invite_code', 'value' => '0', 'type' => 'switch', 'description' => '注册需邀请码', 'is_public' => false],
            ['group' => 'registration', 'key' => 'registration_default_role', 'value' => 'tenant-admin', 'type' => 'select', 'description' => '默认注册角色', 'options' => ['tenant-admin','developer','viewer'], 'is_public' => false],

            // 时区/本地化
            ['group' => 'localization', 'key' => 'default_locale', 'value' => 'zh_CN', 'type' => 'select', 'description' => '默认语言', 'options' => ['zh_CN','en'], 'is_public' => true],
            ['group' => 'localization', 'key' => 'default_timezone', 'value' => 'Asia/Shanghai', 'type' => 'text', 'description' => '默认时区', 'is_public' => true],
            ['group' => 'localization', 'key' => 'date_format', 'value' => 'Y-m-d H:i:s', 'type' => 'text', 'description' => '日期格式', 'is_public' => true],
            ['group' => 'localization', 'key' => 'currency_default', 'value' => 'CNY', 'type' => 'select', 'description' => '默认货币', 'options' => ['CNY','USD','EUR','JPY'], 'is_public' => true],

            // 通知设置
            ['group' => 'notification', 'key' => 'notification_email_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用邮件通知', 'is_public' => false],
            ['group' => 'notification', 'key' => 'notification_sms_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用短信通知', 'is_public' => false],
            ['group' => 'notification', 'key' => 'notification_webhook_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用 Webhook 通知', 'is_public' => false],

            // 备份设置
            ['group' => 'backup', 'key' => 'backup_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用自动备份', 'is_public' => false],
            ['group' => 'backup', 'key' => 'backup_schedule', 'value' => 'daily', 'type' => 'select', 'description' => '备份频率', 'options' => ['hourly','daily','weekly'], 'is_public' => false],
            ['group' => 'backup', 'key' => 'backup_retention_days', 'value' => '30', 'type' => 'text', 'description' => '备份保留天数', 'is_public' => false],

            // 日志设置
            ['group' => 'logging', 'key' => 'log_level', 'value' => 'info', 'type' => 'select', 'description' => '日志级别', 'options' => ['debug','info','warning','error'], 'is_public' => false],
            ['group' => 'logging', 'key' => 'log_retention_days', 'value' => '90', 'type' => 'text', 'description' => '日志保留天数', 'is_public' => false],
            ['group' => 'logging', 'key' => 'audit_log_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用审计日志', 'is_public' => false],

            // 界面设置
            ['group' => 'interface', 'key' => 'admin_sidebar_collapsed', 'value' => '0', 'type' => 'switch', 'description' => '默认收起侧边栏', 'is_public' => false],
            ['group' => 'interface', 'key' => 'items_per_page', 'value' => '20', 'type' => 'select', 'description' => '列表每页条数', 'options' => ['10','20','50','100'], 'is_public' => false],
            ['group' => 'interface', 'key' => 'theme_mode', 'value' => 'light', 'type' => 'select', 'description' => '默认主题', 'options' => ['light','dark','auto'], 'is_public' => true],

            // API 配置
            ['group' => 'api', 'key' => 'api_rate_limit', 'value' => '60', 'type' => 'text', 'description' => 'API 默认限流 (次/分钟)', 'is_public' => false],
            ['group' => 'api', 'key' => 'api_version_default', 'value' => 'v1', 'type' => 'select', 'description' => '默认 API 版本', 'options' => ['v1','v2'], 'is_public' => false],
            ['group' => 'api', 'key' => 'api_docs_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用 API 文档', 'is_public' => true],

            // 客服设置（Live Chat widget 已下线：service_chat_enabled 默认关闭，chat_widget_* 不再入库）
            ['group' => 'service', 'key' => 'service_chat_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用在线客服', 'is_public' => true],
            ['group' => 'service', 'key' => 'service_work_hours', 'value' => '周一至周五 9:00-18:00', 'type' => 'text', 'description' => '客服工作时间', 'is_public' => true],
            ['group' => 'service', 'key' => 'service_auto_reply_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用自动回复', 'is_public' => false],
            ['group' => 'service', 'key' => 'feedback_widget_position', 'value' => 'right', 'type' => 'select', 'options' => ['left', 'right'], 'description' => '反馈浮窗位置（左/右）', 'is_public' => true],
            ['group' => 'service', 'key' => 'feedback_widget_bottom', 'value' => '80', 'type' => 'text', 'description' => '反馈浮窗距离底部距离（px）', 'is_public' => true],

            // 法律/隐私
            ['group' => 'legal', 'key' => 'legal_privacy_url', 'value' => '/privacy', 'type' => 'text', 'description' => '隐私政策 URL', 'is_public' => true],
            ['group' => 'legal', 'key' => 'legal_terms_url', 'value' => '/terms', 'type' => 'text', 'description' => '服务条款 URL', 'is_public' => true],
            ['group' => 'legal', 'key' => 'legal_cookie_policy_url', 'value' => '/cookie-policy', 'type' => 'text', 'description' => 'Cookie 政策 URL', 'is_public' => true],
            ['group' => 'legal', 'key' => 'legal_gdpr_enabled', 'value' => '1', 'type' => 'switch', 'description' => '启用 GDPR 合规', 'is_public' => true],

            // OAuth（仅已实现跳转的四平台；Apple/支付宝未实现不入库开关）
            ['group' => 'oauth', 'key' => 'oauth_wechat_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用微信登录', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_qq_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用QQ登录', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_google_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用Google登录', 'is_public' => false],
            ['group' => 'oauth', 'key' => 'oauth_github_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用GitHub登录', 'is_public' => false],

            // D-31: 微信小程序设置
            ['group' => 'wechat', 'key' => 'wechat_mini_program_appid', 'value' => '', 'type' => 'text', 'description' => '微信小程序 AppID', 'is_public' => false],
            ['group' => 'wechat', 'key' => 'wechat_mini_program_secret', 'value' => '', 'type' => 'password', 'description' => '微信小程序 AppSecret', 'is_public' => false],
            ['group' => 'wechat', 'key' => 'wechat_mini_subscribe_template_id', 'value' => '', 'type' => 'text', 'description' => '小程序过期提醒订阅消息模板ID', 'is_public' => false],
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
