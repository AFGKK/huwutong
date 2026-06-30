<?php

// M3-42 CRM 集成配置

return [
    'providers' => [
        'hubspot' => [
            'enabled' => env('CRM_HUBSPOT_ENABLED', false),
            'api_key' => env('CRM_HUBSPOT_API_KEY', ''),
            'api_base' => 'https://api.hubapi.com',
            'api_version' => 'v3',
            'portal_id' => env('CRM_HUBSPOT_PORTAL_ID', ''),
        ],
        'salesforce' => [
            'enabled' => env('CRM_SALESFORCE_ENABLED', false),
            'client_id' => env('CRM_SALESFORCE_CLIENT_ID', ''),
            'client_secret' => env('CRM_SALESFORCE_CLIENT_SECRET', ''),
            'username' => env('CRM_SALESFORCE_USERNAME', ''),
            'password' => env('CRM_SALESFORCE_PASSWORD', ''),
            'security_token' => env('CRM_SALESFORCE_SECURITY_TOKEN', ''),
            'login_url' => 'https://login.salesforce.com',
            'api_version' => 'v58.0',
        ],
    ],

    'sync' => [
        'direction' => 'bidirectional', // bidirectional|crm_to_local|local_to_crm
        'interval_minutes' => 15,
        'batch_size' => 50,
        'conflict_resolution' => 'crm_wins', // crm_wins|local_wins|manual
        'fields_mapping' => [
            'customer' => [
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
                'company' => 'company',
                'industry' => 'industry',
            ],
            'license' => [
                'license_key' => 'license_key',
                'status' => 'status',
                'expires_at' => 'expires_at',
                'product_name' => 'product_name',
            ],
        ],
    ],

    'webhook' => [
        'enabled' => true,
        'secret' => env('CRM_WEBHOOK_SECRET', ''),
        'events' => ['customer.created', 'customer.updated', 'license.created', 'license.updated'],
    ],

    'logging' => [
        'enabled' => true,
        'retention_days' => 90,
    ],
];
