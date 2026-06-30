<?php

// 互物通 SOC2 / ISO27001 合规准备包配置
return [
    'enabled' => env('COMPLIANCE_PACK_ENABLED', true),

    // 支持的合规框架
    'frameworks' => [
        'soc2' => [
            'name' => 'SOC 2',
            'full_name' => 'Service Organization Control 2',
            'version' => '2023',
            'description' => '适用于 SaaS 服务商的数据安全与隐私保护认证',
            'controls_count' => 16,
            'question_count' => 16,
            'policy_count' => 6,
            'icon' => 'Shield',
        ],
        'iso27001' => [
            'name' => 'ISO 27001',
            'full_name' => 'ISO/IEC 27001:2022',
            'version' => '2022',
            'description' => '国际信息安全管理体系标准',
            'controls_count' => 17,
            'question_count' => 17,
            'policy_count' => 4,
            'icon' => 'DocumentChecked',
        ],
    ],

    // 评分权重
    'scoring' => [
        'questionnaire_weight' => 0.30,   // 问卷完成度权重
        'evidence_weight' => 0.35,        // 证据收集权重
        'gap_closing_weight' => 0.35,     // 差距整改权重
        'passing_score' => 80,            // 通过线（满分100）
    ],

    // 证据收集
    'evidence' => [
        'auto_collect_enabled' => env('COMPLIANCE_EVIDENCE_AUTO_COLLECT', true),
        'max_file_size' => env('COMPLIANCE_EVIDENCE_MAX_SIZE', 50 * 1024 * 1024), // 50MB
        'allowed_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'csv', 'json', 'log', 'txt'],
        'storage_disk' => env('COMPLIANCE_EVIDENCE_DISK', 'local'),
        'storage_path' => 'compliance/evidence',
    ],

    // 策略文档生成
    'policy_documents' => [
        'storage_disk' => env('COMPLIANCE_POLICY_DISK', 'local'),
        'storage_path' => 'compliance/policies',
        'default_author' => env('COMPLIANCE_DEFAULT_AUTHOR', '互物通安全团队'),
    ],

    // 报告导出
    'report' => [
        'storage_disk' => env('COMPLIANCE_REPORT_DISK', 'local'),
        'storage_path' => 'compliance/reports',
        'formats' => ['html', 'json'],
        'logo_path' => env('COMPLIANCE_REPORT_LOGO', ''),
    ],

    // 缓存
    'cache' => [
        'template_ttl' => 86400,      // 模板缓存 24 小时
        'dashboard_ttl' => 3600,      // 仪表盘缓存 1 小时
    ],
];
