<?php

namespace Database\Seeders;

use App\Models\QuotaPlan;
use Illuminate\Database\Seeder;

class TenantIsolationSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => '免费版',
                'slug' => 'free',
                'description' => '适合个人开发者和小团队',
                'tier' => 'free',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'is_default' => true,
                'limits' => [
                    'licenses_max' => 10,
                    'devices_max' => 50,
                    'users_max' => 3,
                    'api_keys_max' => 2,
                    'storage_mb' => 100,
                    'bandwidth_gb' => 5,
                    'monthly_api_calls' => 10000,
                    'seats_total' => 50,
                ],
                'features' => [
                    'whitelabel' => false,
                    'sso' => false,
                    'audit_log' => true,
                    'api_access' => true,
                    'custom_domain' => false,
                    'priority_support' => false,
                ],
            ],
            [
                'name' => '初创版',
                'slug' => 'starter',
                'description' => '适合成长中的创业公司',
                'tier' => 'starter',
                'price_monthly' => 299,
                'price_yearly' => 2990,
                'limits' => [
                    'licenses_max' => 50,
                    'devices_max' => 500,
                    'users_max' => 10,
                    'api_keys_max' => 5,
                    'storage_mb' => 1024,
                    'bandwidth_gb' => 50,
                    'monthly_api_calls' => 100000,
                    'seats_total' => 500,
                ],
                'features' => [
                    'whitelabel' => false,
                    'sso' => false,
                    'audit_log' => true,
                    'api_access' => true,
                    'custom_domain' => false,
                    'priority_support' => false,
                ],
            ],
            [
                'name' => '商业版',
                'slug' => 'business',
                'description' => '适合企业级应用',
                'tier' => 'business',
                'price_monthly' => 999,
                'price_yearly' => 9990,
                'limits' => [
                    'licenses_max' => 500,
                    'devices_max' => 5000,
                    'users_max' => 50,
                    'api_keys_max' => 20,
                    'storage_mb' => 10240,
                    'bandwidth_gb' => 500,
                    'monthly_api_calls' => 1000000,
                    'seats_total' => 5000,
                ],
                'features' => [
                    'whitelabel' => true,
                    'sso' => true,
                    'audit_log' => true,
                    'api_access' => true,
                    'custom_domain' => true,
                    'priority_support' => true,
                ],
            ],
            [
                'name' => '企业版',
                'slug' => 'enterprise',
                'description' => '适合大型企业，一切皆可定制',
                'tier' => 'enterprise',
                'price_monthly' => 4999,
                'price_yearly' => 49990,
                'limits' => [
                    'licenses_max' => 999999,
                    'devices_max' => 999999,
                    'users_max' => 999999,
                    'api_keys_max' => 999,
                    'storage_mb' => 999999,
                    'bandwidth_gb' => 999999,
                    'monthly_api_calls' => 99999999,
                    'seats_total' => 999999,
                ],
                'features' => [
                    'whitelabel' => true,
                    'sso' => true,
                    'audit_log' => true,
                    'api_access' => true,
                    'custom_domain' => true,
                    'priority_support' => true,
                    'dedicated_infrastructure' => true,
                    'sla_guarantee' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            QuotaPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $this->command->info('配额方案已初始化: ' . QuotaPlan::count() . ' 个方案');

        // 为已有租户分配默认方案
        $defaultPlan = QuotaPlan::where('is_default', true)->first();
        if ($defaultPlan) {
            $count = \App\Models\Tenant::whereNull('quota_plan_id')->update(['quota_plan_id' => $defaultPlan->id]);
            $this->command->info("已为 {$count} 个租户分配默认配额方案");
        }
    }
}
