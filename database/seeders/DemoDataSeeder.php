<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Device;
use App\Models\TicketCategory;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $admin = User::where('email', 'admin@huwutong.com')->first();

        // ── 产品 ──
        $products = [
            [
                'name' => 'HWT License Pro',
                'slug' => 'hwt-license-pro',
                'description' => '企业级软件授权管理系统 - 专业版',
                'version' => '2.1.0',
                'modules' => ['core', 'offline', 'device-binding', 'api'],
                'is_active' => true,
            ],
            [
                'name' => 'HWT License Enterprise',
                'slug' => 'hwt-license-enterprise',
                'description' => '企业级软件授权管理系统 - 企业版（含所有功能）',
                'version' => '3.0.0',
                'modules' => ['core', 'offline', 'device-binding', 'api', 'sso', 'audit', 'white-label'],
                'is_active' => true,
            ],
            [
                'name' => 'HWT Cloud SDK',
                'slug' => 'hwt-cloud-sdk',
                'description' => '云端集成 SDK，快速接入授权服务',
                'version' => '1.2.0',
                'modules' => ['activation', 'validation', 'feature-flags'],
                'is_active' => true,
            ],
        ];

        foreach ($products as $data) {
            Product::firstOrCreate(['slug' => $data['slug']], $data);
        }

        $product = Product::where('slug', 'hwt-license-pro')->first();

        // ── 客户 ──
        $customer = Customer::firstOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $admin->id],
            [
                'type' => 'company',
                'level' => 'enterprise',
                'status' => 'active',
            ]
        );

        $customer2 = Customer::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'user_id' => null,
                'type' => 'company',
                'level' => 'standard',
                'status' => 'active',
            ]
        );

        // ── License ──
        $licenseKey = 'HWT-' . strtoupper(substr(md5(uniqid()), 0, 20));

        License::firstOrCreate(
            ['license_key' => $licenseKey],
            [
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'customer_id' => $customer->id,
                'type' => 'enterprise',
                'status' => 'active',
                'seats' => 50,
                'max_devices' => 100,
                'expires_at' => now()->addMonths(9),
                'metadata' => ['region' => 'cn', 'tier' => 'gold'],
            ]
        );

        // ── 设备（演示） ──
        $license = License::where('license_key', $licenseKey)->first();
        Device::firstOrCreate(
            ['fingerprint' => 'demo-fp-win-' . md5('windows-server-01')],
            [
                'tenant_id' => $tenantId,
                'license_id' => $license->id,
                'platform' => 'windows',
                'trust_score' => 100,
                'is_blacklisted' => false,
                'is_virtual' => false,
                'last_seen_at' => now()->subHours(2),
                'metadata' => json_encode(['hostname' => 'WS-CORE-01', 'ip' => '192.168.1.100']),
            ]
        );
        Device::firstOrCreate(
            ['fingerprint' => 'demo-fp-linux-' . md5('linux-app-01')],
            [
                'tenant_id' => $tenantId,
                'license_id' => $license->id,
                'platform' => 'linux',
                'trust_score' => 90,
                'is_blacklisted' => false,
                'is_virtual' => true,
                'last_seen_at' => now()->subDays(1),
                'metadata' => json_encode(['hostname' => 'LNX-APP-01', 'ip' => '10.0.0.50']),
            ]
        );

        // ── 订阅 ──
        Subscription::firstOrCreate(
            ['tenant_id' => $tenantId, 'customer_id' => $customer->id],
            [
                'product_id' => $product->id,
                'plan' => 'enterprise-yearly',
                'status' => 'active',
                'price' => 9999.00,
                'currency' => 'CNY',
                'billing_period' => 'yearly',
                'starts_at' => now()->subMonths(3),
                'ends_at' => now()->addMonths(9),
                'trial_ends_at' => null,
                'grace_days' => 7,
                'auto_renew' => true,
            ]
        );

        // ── 工单分类 ──
        $ticketCatSlugs = ['tech-support', 'billing', 'feature-request', 'bug-report', 'other'];
        $ticketCatNames = ['技术支持', '计费问题', '功能咨询', 'Bug 报告', '其他'];
        foreach ($ticketCatNames as $i => $name) {
            TicketCategory::firstOrCreate(
                ['slug' => $ticketCatSlugs[$i]],
                ['name' => $name, 'sort_order' => $i + 1, 'is_active' => true]
            );
        }

        // ── 帮助中心分类 ──
        $kbCats = [
            ['name' => '快速入门', 'slug' => 'getting-started', 'description' => '帮助新用户快速上手', 'sort_order' => 1],
            ['name' => '授权管理', 'slug' => 'license-management', 'description' => 'License 相关操作指南', 'sort_order' => 2],
            ['name' => 'API 集成', 'slug' => 'api-integration', 'description' => 'SDK 集成和 API 调用', 'sort_order' => 3],
            ['name' => '常见问题', 'slug' => 'faq', 'description' => 'FAQ 汇总', 'sort_order' => 4],
        ];
        foreach ($kbCats as $cat) {
            KbCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // ── 帮助中心文章 ──
        $kbCat = KbCategory::where('slug', 'getting-started')->first();
        KbArticle::firstOrCreate(
            ['slug' => 'getting-started-guide'],
            [
                'category_id' => $kbCat->id,
                'author_id' => $admin->id,
                'title' => '如何开始使用 HWT License',
                'slug' => 'getting-started-guide',
                'content' => "<h2>欢迎使用 HWT License</h2><p>HWT License 是企业级软件授权管理系统，本文将帮助您快速上手。</p><h3>第一步：创建产品</h3><p>进入「产品管理」页面，创建您的软件产品，配置版本和模块信息。</p><h3>第二步：创建 License</h3><p>为您的客户创建授权 License，支持多种类型（试用版/标准版/企业版）。</p><h3>第三步：集成 SDK</h3><p>在您的软件中集成 HWT SDK，实现自动激活和验证。</p>",
                'status' => 'published',
                'view_count' => 128,
                'helpful_count' => 35,
                'published_at' => now()->subDays(30),
            ]
        );

        $this->command->info('演示数据创建成功!');
        $this->command->info("  - License: {$licenseKey}");
        $this->command->info('  - 3 个产品, 2 个客户, 2 个设备, 1 个订阅');
        $this->command->info('  - 5 个工单分类, 4 个帮助中心分类, 1 篇文章');
    }
}
