<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI 长期记忆配置
    |--------------------------------------------------------------------------
    */

    // 默认置信度阈值（低于此值的记忆不自动注入上下文）
    'min_confidence' => env('AI_MEMORY_MIN_CONFIDENCE', 0.5),

    // 每次对话注入的最大记忆条数
    'max_memories_per_context' => env('AI_MEMORY_MAX_PER_CONTEXT', 10),

    // 自动提取设置
    'extraction' => [
        'enabled' => env('AI_MEMORY_EXTRACTION_ENABLED', true),
        // 每 N 条消息后触发一次记忆提取
        'message_interval' => env('AI_MEMORY_EXTRACT_INTERVAL', 5),
        // 提取时使用的 LLM 模型
        'model' => env('AI_MEMORY_EXTRACT_MODEL', 'deepseek-chat'),
        // 新记忆的默认置信度
        'default_confidence' => env('AI_MEMORY_DEFAULT_CONFIDENCE', 0.7),
    ],

    // 记忆过期策略
    'retention' => [
        // 未确认的记忆默认保留天数
        'unconfirmed_days' => env('AI_MEMORY_UNCONFIRMED_DAYS', 30),
        // 高优先级记忆永久保留
        'high_priority_forever' => true,
    ],

    // 记忆清理
    'pruning' => [
        'enabled' => env('AI_MEMORY_PRUNING_ENABLED', true),
        // 每天清理过期和低置信度记忆
        'schedule' => 'daily',
        'min_confidence_to_keep' => 0.1,
    ],

    // 分类定义
    'categories' => [
        'user_preference' => '用户偏好',
        'user_fact' => '用户事实',
        'business_info' => '业务信息',
        'personal_info' => '个人信息',
        'technical_context' => '技术上下文',
        'project_context' => '项目上下文',
        'conversation_style' => '对话风格',
        'ai_insight' => 'AI洞察',
    ],
];
