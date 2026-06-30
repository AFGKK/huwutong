<?php

// M2-129 租户内团队协作 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 角色定义
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'admin' => '管理员',
        'finance' => '财务',
        'developer' => '开发者',
        'readonly' => '只读',
    ],

    /*
    |--------------------------------------------------------------------------
    | 角色权限
    |--------------------------------------------------------------------------
    */
    'role_permissions' => [
        'admin' => ['invite', 'remove', 'manage_roles', 'transfer_admin', 'view_billing', 'view_licenses', 'view_devices', 'manage_api_keys'],
        'finance' => ['view_billing', 'view_invoices'],
        'developer' => ['view_licenses', 'view_devices', 'manage_api_keys', 'view_usage'],
        'readonly' => ['view_licenses', 'view_devices'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 邀请策略
    |--------------------------------------------------------------------------
    */
    'invitation' => [
        'expires_hours' => 168,  // 7天
        'max_pending_per_tenant' => 50,
        'require_email_verification' => true,
        'allow_batch_invite' => true,
        'max_batch_size' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | 成员限制
    |--------------------------------------------------------------------------
    */
    'member_limits' => [
        'max_members' => (int) env('TENANT_TEAM_MAX_MEMBERS', 100),
        'min_admin_count' => 1,
    ],
];
