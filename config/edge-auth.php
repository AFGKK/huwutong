<?php

// M3-17 边缘计算授权 + AI Token 配额授权

return [
    'edge' => [
        'node_types' => ['cloudflare', 'akamai', 'fastly', 'custom'],
        'validation_ttl_seconds' => 3600,
        'max_nodes_per_tenant' => 20,
        'offline_grace_period_minutes' => 10,
        'geo_restrictions' => [
            'enabled' => true,
            'default_action' => 'allow', // allow|deny|challenge
        ],
    ],

    'ai_tokens' => [
        'models' => [
            'gpt-4' => ['cost_per_token' => 0.00003, 'token_type' => 'completion'],
            'gpt-3.5-turbo' => ['cost_per_token' => 0.000002, 'token_type' => 'completion'],
            'claude-3' => ['cost_per_token' => 0.000015, 'token_type' => 'completion'],
            'custom' => ['cost_per_token' => 0.00001, 'token_type' => 'completion'],
        ],
        'default_monthly_tokens' => 1000000,
        'overage_action' => 'throttle', // throttle|block|bill
        'token_window_days' => 30,
        'reporting' => [
            'granularity' => 'hour',
            'retention_days' => 90,
        ],
    ],
];
