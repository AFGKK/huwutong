<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 创建默认租户
        $tenant = Tenant::firstOrCreate(
            ['id' => 1],
            [
                'name' => '互物通科技',
                'domain' => 'huwutong.com',
                'subscription_plan' => 'enterprise',
                'status' => 'active',
                'data_region' => 'cn-beijing',
                'branding' => [
                    'primary_color' => '#409EFF',
                    'logo_url' => '',
                    'favicon_url' => '',
                ],
                'mfa_policy' => 'optional',
                'allowed_ips' => [],
            ]
        );

        // 设置权限 team 上下文为当前租户
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // 创建超级管理员
        $admin = User::firstOrCreate(
            ['email' => 'admin@huwutong.com'],
            [
                'name' => '超级管理员',
                'password' => bcrypt('admin123'),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('super-admin');

        // 创建租户管理员
        $tenantAdmin = User::firstOrCreate(
            ['email' => 'tenant@huwutong.com'],
            [
                'name' => '租户管理员',
                'password' => bcrypt('admin123'),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $tenantAdmin->assignRole('tenant-admin');

        // 创建普通用户（演示用）
        $demo = User::firstOrCreate(
            ['email' => 'demo@huwutong.com'],
            [
                'name' => '演示用户',
                'password' => bcrypt('demo123'),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        if (! $demo->hasRole('developer')) {
            $demo->assignRole('developer');
        }

        $this->command->info('管理员账号创建成功:');
        $this->command->info('  - admin@huwutong.com / admin123 (超级管理员)');
        $this->command->info('  - tenant@huwutong.com / admin123 (租户管理员)');
        $this->command->info('  - demo@huwutong.com / demo123 (演示用户)');
    }
}
