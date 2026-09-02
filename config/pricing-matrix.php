<?php

/**
 * 定价对比矩阵行定义（标签见 lang 下 pricing_matrix.php；单元格值来自套餐 limits / metadata.comparison）
 */
return [
    'rows' => [
        ['key' => 'max_products', 'source' => 'limits', 'type' => 'count', 'unit' => 'products'],
        ['key' => 'max_activations', 'source' => 'limits', 'type' => 'count', 'unit' => 'activations'],
        ['key' => 'api_rate_limit', 'source' => 'limits', 'type' => 'rate'],
        ['key' => 'max_api_keys', 'source' => 'limits', 'type' => 'count', 'unit' => 'keys'],
        ['key' => 'team_members', 'source' => 'limits', 'type' => 'count', 'unit' => 'members'],
        ['key' => 'rbac', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'webhook', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'webhook_retry_filter', 'source' => 'comparison', 'type' => 'enum', 'enum_prefix' => 'webhook_retry'],
        ['key' => 'customer_portal', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'multi_currency', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'custom_domain', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'sdk_languages', 'source' => 'comparison', 'type' => 'count', 'unit' => 'languages'],
        ['key' => 'offline_licensing', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'device_fingerprint', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'floating_seats', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'oem_whitelabel', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'sso_saml', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'audit_logs', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'ai_insights', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'live_chat', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'ai_support', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'human_handoff', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'canned_replies', 'source' => 'comparison', 'type' => 'count', 'unit' => 'replies'],
        ['key' => 'agent_groups', 'source' => 'comparison', 'type' => 'count', 'unit' => 'groups'],
        ['key' => 'im_notifications', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'sla_options', 'source' => 'comparison', 'type' => 'enum', 'enum_prefix' => 'sla'],
        ['key' => 'data_export', 'source' => 'comparison', 'type' => 'enum', 'enum_prefix' => 'data_export'],
        ['key' => 'trial_management', 'source' => 'comparison', 'type' => 'enum', 'enum_prefix' => 'trial'],
        ['key' => 'dedicated_csm', 'source' => 'comparison', 'type' => 'boolean'],
        ['key' => 'private_deploy', 'source' => 'comparison', 'type' => 'boolean'],
    ],
];
