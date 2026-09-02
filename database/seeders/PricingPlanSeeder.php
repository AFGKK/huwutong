<?php

namespace Database\Seeders;

use App\Models\PricingPlan;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

class PricingPlanSeeder extends Seeder
{
    /** @return array<string, mixed> */
    private function comparisonFor(string $slug): array
    {
        return match ($slug) {
            'free' => [
                'sdk_languages' => 3,
                'live_chat' => true,
                'trial_management' => '7',
            ],
            'basic' => [
                'webhook' => true,
                'sdk_languages' => 6,
                'offline_licensing' => true,
                'device_fingerprint' => true,
                'live_chat' => true,
                'canned_replies' => 10,
                'sla_options' => 'negotiable',
                'trial_management' => '14',
            ],
            'pro' => [
                'rbac' => true,
                'webhook' => true,
                'webhook_retry_filter' => 'retry_filter',
                'customer_portal' => true,
                'multi_currency' => true,
                'custom_domain' => true,
                'sdk_languages' => 6,
                'offline_licensing' => true,
                'device_fingerprint' => true,
                'floating_seats' => true,
                'ai_insights' => true,
                'live_chat' => true,
                'ai_support' => true,
                'human_handoff' => true,
                'canned_replies' => 100,
                'agent_groups' => 5,
                'im_notifications' => true,
                'sla_options' => 'negotiable',
                'data_export' => 'csv',
                'trial_management' => '30',
            ],
            'enterprise' => [
                'rbac' => true,
                'webhook' => true,
                'webhook_retry_filter' => 'full',
                'customer_portal' => true,
                'multi_currency' => true,
                'custom_domain' => true,
                'sdk_languages' => 6,
                'offline_licensing' => true,
                'device_fingerprint' => true,
                'floating_seats' => true,
                'oem_whitelabel' => true,
                'sso_saml' => true,
                'audit_logs' => true,
                'ai_insights' => true,
                'live_chat' => true,
                'ai_support' => true,
                'human_handoff' => true,
                'canned_replies' => 500,
                'agent_groups' => -1,
                'im_notifications' => true,
                'sla_options' => 'written',
                'data_export' => 'csv_json',
                'trial_management' => 'custom',
                'dedicated_csm' => true,
                'private_deploy' => true,
            ],
            default => [],
        };
    }

    public function run(): void
    {
        // 默认租户（假设 tenant_id=1）
        $tenantId = 1;

        $plans = [
            [
                'tenant_id' => $tenantId,
                'slug' => 'free',
                'name' => '免费版',
                'description' => '适合个人开发者和小型项目',
                'billing_period' => 'monthly',
                'price_monthly' => 0,
                'price_quarterly' => 0,
                'price_semi_annually' => 0,
                'price_yearly' => 0,
                'currency' => 'CNY',
                'trial_days' => 0,
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
                'badge' => null,
                'features' => [
                    '1 个产品',
                    '最多 100 个激活',
                    '基础 API 访问',
                    '社区支持',
                ],
                'limits' => [
                    'max_products' => 1,
                    'max_activations' => 100,
                    'api_rate_limit' => 60,
                    'max_api_keys' => 2,
                    'team_members' => 1,
                ],
            ],
            [
                'tenant_id' => $tenantId,
                'slug' => 'basic',
                'name' => '基础版',
                'description' => '适合初创团队和小型企业',
                'billing_period' => 'monthly',
                'price_monthly' => 99,
                'price_quarterly' => 267,   // 9折
                'price_semi_annually' => 504, // 85折
                'price_yearly' => 948,      // 8折
                'currency' => 'CNY',
                'trial_days' => 14,
                'sort_order' => 2,
                'is_public' => true,
                'badge' => null,
                'features' => [
                    '最多 3 个产品',
                    '最多 1,000 个激活',
                    '标准 API 访问',
                    '邮件支持',
                    '基本分析统计',
                ],
                'limits' => [
                    'max_products' => 3,
                    'max_activations' => 1000,
                    'api_rate_limit' => 300,
                    'max_api_keys' => 5,
                    'team_members' => 3,
                ],
            ],
            [
                'tenant_id' => $tenantId,
                'slug' => 'pro',
                'name' => '专业版',
                'description' => '适合快速成长的业务',
                'price_monthly' => 299,
                'price_quarterly' => 807,    // 9折
                'price_semi_annually' => 1524, // 85折
                'price_yearly' => 2868,      // 8折
                'currency' => 'CNY',
                'trial_days' => 14,
                'sort_order' => 3,
                'is_public' => true,
                'badge' => 'popular',
                'features' => [
                    '最多 10 个产品',
                    '最多 10,000 个激活',
                    '高级 API 访问 (Webhooks)',
                    '优先邮件 + 在线支持',
                    '客户 Portal',
                    '高级分析 & 报表',
                    '自定义域名',
                    'Teams (5 人)',
                ],
                'limits' => [
                    'max_products' => 10,
                    'max_activations' => 10000,
                    'api_rate_limit' => 1000,
                    'max_api_keys' => 20,
                    'team_members' => 5,
                ],
            ],
            [
                'tenant_id' => $tenantId,
                'slug' => 'enterprise',
                'name' => '企业版',
                'description' => '适合大规模部署的企业',
                'price_monthly' => 999,
                'price_quarterly' => 2697,   // 9折
                'price_semi_annually' => 5094, // 85折
                'price_yearly' => 9588,      // 8折
                'currency' => 'CNY',
                'trial_days' => 30,
                'sort_order' => 4,
                'is_public' => true,
                'badge' => 'best_value',
                'features' => [
                    '无限产品',
                    '无限激活',
                    '全部 API 功能',
                    '专属客户经理',
                    'SLA 保障 (99.9%)',
                    'SSO / SAML',
                    '审计日志',
                    '自定义合同 & 发票',
                    '无限团队成员',
                ],
                'limits' => [
                    'max_products' => -1,
                    'max_activations' => -1,
                    'api_rate_limit' => 5000,
                    'max_api_keys' => 100,
                    'team_members' => -1,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            $plan['billing_period'] = $plan['billing_period'] ?? 'monthly';
            $plan['is_active'] = $plan['is_active'] ?? true;
            $plan['metadata'] = [
                'comparison' => $this->comparisonFor($plan['slug']),
            ];
            PricingPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        // 创建示例优惠券
        $coupons = [
            [
                'tenant_id' => $tenantId,
                'code' => 'WELCOME20',
                'name' => '新用户 8 折',
                'description' => '新用户首单 8 折优惠',
                'type' => 'percentage',
                'value' => 20,
                'currency' => 'CNY',
                'minimum_order_amount' => 0,
                'maximum_discount' => 500,
                'usage_limit' => 100,
                'usage_limit_per_user' => 1,
                'applicable_billing_periods' => ['monthly', 'yearly'],
                'status' => 'active',
                'expires_at' => now()->addMonths(6),
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'YEARLY50',
                'name' => '年付 5 折',
                'description' => '年付方案享受 5 折优惠',
                'type' => 'percentage',
                'value' => 50,
                'currency' => 'CNY',
                'minimum_order_amount' => 0,
                'maximum_discount' => 2000,
                'usage_limit' => 50,
                'usage_limit_per_user' => 1,
                'applicable_billing_periods' => ['yearly'],
                'status' => 'active',
                'expires_at' => now()->addMonths(3),
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'FREE30',
                'name' => '30 天免费试用',
                'description' => '30 天免费试用专业版',
                'type' => 'free_trial',
                'value' => 30,
                'currency' => 'CNY',
                'usage_limit' => 200,
                'usage_limit_per_user' => 1,
                'applicable_plans' => ['pro', 'enterprise'],
                'status' => 'active',
                'expires_at' => now()->addMonths(12),
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(
                ['code' => $coupon['code']],
                $coupon
            );
        }
    }
}
