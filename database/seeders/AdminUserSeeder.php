<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 创建默认租户（管理员强制 MFA）
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
                'mfa_policy' => 'required_for_admin',
                'allowed_ips' => [],
            ]
        );

        // 已有租户也升级策略
        if ($tenant->mfa_policy === 'optional' || $tenant->mfa_policy === null) {
            $tenant->update(['mfa_policy' => 'required_for_admin']);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $adminPassword = (string) env('ADMIN_DEFAULT_PASSWORD', '');
        if ($adminPassword === '' || in_array(strtolower($adminPassword), ['admin123', 'password', '12345678'], true)) {
            $adminPassword = 'Hwt!' . Str::password(12, symbols: true);
            $this->command?->warn('ADMIN_DEFAULT_PASSWORD 未设置或过弱，已生成随机强密码（请立即保存）:');
            $this->command?->warn('  admin@huwutong.com / ' . $adminPassword);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@huwutong.com'],
            [
                'name' => '超级管理员',
                'password' => bcrypt($adminPassword),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $admin->assignRole('super-admin');

        $tenantAdmin = User::firstOrCreate(
            ['email' => 'tenant@huwutong.com'],
            [
                'name' => '租户管理员',
                'password' => bcrypt($adminPassword),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        $tenantAdmin->assignRole('tenant-admin');

        $demo = User::firstOrCreate(
            ['email' => 'demo@huwutong.com'],
            [
                'name' => '演示用户',
                'password' => bcrypt('Demo!' . Str::password(10, symbols: false)),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ]
        );
        if (! $demo->hasRole('developer')) {
            $demo->assignRole('developer');
        }

        $this->command->info('管理员账号就绪（密码见 ADMIN_DEFAULT_PASSWORD 或上方随机输出）');
        $this->command->info('  - admin@huwutong.com (超级管理员)');
        $this->command->info('  - tenant@huwutong.com (租户管理员)');
        $this->command->info('  - demo@huwutong.com (演示用户)');
        $this->command->info('  租户 MFA 策略: required_for_admin');
    }
}
