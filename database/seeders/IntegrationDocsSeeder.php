<?php

namespace Database\Seeders;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 刷新公开集成文档相关的帮助中心文章（可重复执行）。
 */
class IntegrationDocsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@huwutong.com')->first()
            ?? User::query()->first();

        if (! $admin) {
            $this->command?->warn('IntegrationDocsSeeder skipped: no user for author_id');

            return;
        }

        $cats = [
            ['name' => '快速入门', 'slug' => 'getting-started', 'description' => '帮助新用户快速上手', 'sort_order' => 1],
            ['name' => '授权管理', 'slug' => 'license-management', 'description' => 'License 相关操作指南', 'sort_order' => 2],
            ['name' => 'API 集成', 'slug' => 'api-integration', 'description' => 'SDK 集成和 API 调用', 'sort_order' => 3],
            ['name' => '常见问题', 'slug' => 'faq', 'description' => 'FAQ 汇总', 'sort_order' => 4],
        ];

        foreach ($cats as $cat) {
            KbCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        $articles = [
            [
                'slug' => 'getting-started-guide',
                'category' => 'getting-started',
                'title' => '如何开始使用 HWT License',
                'content' => <<<'HTML'
<h2>欢迎使用互物通</h2>
<p>互物通（HWT License）是企业级软件授权管理系统。按下面四步即可完成首个产品授权闭环。</p>
<ol>
<li>注册账号并创建产品（版本 / 模块）</li>
<li>创建 API Key，并生成客户 License</li>
<li>按语言安装 SDK，完成 activate / validate</li>
<li>上线后通过 Webhook 与错误码处理异常状态</li>
</ol>
<p>推荐阅读：</p>
<ul>
<li><a href="/docs/quickstart">5 分钟快速入门</a></li>
<li><a href="/sdk">SDK 下载与语言列表</a></li>
<li><a href="/docs">开发者文档中心</a></li>
</ul>
HTML,
            ],
            [
                'slug' => 'license-activation-guide',
                'category' => 'license-management',
                'title' => 'License 激活与设备绑定',
                'content' => <<<'HTML'
<h2>激活</h2>
<p>客户购买后可通过客户门户或 SDK <code>activate()</code> 完成激活，支持在线验证与离线包。</p>
<h3>设备绑定</h3>
<p>每个 License 可绑定限定数量的设备；超出上限将返回 <code>DEVICE_LIMIT</code>。</p>
<h3>换机</h3>
<p>先调用 <code>deactivate()</code> 释放旧设备，再在新设备上 activate。</p>
<p>相关文档：<a href="/api-docs">API 参考</a> · <a href="/docs/error-codes">错误码</a> · <a href="/docs/webhooks">Webhook</a></p>
HTML,
            ],
            [
                'slug' => 'sdk-integration-quickstart',
                'category' => 'api-integration',
                'title' => 'SDK 集成快速入门',
                'content' => <<<'HTML'
<h2>多语言 SDK</h2>
<p>互物通提供 PHP、Node.js、Python、Go、Java、C#、Flutter、Electron、Tauri SDK。</p>
<h3>分语言教程</h3>
<ul>
<li><a href="/docs/sdk/php">PHP</a></li>
<li><a href="/docs/sdk/node">Node.js</a></li>
<li><a href="/docs/sdk/python">Python</a></li>
<li><a href="/docs/sdk/go">Go</a></li>
<li><a href="/docs/sdk/java">Java</a></li>
<li><a href="/docs/sdk/csharp">C#</a></li>
<li><a href="/docs/sdk/flutter">Flutter</a></li>
<li><a href="/docs/sdk/electron">Electron</a></li>
<li><a href="/docs/sdk/tauri">Tauri</a></li>
</ul>
<h3>验证 License</h3>
<p>安装 SDK 后初始化 Client，调用 <code>validate()</code> 即可完成授权校验；完整端点见 <a href="/api-docs">API 文档</a>。</p>
<p>仓库示例工程：<code>examples/php</code>、<code>examples/nodejs</code>、<code>examples/python</code>。</p>
HTML,
            ],
            [
                'slug' => 'api-and-webhook-guide',
                'category' => 'api-integration',
                'title' => 'API、错误码与 Webhook',
                'content' => <<<'HTML'
<h2>REST API</h2>
<p>公开 API 参考见 <a href="/api-docs">/api-docs</a>，包含激活、验证、离线授权与心跳等端点示例。</p>
<h2>错误码</h2>
<p>统一错误码（M2-34）见 <a href="/docs/error-codes">/docs/error-codes</a>，例如 <code>LICENSE_EXPIRED</code>、<code>DEVICE_LIMIT</code>。</p>
<h2>Webhook</h2>
<p>订阅 <code>license.activated</code> 等事件，配置验签后接收状态变更推送：<a href="/docs/webhooks">Webhook 指南</a>。</p>
HTML,
            ],
            [
                'slug' => 'pricing-and-refund-faq',
                'category' => 'faq',
                'title' => '定价与退款常见问题',
                'content' => <<<'HTML'
<h2>如何升级套餐？</h2>
<p>在客户门户提交升级申请，或联系销售团队。也可从 <a href="/pricing">定价页</a> 发起自助订阅。</p>
<h2>如何申请退款？</h2>
<p>已支付订单可在「我的订单」中提交退款申请，审核通过后原路退回。</p>
<p>开发接入问题请优先查看 <a href="/docs">文档中心</a> 与 <a href="/help">帮助中心</a>。</p>
HTML,
            ],
        ];

        foreach ($articles as $article) {
            $category = KbCategory::where('slug', $article['category'])->first();
            if (! $category) {
                continue;
            }

            KbArticle::updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'category_id' => $category->id,
                    'author_id' => $admin->id,
                    'title' => $article['title'],
                    'content' => $article['content'],
                    'status' => 'published',
                    'published_at' => now(),
                ]
            );
        }
    }
}
