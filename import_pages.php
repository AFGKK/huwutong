<?php
/**
 * 导入/恢复 CMS 页面 — 运行: php import_pages.php [--force]
 *
 * 默认: 仅创建不存在的页面
 * --force: 覆盖已有页面为互物通完整版内容并发布
 */
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$force = in_array('--force', $argv ?? [], true);
$now = now();

$pages = [
    [
        'slug' => 'about',
        'title' => '关于互物通',
        'content' => '<h2>我们的使命</h2><p>互物通为企业软件团队提供可核验的授权管理基础设施：签发、校验、吊销与设备生命周期管理。</p><h2>我们的故事</h2><p>互物通由工程师与产品经理组成，专注把授权做成可独立校验、可私有化落地的能力，而不是营销口号。</p><h2>核心价值</h2><ul><li><strong>可验安全</strong> — Ed25519 签名、离线验证与吊销列表</li><li><strong>清晰接入</strong> — 多语言 SDK 与文档示例，按步骤完成集成</li><li><strong>合规就绪</strong> — 面向 GDPR / PIPL 等常见要求预留能力（非已获 ISO/SOC 认证声明）</li><li><strong>灵活部署</strong> — SaaS、私有化与气隙模式可选</li></ul>',
        'meta' => json_encode(['title' => '关于我们', 'description' => '了解互物通——为企业软件提供可核验的授权管理基础设施。', 'subtitle' => '让授权管理可核验、可控、可落地']),
        'locale' => 'zh-CN',
        'status' => 'published',
        'version' => 1,
        'published_at' => $now,
    ],
    [
        'slug' => 'privacy',
        'title' => '隐私政策',
        'content' => '<h2>信息收集</h2><p>我们收集您在使用互物通服务时提供的信息，包括但不限于：姓名、邮箱地址、公司名称、支付信息等。我们仅收集提供服务所必需的信息。</p><h2>信息使用</h2><p>我们使用收集的信息用于：提供和维护服务、处理交易、发送服务通知、改善用户体验、以及法律要求的合规目的。</p><h2>信息共享</h2><p>我们不会将您的个人信息出售给第三方。我们可能与信任的第三方服务提供商共享必要的信息，以便他们代表我们提供服务（如支付处理、邮件发送等）。</p><h2>数据安全</h2><p>我们采用行业常见的安全措施保护您的数据，包括传输加密（TLS）、访问控制与审计日志等。</p><h2>您的权利</h2><p>您有权访问、更正、删除您的个人数据，以及限制或反对数据处理。您可以通过联系我们来行使这些权利。</p><h2>Cookie</h2><p>我们使用必要的 Cookie 来确保服务正常运行。您可以随时通过浏览器设置管理 Cookie 偏好。</p>',
        'meta' => json_encode(['title' => '隐私政策', 'description' => '互物通隐私政策 - 了解我们如何收集、使用和保护您的个人信息。', 'subtitle' => '最后更新：2026 年 1 月 1 日']),
        'locale' => 'zh-CN',
        'status' => 'published',
        'version' => 1,
        'published_at' => $now,
    ],
    [
        'slug' => 'terms',
        'title' => '服务条款',
        'content' => '<h2>服务说明</h2><p>互物通（以下简称"本服务"）提供企业级软件授权管理解决方案，包括 License 生成与验证、客户管理、支付处理、数据分析等功能。</p><h2>账户注册</h2><p>使用本服务需要注册账户。您必须提供准确、完整的信息，并负责维护账户安全。您对账户下的所有活动负责。</p><h2>使用限制</h2><p>您同意不会：滥用本服务进行非法活动；尝试破解或逆向工程本服务；超出授权范围使用本服务；干扰其他用户正常使用。</p><h2>付费条款</h2><p>付费方案按月度或年度计费。未按时付款可能导致服务暂停。具体计费规则以购买时展示的方案为准。</p><h2>SLA 服务等级</h2><p>如双方另行约定服务等级协议（SLA），则以书面约定为准；未单独约定时，服务按"现状"提供，补偿规则以届时公布的信用政策为准。</p><h2>免责声明</h2><p>本服务按"现状"提供。在法律允许的最大范围内，我们不对因使用本服务产生的间接损失承担责任。</p>',
        'meta' => json_encode(['title' => '服务条款', 'description' => '互物通服务条款 - 使用互物通服务前请仔细阅读。', 'subtitle' => '最后更新：2026 年 1 月 1 日']),
        'locale' => 'zh-CN',
        'status' => 'published',
        'version' => 1,
        'published_at' => $now,
    ],
    [
        'slug' => 'contact',
        'title' => '联系我们',
        'content' => '<h2>联系方式</h2><p>我们很乐意听到您的声音。如有任何问题，请通过以下方式联系我们：</p><ul><li><strong>邮件：</strong> support@huwutong.com</li><li><strong>地址：</strong> 上海市浦东新区张江高科技园区</li><li><strong>工作时间：</strong> 周一至周五 9:00 - 18:00</li></ul><p>您也可以通过管理后台的"系统设置"页面更新联系信息。</p>',
        'meta' => json_encode(['title' => '联系我们', 'description' => '有任何问题？联系互物通团队，我们将在1个工作日内回复。', 'subtitle' => '我们很乐意听到您的声音']),
        'locale' => 'zh-CN',
        'status' => 'published',
        'version' => 1,
        'published_at' => $now,
    ],
];

foreach ($pages as $data) {
    $slug = $data['slug'];
    $exists = DB::table('pages')->where('slug', $slug)->first();

    if (!$exists) {
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        DB::table('pages')->insert($data);
        echo "Created page: {$slug}\n";
        continue;
    }

    if ($force) {
        DB::table('pages')->where('slug', $slug)->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'meta' => $data['meta'],
            'locale' => $data['locale'],
            'status' => $data['status'],
            'version' => DB::raw('version + 1'),
            'published_at' => $data['published_at'],
            'updated_at' => $now,
        ]);
        echo "Updated page: {$slug}\n";
    } else {
        echo "Already exists (use --force to overwrite): {$slug}\n";
    }
}

echo "Done!\n";
