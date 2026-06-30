<?php

// M2-140 ISO 42001 AI 管理系统合规 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 启用状态
    |--------------------------------------------------------------------------
    */
    'enabled' => env('AI_COMPLIANCE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | AI 系统清单
    |--------------------------------------------------------------------------
    */
    'system_registry' => [
        'required_fields' => ['name', 'version', 'purpose', 'deployment_status', 'risk_level'],
        'risk_levels' => ['low' => '低风险', 'medium' => '中风险', 'high' => '高风险', 'critical' => '极高风险'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 风险影响评估
    |--------------------------------------------------------------------------
    */
    'risk_assessment' => [
        'review_interval_days' => (int) env('AI_RISK_REVIEW_INTERVAL', 180),
        'impact_categories' => [
            'fairness' => '公平性',
            'privacy' => '隐私',
            'safety' => '安全',
            'transparency' => '透明度',
            'accountability' => '问责制',
            'non_discrimination' => '非歧视',
        ],
        'severity_levels' => ['negligible', 'minor', 'moderate', 'major', 'critical'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 偏见检测
    |--------------------------------------------------------------------------
    */
    'bias_detection' => [
        'metrics' => [
            'demographic_parity' => '人口统计平等',
            'equal_opportunity' => '均等机会',
            'predictive_parity' => '预测平等',
            'disparate_impact' => '差异化影响',
        ],
        'threshold_warning' => 0.1,
        'threshold_critical' => 0.2,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI 决策审计日志
    |--------------------------------------------------------------------------
    */
    'decision_log' => [
        'retention_days' => (int) env('AI_DECISION_LOG_RETENTION', 365),
        'log_all_decisions' => env('AI_LOG_ALL_DECISIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 透明度披露
    |--------------------------------------------------------------------------
    */
    'transparency' => [
        'require_disclosure' => env('AI_REQUIRE_DISCLOSURE', true),
        'default_disclosure_text' => '此决策由 AI 系统辅助生成，如有疑问可申请人工复核。',
    ],

    /*
    |--------------------------------------------------------------------------
    | 人工申诉
    |--------------------------------------------------------------------------
    */
    'override' => [
        'max_processing_hours' => (int) env('AI_OVERRIDE_MAX_HOURS', 48),
        'require_reason' => true,
        'escalation_levels' => ['first_line', 'supervisor', 'compliance_officer'],
    ],
];
