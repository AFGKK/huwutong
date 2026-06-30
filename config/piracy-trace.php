<?php

// M3-34 AI 盗版溯源配置

return [
    'scan' => [
        'sources' => [
            'github' => [
                'enabled' => env('PIRACY_GITHUB_ENABLED', true),
                'search_queries' => [
                    'huwutong license key',
                    'hwt- license',
                    'license key huwutong',
                    'hwt_license',
                ],
                'max_results_per_query' => 100,
                'scan_interval_minutes' => 1440,
                'token' => env('GITHUB_TOKEN', ''),
            ],
            'pastebin' => [
                'enabled' => env('PIRACY_PASTEBIN_ENABLED', false),
                'scan_interval_minutes' => 1440,
            ],
            'darkweb' => [
                'enabled' => env('PIRACY_DARKWEB_ENABLED', false),
                'scan_interval_minutes' => 2880,
            ],
            'telegram' => [
                'enabled' => env('PIRACY_TELEGRAM_ENABLED', false),
                'scan_interval_minutes' => 1440,
            ],
        ],
        'max_urls_per_scan' => 500,
        'cache_discovered_urls_days' => 30,
    ],

    'detection' => [
        'confidence_thresholds' => [
            'confirmed' => 90,
            'high' => 70,
            'medium' => 40,
            'low' => 10,
        ],
        'pattern_matching' => [
            'enabled' => true,
            'regex_patterns' => [
                '/[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}/',
                '/HWT-(?:ENT|PRO|STD|TRIAL|DEV)-[A-Z0-9]+/',
                '/hwt_lic_key["\']?\s*[:=]\s*["\'][^"\']+["\']/',
            ],
        ],
        'ai_classification' => [
            'enabled' => env('PIRACY_AI_ENABLED', false),
            'model' => env('PIRACY_AI_MODEL', 'gpt-4'),
            'min_confidence' => 0.6,
        ],
    ],

    'auto_remediation' => [
        'auto_revoke_confirmed' => true,
        'auto_notify_customer' => true,
        'auto_generate_forensic_report' => true,
        'notify_channels' => ['database', 'mail'],
    ],

    'forensic_report' => [
        'include_evidence_screenshots' => true,
        'include_source_code_snippets' => true,
        'include_timeline' => true,
        'max_evidence_items' => 50,
        'report_formats' => ['pdf', 'html', 'json'],
    ],

    'retention' => [
        'scan_log_days' => 90,
        'evidence_days' => 365,
        'resolved_incident_days' => 180,
    ],
];
