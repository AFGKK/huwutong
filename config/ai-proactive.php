<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI 主动洞察推送配置
    |--------------------------------------------------------------------------
    */

    // 总开关
    'enabled' => env('AI_PROACTIVE_ENABLED', true),

    // 扫描间隔（分钟）
    'scan_interval' => env('AI_PROACTIVE_SCAN_INTERVAL', 15),

    // 未回复超时（分钟）— 超过此时间 AI 回复后用户未发言 → 触发洞察
    'unreplied_timeout' => env('AI_PROACTIVE_UNREPLIED_TIMEOUT', 30),

    // 同一会话两次洞察的最小间隔（分钟）
    'min_insight_interval' => env('AI_PROACTIVE_MIN_INTERVAL', 120),

    // 每次扫描最多生成的洞察数
    'max_insights_per_scan' => env('AI_PROACTIVE_MAX_PER_SCAN', 20),

    // 每个用户每日最大洞察数
    'max_daily_per_user' => env('AI_PROACTIVE_DAILY_PER_USER', 5),

    // LLM 配置
    'llm' => [
        'model' => env('AI_PROACTIVE_MODEL', 'deepseek-chat'),
        'temperature' => (float) env('AI_PROACTIVE_TEMPERATURE', 0.3),
        'max_tokens' => (int) env('AI_PROACTIVE_MAX_TOKENS', 600),
    ],

    // 洞察类型定义
    'types' => [
        'follow_up' => [
            'label' => '跟进建议',
            'description' => 'AI 上次回复后用户未回应，建议主动跟进',
            'icon' => 'Message',
        ],
        'reminder' => [
            'label' => '待办提醒',
            'description' => '从对话中识别的待办事项提醒',
            'icon' => 'AlarmClock',
        ],
        'suggestion' => [
            'label' => '智能建议',
            'description' => '基于对话上下文的主动建议',
            'icon' => 'Lightbulb',
        ],
        'insight' => [
            'label' => '深度洞察',
            'description' => 'AI 分析对话后发现的模式或机会',
            'icon' => 'DataAnalysis',
        ],
        'alert' => [
            'label' => '异常提醒',
            'description' => '检测到对话中的异常情况',
            'icon' => 'WarningFilled',
        ],
    ],

    // Prompt 模板
    'prompts' => [
        'generate_insight' => <<<'PROMPT'
你是一个主动洞察助手。分析以下对话片段，生成一个主动式的洞察建议。

对话上下文：
{conversation_context}

要求：
1. 判断是否需要主动跟进（用户可能等待回复、有未解决问题、或有可提供的额外帮助）
2. 如果需要，生成一条简洁的洞察消息
3. 不需要则返回 null

返回 JSON（严格格式）：
{
  "should_push": true/false,
  "type": "follow_up|reminder|suggestion|insight|alert",
  "title": "20字以内的标题",
  "content": "50字以内的洞察内容",
  "reason": "简要说明为什么需要推送"
}
PROMPT,
    ],
];
