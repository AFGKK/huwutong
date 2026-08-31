<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * 法务/公司 CMS 草稿：充实正文但保持 draft，前台继续走静态精美页。
 */
class LegalCmsPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about',
                'title' => '关于我们',
                'status' => 'draft',
                'meta' => [
                    'title' => '关于我们 - 互物通',
                    'description' => '了解互物通——可核验的企业软件授权基础设施',
                    'subtitle' => '可核验、可控、可落地',
                    'keywords' => '互物通,关于我们,License',
                ],
                'content' => <<<'HTML'
<h2>我们的使命</h2>
<p>互物通为企业软件团队提供可核验的授权管理基础设施：签发、校验、吊销与设备生命周期管理。</p>
<h2>核心能力</h2>
<ul>
<li>Ed25519 签名与离线验证</li>
<li>多平台 SDK 与开放 API</li>
<li>客户门户、支付与订阅</li>
<li>面向 GDPR / PIPL 等常见要求的合规就绪能力（非已获 ISO/SOC 认证声明）</li>
</ul>
<p><em>提示：本页为 CMS 草稿。发布前请完善内容；发布后将替换静态「关于我们」页。</em></p>
HTML,
            ],
            [
                'slug' => 'privacy',
                'title' => '隐私政策',
                'status' => 'draft',
                'meta' => [
                    'title' => '隐私政策 - 互物通',
                    'description' => '互物通隐私政策——我们如何收集、使用和保护您的个人信息',
                    'subtitle' => '最后更新：2026 年 1 月 1 日',
                    'keywords' => '隐私政策,GDPR',
                ],
                'content' => <<<'HTML'
<h2>信息收集</h2>
<p>我们收集您在使用互物通服务时提供的信息，包括但不限于：姓名、邮箱地址、公司名称、支付信息等。我们仅收集提供服务所必需的信息。</p>
<h2>信息使用</h2>
<p>我们使用收集的信息用于：提供和维护服务、处理交易、发送服务通知、改善用户体验、以及法律要求的合规目的。</p>
<h2>信息共享</h2>
<p>我们不会将您的个人信息出售给第三方。我们可能与信任的第三方服务提供商共享必要的信息，以便他们代表我们提供服务（如支付处理、邮件发送等）。</p>
<h2>数据安全</h2>
<p>我们采用行业常见的安全措施保护您的数据，包括传输加密（TLS）、访问控制与审计日志等。</p>
<h2>您的权利</h2>
<p>您有权访问、更正、删除您的个人数据，以及限制或反对数据处理。您可以通过联系我们来行使这些权利。</p>
<h2>Cookie</h2>
<p>我们使用必要的 Cookie 来确保服务正常运行。您可以随时通过浏览器设置管理 Cookie 偏好。</p>
<h2>联系我们</h2>
<p>如对隐私政策有任何疑问，请通过 <a href="/contact">联系我们</a> 页面与我们取得联系。</p>
HTML,
            ],
            [
                'slug' => 'terms',
                'title' => '服务条款',
                'status' => 'draft',
                'meta' => [
                    'title' => '服务条款 - 互物通',
                    'description' => '互物通服务条款——使用互物通服务需遵守的条款与条件',
                    'subtitle' => '最后更新：2026 年 1 月 1 日',
                    'keywords' => '服务条款,SLA',
                ],
                'content' => <<<'HTML'
<h2>服务说明</h2>
<p>互物通（以下简称本服务）提供企业级软件授权管理解决方案，包括 License 生成与验证、客户管理、支付处理、数据分析等功能。</p>
<h2>账户注册</h2>
<p>使用本服务需要注册账户。您必须提供准确、完整的信息，并负责维护账户安全。您对账户下的所有活动负责。</p>
<h2>使用限制</h2>
<p>您同意不会：滥用本服务进行非法活动；尝试破解或逆向工程本服务；超出授权范围使用本服务；干扰其他用户正常使用。</p>
<h2>付费条款</h2>
<p>付费方案按月度或年度计费。未按时付款可能导致服务暂停。具体计费规则以购买时展示的方案为准。</p>
<h2>SLA 服务等级</h2>
<p>如双方另行约定服务等级协议（SLA），则以书面约定为准；未单独约定时，服务按「现状」提供，补偿规则以届时公布的信用政策为准。</p>
<h2>免责声明</h2>
<p>本服务按「现状」提供。在法律允许的最大范围内，我们不对因使用本服务产生的间接损失承担责任。</p>
HTML,
            ],
            [
                'slug' => 'contact',
                'title' => '联系我们（CMS 草稿）',
                'status' => 'draft',
                'meta' => [
                    'title' => '联系我们 - 互物通',
                    'description' => '联系互物通销售与支持团队',
                    'keywords' => '联系我们',
                ],
                'content' => <<<'HTML'
<p>公开路由 <code>/contact</code> 始终使用带表单的静态页。本 CMS 条目仅保存可复用文案草稿，<strong>发布不会覆盖</strong>前台联系表单页。</p>
<p>如需修改联系邮箱、地址、工作时间，请到「站点设置」中配置。</p>
HTML,
            ],
        ];

        foreach ($pages as $data) {
            $page = Page::firstOrNew(['slug' => $data['slug']]);
            // 不覆盖已发布内容；仅补齐 draft 或空正文
            if ($page->exists && $page->status === 'published') {
                continue;
            }
            $page->fill([
                'title' => $data['title'],
                'content' => $data['content'],
                'locale' => 'zh-CN',
                'status' => 'draft',
                'meta' => $data['meta'],
                'version' => $page->version ?: 1,
            ]);
            $page->save();
        }

        $this->command?->info('Legal CMS drafts ready (about/privacy/terms/contact remain draft)');
    }
}
