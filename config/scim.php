<?php

// M2-51 SCIM 自动用户同步配置

return [

    /*
    |--------------------------------------------------------------------------
    | SCIM 2.0 协议配置
    |--------------------------------------------------------------------------
    */
    'protocol' => [
        // SCIM 端点基础路径
        'base_path' => env('SCIM_BASE_PATH', '/api/scim/v2'),
        // 支持的 SCIM 版本
        'version' => '2.0',
        // 内容类型
        'content_type' => 'application/scim+json',
        // 每页返回的最大记录数
        'max_results' => 100,
        // 默认每页记录数
        'default_results' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | 支持的 IdP 提供商
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'okta' => [
            'label' => 'Okta',
            'docs_url' => 'https://developer.okta.com/docs/guides/scim-provisioning-integration/',
            'default_base_url' => 'https://{your-org}.okta.com',
        ],
        'azure_ad' => [
            'label' => 'Azure AD / Entra ID',
            'docs_url' => 'https://learn.microsoft.com/en-us/azure/active-directory/app-provisioning/',
            'default_base_url' => 'https://{tenant-name}.scim.azure.com',
        ],
        'onelogin' => [
            'label' => 'OneLogin',
            'docs_url' => 'https://developers.onelogin.com/scim',
            'default_base_url' => 'https://{your-domain}.onelogin.com',
        ],
        'generic' => [
            'label' => '通用 SCIM',
            'docs_url' => 'https://datatracker.ietf.org/doc/html/rfc7644',
            'default_base_url' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 用户同步配置
    |--------------------------------------------------------------------------
    */
    'user_sync' => [
        // 默认角色映射（IdP 角色 → 互物通角色）
        'default_role' => 'customer',
        // 冲突处理策略: skip / overwrite / merge
        'conflict_strategy' => env('SCIM_CONFLICT_STRATEGY', 'overwrite'),
        // 自动激活新同步的用户
        'auto_activate' => env('SCIM_AUTO_ACTIVATE', true),
        // 是否在 IdP 删除用户时自动禁用互物通账号
        'auto_disable_on_delete' => env('SCIM_AUTO_DISABLE_ON_DELETE', true),
        // 用户名字段映射
        'username_field' => 'userName',
        // 邮箱字段
        'email_field' => 'emails[type eq "work"].value',
    ],

    /*
    |--------------------------------------------------------------------------
    | 组同步配置
    |--------------------------------------------------------------------------
    */
    'group_sync' => [
        'enabled' => env('SCIM_GROUP_SYNC', false),
        // 组名 → 角色映射规则
        'group_role_mapping' => [
            // 'Admin' => 'admin',
            // 'Developer' => 'developer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 同步调度
    |--------------------------------------------------------------------------
    */
    'schedule' => [
        // 自动同步频率（分钟）
        'auto_sync_interval' => env('SCIM_AUTO_SYNC_INTERVAL', 60),
        // 同步超时（秒）
        'sync_timeout' => 300,
        // 失败重试次数
        'max_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | 字段映射默认模板
    |--------------------------------------------------------------------------
    */
    'field_mappings' => [
        'user' => [
            ['scim_field' => 'userName', 'local_field' => 'name', 'required' => true],
            ['scim_field' => 'name.givenName', 'local_field' => 'first_name', 'required' => false],
            ['scim_field' => 'name.familyName', 'local_field' => 'last_name', 'required' => false],
            ['scim_field' => 'emails[type eq "work"].value', 'local_field' => 'email', 'required' => true],
            ['scim_field' => 'active', 'local_field' => 'is_active', 'required' => false],
            ['scim_field' => 'phoneNumbers[type eq "work"].value', 'local_field' => 'phone', 'required' => false],
        ],
        'group' => [
            ['scim_field' => 'displayName', 'local_field' => 'name', 'required' => true],
            ['scim_field' => 'members', 'local_field' => 'member_ids', 'required' => false],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('SCIM_LOG_CHANNEL', 'stack'),
        'level' => env('SCIM_LOG_LEVEL', 'info'),
        // 同步记录保留天数
        'retention_days' => 90,
    ],

];
