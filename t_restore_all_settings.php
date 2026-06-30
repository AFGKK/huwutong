<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

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
    ['group' => 'seo', 'key' => 'seo_enable_sitemap', 'value' => '1', 'type' => 'switch', 'description' => '启用站点地图自动生成', 'is_public' => true],
    ['group' => 'seo', 'key' => 'seo_sitemap_priority', 'value' => '0.5', 'type' => 'select', 'description' => '站点地图默认优先级', 'options' => json_encode(['0.0','0.1','0.2','0.3','0.4','0.5','0.6','0.7','0.8','0.9','1.0']), 'is_public' => true],
    ['group' => 'seo', 'key' => 'seo_sitemap_changefreq', 'value' => 'weekly', 'type' => 'select', 'description' => '站点地图默认更新频率', 'options' => json_encode(['always','hourly','daily','weekly','monthly','yearly','never']), 'is_public' => true],
    ['group' => 'seo', 'key' => 'seo_robots_txt', 'value' => "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /build\nDisallow: /api\nSitemap: https://example.com/sitemap.xml", 'type' => 'textarea', 'description' => 'robots.txt 自定义内容', 'is_public' => true],
    ['group' => 'seo', 'key' => 'seo_canonical_domain', 'value' => '', 'type' => 'text', 'description' => '权威域名 (如 https://www.example.com)', 'is_public' => true],
    ['group' => 'seo', 'key' => 'seo_og_image_default', 'value' => '', 'type' => 'image', 'description' => '默认 Open Graph 分享图片', 'is_public' => true],
    ['group' => 'seo', 'key' => 'seo_json_ld', 'value' => '', 'type' => 'textarea', 'description' => '全局 JSON-LD 结构化数据', 'is_public' => true],
    ['group' => 'seo', 'key' => 'seo_robots_meta', 'value' => 'index,follow', 'type' => 'select', 'description' => '默认 Robots Meta 标签', 'options' => json_encode(['index,follow','noindex,follow','index,nofollow','noindex,nofollow']), 'is_public' => true],

    // 社交
    ['group' => 'social', 'key' => 'social_github', 'value' => '', 'type' => 'text', 'description' => 'GitHub 链接', 'is_public' => true],
    ['group' => 'social', 'key' => 'social_twitter', 'value' => '', 'type' => 'text', 'description' => 'Twitter 链接', 'is_public' => true],
    ['group' => 'social', 'key' => 'social_wechat', 'value' => '', 'type' => 'text', 'description' => '微信公众号', 'is_public' => true],

    // 存储配置
    ['group' => 'storage', 'key' => 'upload_max_size', 'value' => '10', 'type' => 'select', 'description' => '上传文件大小限制 (MB)', 'options' => json_encode(['2','5','10','20','50','100']), 'is_public' => true],
    ['group' => 'storage', 'key' => 'upload_allowed_types', 'value' => 'jpg,jpeg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,zip', 'type' => 'text', 'description' => '允许上传的文件类型 (用逗号分隔)', 'is_public' => true],
    ['group' => 'storage', 'key' => 'cdn_url', 'value' => '', 'type' => 'text', 'description' => 'CDN 域名 (如 https://cdn.example.com)', 'is_public' => true],
    ['group' => 'storage', 'key' => 'cdn_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用 CDN 加速', 'is_public' => true],
    ['group' => 'storage', 'key' => 'cloud_storage_driver', 'value' => 's3', 'type' => 'select', 'description' => '默认云存储驱动', 'options' => json_encode(['s3','oss','cos','obs','r2','b2']), 'is_public' => false],
    ['group' => 'storage', 'key' => 'cloud_storage_key', 'value' => '', 'type' => 'password', 'description' => 'Access Key ID', 'is_public' => false],
    ['group' => 'storage', 'key' => 'cloud_storage_secret', 'value' => '', 'type' => 'password', 'description' => 'Secret Access Key', 'is_public' => false],
    ['group' => 'storage', 'key' => 'cloud_storage_region', 'value' => 'us-east-1', 'type' => 'text', 'description' => '区域 / Region', 'is_public' => false],
    ['group' => 'storage', 'key' => 'cloud_storage_bucket', 'value' => '', 'type' => 'text', 'description' => 'Bucket 名称', 'is_public' => false],
    ['group' => 'storage', 'key' => 'cloud_storage_endpoint', 'value' => '', 'type' => 'text', 'description' => '端点 URL', 'is_public' => false],
    ['group' => 'storage', 'key' => 'cloud_storage_use_path_style', 'value' => '0', 'type' => 'switch', 'description' => '使用 Path Style 端点', 'is_public' => false],
    ['group' => 'storage', 'key' => 'cloud_storage_path_prefix', 'value' => '', 'type' => 'text', 'description' => '文件路径前缀', 'is_public' => false],

    // 短信配置
    ['group' => 'sms', 'key' => 'sms_driver', 'value' => 'aliyun', 'type' => 'select', 'description' => '短信驱动', 'options' => json_encode(['aliyun','tencent']), 'is_public' => false],
    ['group' => 'sms', 'key' => 'sms_aliyun_key', 'value' => '', 'type' => 'password', 'description' => '阿里云 AccessKey', 'is_public' => false],
    ['group' => 'sms', 'key' => 'sms_aliyun_secret', 'value' => '', 'type' => 'password', 'description' => '阿里云 AccessSecret', 'is_public' => false],
    ['group' => 'sms', 'key' => 'sms_aliyun_sign', 'value' => '', 'type' => 'text', 'description' => '阿里云短信签名', 'is_public' => false],
    ['group' => 'sms', 'key' => 'sms_tencent_app_id', 'value' => '', 'type' => 'text', 'description' => '腾讯云 AppID', 'is_public' => false],
    ['group' => 'sms', 'key' => 'sms_tencent_key', 'value' => '', 'type' => 'password', 'description' => '腾讯云 AppKey', 'is_public' => false],
    ['group' => 'sms', 'key' => 'sms_tencent_sign', 'value' => '', 'type' => 'text', 'description' => '腾讯云短信签名', 'is_public' => false],

    // AI / 大模型配置
    ['group' => 'ai', 'key' => 'llm_default_provider', 'value' => 'deepseek', 'type' => 'select', 'description' => '默认 AI 提供商', 'options' => json_encode(['deepseek','openai','claude','tongyi','wenxin','glm','ollama']), 'is_public' => false],
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

    // OAuth 补充
    ['group' => 'oauth', 'key' => 'oauth_alipay_app_id', 'value' => '', 'type' => 'text', 'description' => '支付宝 App ID', 'is_public' => false],
    ['group' => 'oauth', 'key' => 'oauth_alipay_private_key', 'value' => '', 'type' => 'password', 'description' => '支付宝应用私钥', 'is_public' => false],
    ['group' => 'oauth', 'key' => 'oauth_alipay_enabled', 'value' => '0', 'type' => 'switch', 'description' => '启用支付宝登录', 'is_public' => false],
];

$count = 0;
foreach ($settings as $s) {
    $exists = \Illuminate\Support\Facades\DB::table('site_settings')->where('key', $s['key'])->exists();
    if (!$exists) {
        $s['created_at'] = now();
        $s['updated_at'] = now();
        \Illuminate\Support\Facades\DB::table('site_settings')->insert($s);
        $count++;
    }
}

echo "Restored $count settings.\n";

$groups = \Illuminate\Support\Facades\DB::table('site_settings')->select('group', \Illuminate\Support\Facades\DB::raw('count(*) as total'))->groupBy('group')->get();
foreach ($groups as $g) {
    echo "  {$g->group}: {$g->total} items\n";
}
