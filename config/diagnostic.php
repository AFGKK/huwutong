<?php

// M2-40 AI 错误诊断助手配置

return [

    /*
    |--------------------------------------------------------------------------
    | 诊断引擎开关
    |--------------------------------------------------------------------------
    */
    'enabled' => env('DIAGNOSTIC_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | LLM 增强配置
    |--------------------------------------------------------------------------
    |
    | 当启用 LLM 增强时，诊断结果会通过大语言模型生成更详细的
    | 自然语言解释和建议。需配置 LLM provider。
    |
    */
    'llm_enhancement' => [
        'enabled' => env('DIAGNOSTIC_LLM_ENABLED', false),
        // 使用的 LLM provider (deepseek / openai / claude / tongyi)
        'provider' => env('DIAGNOSTIC_LLM_PROVIDER', 'deepseek'),
        'model' => env('DIAGNOSTIC_LLM_MODEL', 'deepseek-chat'),
        // 系统提示词模板
        'system_prompt' => '你是一个软件授权系统的错误诊断专家。请基于给定的错误上下文，提供简洁、准确的原因分析和可操作的解决方案。',
        // 最大 Token 数
        'max_tokens' => 500,
        // 温度参数
        'temperature' => 0.3,
    ],

    /*
    |--------------------------------------------------------------------------
    | 诊断场景
    |--------------------------------------------------------------------------
    |
    | 支持的诊断类型和对应的严重级别阈值。
    |
    */
    'scenarios' => [
        'activation' => [
            'enabled' => true,
            'label' => '激活失败诊断',
        ],
        'validation' => [
            'enabled' => true,
            'label' => '验证失败诊断',
        ],
        'device' => [
            'enabled' => true,
            'label' => '设备绑定诊断',
        ],
        'sdk' => [
            'enabled' => true,
            'label' => 'SDK 集成建议',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 严重级别颜色和图标
    |--------------------------------------------------------------------------
    */
    'severity' => [
        'critical' => ['color' => '#f56c6c', 'icon' => 'CircleCloseFilled', 'label' => '严重'],
        'high' => ['color' => '#e6a23c', 'icon' => 'WarningFilled', 'label' => '高'],
        'medium' => ['color' => '#409eff', 'icon' => 'InfoFilled', 'label' => '中'],
        'low' => ['color' => '#909399', 'icon' => 'CircleCheck', 'label' => '低'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 诊断缓存
    |--------------------------------------------------------------------------
    |
    | 相同错误的诊断结果缓存，避免重复 LLM 调用。
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl_seconds' => 3600, // 1小时
        // 用于缓存的错误上下文键
        'key_fields' => ['error_code', 'product_id', 'device_fingerprint_prefix'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('DIAGNOSTIC_LOG_CHANNEL', 'stack'),
        'level' => env('DIAGNOSTIC_LOG_LEVEL', 'info'),
        'record_diagnostics' => env('DIAGNOSTIC_RECORD_HISTORY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SDK 建议配置
    |--------------------------------------------------------------------------
    */
    'sdk_suggestions' => [
        // 不同 SDK 语言的示例代码语言
        'languages' => ['php', 'javascript', 'python', 'go', 'java', 'csharp'],
        // 建议中使用的文档链接模板
        'doc_base_url' => env('DIAGNOSTIC_DOC_URL', 'https://docs.huwutong.com'),
    ],
];
