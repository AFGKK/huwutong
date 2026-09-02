<?php

return [
    'unlimited' => 'Unlimited',
    'per_minute' => '/min',
    'check' => '✓',
    'units' => [
        'products' => '',
        'activations' => '',
        'keys' => '',
        'members' => '',
        'languages' => '',
        'replies' => '',
        'groups' => '',
    ],
    'values' => [
        'webhook_retry' => [
            'retry_filter' => 'Retry + filter',
            'full' => 'Full',
        ],
        'sla' => [
            'negotiable' => 'Negotiable',
            'written' => 'Written SLA',
        ],
        'data_export' => [
            'csv' => 'CSV',
            'csv_json' => 'CSV+JSON',
        ],
        'trial' => [
            '7' => '7 d',
            '14' => '14 d',
            '30' => '30 d',
            'custom' => 'Custom',
        ],
    ],
    'max_products' => [
        'label' => 'Products',
        'tip' => 'Number of software products you can manage',
    ],
    'max_activations' => [
        'label' => 'Device activations',
        'tip' => 'Monthly terminal activations (license activations)',
    ],
    'api_rate_limit' => [
        'label' => 'API rate limit',
        'tip' => 'Request rate limit; excess traffic is temporarily throttled',
    ],
    'max_api_keys' => [
        'label' => 'API keys',
        'tip' => 'API keys for SDK integration and authentication',
    ],
    'team_members' => [
        'label' => 'Team members',
        'tip' => 'Seats for collaborative team access',
    ],
    'rbac' => [
        'label' => 'RBAC',
        'tip' => 'Role-based access control with fine-grained permissions',
    ],
    'webhook' => [
        'label' => 'Webhook',
        'tip' => 'Webhook callbacks for license events',
    ],
    'webhook_retry_filter' => [
        'label' => 'Webhook retry / filter',
        'tip' => 'Retries, event filters, replay, and dead-letter monitoring',
    ],
    'customer_portal' => [
        'label' => 'Customer portal',
        'tip' => 'Self-serve portal for customers to manage licenses',
    ],
    'multi_currency' => [
        'label' => 'Multi-currency',
        'tip' => 'CNY / USD / EUR pricing and settlement',
    ],
    'custom_domain' => [
        'label' => 'Custom domain',
        'tip' => 'Host license verification on your own domain',
    ],
    'sdk_languages' => [
        'label' => 'Multi-language SDKs',
        'tip' => 'SDKs for PHP / Node / Python / Java / Go / .NET',
    ],
    'offline_licensing' => [
        'label' => 'Offline licensing',
        'tip' => 'Generate and verify licenses offline',
    ],
    'device_fingerprint' => [
        'label' => 'Device fingerprint',
        'tip' => 'Hardware-based binding to reduce license abuse',
    ],
    'floating_seats' => [
        'label' => 'Floating seat pool',
        'tip' => 'Dynamically allocate seats across team devices',
    ],
    'oem_whitelabel' => [
        'label' => 'OEM / white-label',
        'tip' => 'Present the console under your brand',
    ],
    'sso_saml' => [
        'label' => 'SSO / SAML',
        'tip' => 'SAML 2.0 / OIDC single sign-on',
    ],
    'audit_logs' => [
        'label' => 'Audit logs',
        'tip' => 'Operational audit trails for compliance reviews',
    ],
    'ai_insights' => [
        'label' => 'AI insights',
        'tip' => 'Usage analysis and anomaly signals',
    ],
    'live_chat' => [
        'label' => 'Live chat',
        'tip' => 'On-site live chat and message handling',
    ],
    'ai_support' => [
        'label' => 'AI support',
        'tip' => 'AI replies, RAG Q&A, and auto-reply rules',
    ],
    'human_handoff' => [
        'label' => 'Human handoff',
        'tip' => 'AI-to-human handoff with confidence / timeout policies',
    ],
    'canned_replies' => [
        'label' => 'Canned replies',
        'tip' => 'Preset reply templates for agents',
    ],
    'agent_groups' => [
        'label' => 'Agent groups',
        'tip' => 'Agent groups / departments for routing',
    ],
    'im_notifications' => [
        'label' => 'IM notifications',
        'tip' => 'Slack / DingTalk / WeCom / Feishu notifications',
    ],
    'sla_options' => [
        'label' => 'SLA options',
        'tip' => 'Enterprise plans may include a written availability target; otherwise the service is provided as-is',
    ],
    'data_export' => [
        'label' => 'Data export',
        'tip' => 'Export licenses, audits, and customers as CSV / JSON',
    ],
    'trial_management' => [
        'label' => 'Trial management',
        'tip' => 'Trial limits, expiry, and conversion to paid',
    ],
    'dedicated_csm' => [
        'label' => 'Dedicated CSM',
        'tip' => 'Dedicated customer success support',
    ],
    'private_deploy' => [
        'label' => 'Private deploy',
        'tip' => 'Deploy on your own infrastructure',
    ],
];
