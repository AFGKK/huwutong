<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 权限定义（按领域分组） ──

        $permissionsByGroup = [

            'tenants' => [
                'tenants.view', 'tenants.create', 'tenants.edit', 'tenants.delete',
            ],

            'users' => [
                'users.view', 'users.create', 'users.edit', 'users.delete',
            ],

            'customers' => [
                'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            ],

            'products' => [
                'products.view', 'products.create', 'products.edit', 'products.delete',
            ],

            'licenses' => [
                'licenses.view', 'licenses.create', 'licenses.edit', 'licenses.delete',
                'licenses.activate', 'licenses.deactivate', 'licenses.revoke',
            ],

            'devices' => [
                'devices.view', 'devices.blacklist',
            ],

            'subscriptions' => [
                'subscriptions.view', 'subscriptions.create', 'subscriptions.cancel',
            ],

            'invoices' => [
                'invoices.view', 'invoices.create', 'invoices.refund',
            ],

            'earnings' => [
                'earnings.view', 'earnings.withdraw', 'earnings.approve_withdrawal',
            ],

            'logs' => [
                'logs.view', 'logs.export',
            ],

            'rbac' => [
                'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
                'permissions.view',
            ],

            'settings' => [
                'settings.view', 'settings.edit',
            ],
        ];

        // 创建所有权限（使用 firstOrCreate 避免重复）
        foreach ($permissionsByGroup as $group => $permissions) {
            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }
        }

        // ── 角色定义（使用 firstOrCreate 避免重复） ──

        // 超级管理员（平台级别，拥有所有权限）
        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);
        $superAdmin->givePermissionTo(Permission::all());

        // 租户管理员
        $tenantAdmin = Role::firstOrCreate([
            'name' => 'tenant-admin',
            'guard_name' => 'web',
        ]);
        $tenantAdmin->givePermissionTo([
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'products.view',
            'licenses.view', 'licenses.create', 'licenses.edit', 'licenses.activate',
            'devices.view',
            'subscriptions.view', 'subscriptions.create', 'subscriptions.cancel',
            'invoices.view',
            'earnings.view',
            'logs.view',
            'roles.view', 'permissions.view',
            'settings.view', 'settings.edit',
        ]);

        // 财务角色
        $finance = Role::firstOrCreate([
            'name' => 'finance',
            'guard_name' => 'web',
        ]);
        $finance->givePermissionTo([
            'invoices.view', 'invoices.create', 'invoices.refund',
            'subscriptions.view',
            'earnings.view', 'earnings.approve_withdrawal',
            'customers.view',
        ]);

        // 开发者角色
        $developer = Role::firstOrCreate([
            'name' => 'developer',
            'guard_name' => 'web',
        ]);
        $developer->givePermissionTo([
            'licenses.view', 'licenses.create', 'licenses.edit', 'licenses.activate', 'licenses.deactivate',
            'devices.view', 'devices.blacklist',
            'products.view',
            'logs.view', 'logs.export',
        ]);

        // 只读角色
        $readonly = Role::firstOrCreate([
            'name' => 'readonly',
            'guard_name' => 'web',
        ]);
        $readonly->givePermissionTo([
            'users.view',
            'customers.view',
            'products.view',
            'licenses.view',
            'devices.view',
            'subscriptions.view',
            'invoices.view',
            'earnings.view',
            'logs.view',
            'permissions.view',
        ]);
    }
}
