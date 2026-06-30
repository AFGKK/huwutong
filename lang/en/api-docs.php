<?php

return [
    // 页面标题
    'page_title' => 'API Documentation',
    'page_description' => 'Interactive API documentation with multilingual support',

    // 分组名称
    'groups' => [
        'auth' => 'Authentication',
        'licenses' => 'License Management',
        'subscriptions' => 'Subscription Management',
        'invoices' => 'Invoice Management',
        'customers' => 'Customer Management',
        'products' => 'Product Management',
        'api-keys' => 'API Keys',
        'webhooks' => 'Webhooks',
        'features' => 'Feature Flags',
        'devices' => 'Device Management',
        'analytics' => 'Analytics & Reports',
        'billing' => 'Billing',
        'admin' => 'System Administration',
        'audit' => 'Audit & Compliance',
        'ecommerce' => 'E-commerce',
        'collaboration' => 'Collaboration',
        'security' => 'Security',
        'notifications' => 'Notifications',
        'sso' => 'SSO & Identity',
        'scim' => 'SCIM Provisioning',
    ],

    // 状态标签
    'status' => [
        'active' => 'Active',
        'deprecated' => 'Deprecated',
        'beta' => 'Beta',
        'experimental' => 'Experimental',
    ],

    // 方法标签
    'methods' => [
        'GET' => 'GET',
        'POST' => 'POST',
        'PUT' => 'PUT',
        'PATCH' => 'PATCH',
        'DELETE' => 'DELETE',
    ],

    // 常用字段
    'fields' => [
        'id' => 'ID',
        'tenant_id' => 'Tenant ID',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'deleted_at' => 'Deleted At',
        'status' => 'Status',
        'type' => 'Type',
        'name' => 'Name',
        'description' => 'Description',
        'email' => 'Email',
        'phone' => 'Phone',
        'metadata' => 'Metadata',
        'config' => 'Configuration',
        'settings' => 'Settings',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
    ],

    // 文档导航
    'nav' => [
        'overview' => 'Overview',
        'endpoints' => 'Endpoints',
        'schemas' => 'Schemas',
        'code_examples' => 'Code Examples',
        'changelog' => 'Changelog',
        'sdk' => 'SDKs & Clients',
        'test_console' => 'Test Console',
    ],

    // 通用
    'common' => [
        'search' => 'Search endpoints...',
        'filter' => 'Filter',
        'export_openapi' => 'Export OpenAPI',
        'scan_routes' => 'Scan Routes',
        'no_description' => 'No description provided.',
        'request' => 'Request',
        'response' => 'Response',
        'parameters' => 'Parameters',
        'headers' => 'Headers',
        'request_body' => 'Request Body',
        'responses' => 'Responses',
        'example' => 'Example',
        'schema' => 'Schema',
        'authorization' => 'Authorization',
        'rate_limiting' => 'Rate Limiting',
        'pagination' => 'Pagination',
        'errors' => 'Errors',
        'base_url' => 'Base URL',
        'api_version' => 'API Version',
    ],

    // 语言名称
    'language_name' => 'English',
];
