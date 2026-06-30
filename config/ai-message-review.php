<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI 发送前消息预审配置
    |--------------------------------------------------------------------------
    */

    // 是否启用 AI 预审
    'enabled' => env('AI_MESSAGE_REVIEW_ENABLED', true),

    // 审查级别: strict / moderate / light
    'level' => env('AI_MESSAGE_REVIEW_LEVEL', 'moderate'),

    // 触发预审的消息类型
    'message_types' => ['text'],

    // 最低消息长度（字符数，低于此长度直接跳过 LLM 审查）
    'min_length_for_llm' => env('AI_MESSAGE_REVIEW_MIN_LENGTH', 10),

    // LLM 审查配置
    'llm' => [
        'model' => env('AI_MESSAGE_REVIEW_MODEL', 'deepseek-chat'),
        'temperature' => (float) env('AI_MESSAGE_REVIEW_TEMPERATURE', 0.1),
        'max_tokens' => (int) env('AI_MESSAGE_REVIEW_MAX_TOKENS', 500),
    ],

    // 风险等级阈值
    'levels' => [
        // high: 直接拦截，必须修改后才能发送
        'high' => [
            'label' => '高风险',
            'action' => 'block',
            'color' => '#f56c6c',
        ],
        // medium: 警告提醒，用户可强制发送
        'medium' => [
            'label' => '中风险',
            'action' => 'warn',
            'color' => '#e6a23c',
        ],
        // low: 仅记录，不阻断
        'low' => [
            'label' => '低风险',
            'action' => 'log',
            'color' => '#909399',
        ],
    ],

    // 敏感词检查配置（第一道防线）
    'sensitive_word_check' => [
        'enabled' => true,
        // 命中敏感词时的默认风险等级
        'default_level' => 'high',
        // 命中敏感词时建议的替换提示
        'replacement_hint' => '消息包含敏感词「{word}」，请修改后重试',
    ],

    // 审查类别
    'categories' => [
        'tone' => '语气检查',
        'leakage' => '信息泄露',
        'harassment' => '骚扰/攻击',
        'spam' => '垃圾信息',
        'sensitive' => '敏感内容',
    ],

    // Prompt 模板（如 PromptTemplate 表中不存在则使用此处默认值）
    'prompts' => [
        'review_system' => <<<'PROMPT'
你是一个企业级消息预审助手。请审核以下消息内容，判断是否存在风险。

审查维度：
1. **语气问题**：是否包含攻击性、侮辱性、不礼貌或过激言辞
2. **信息泄露**：是否包含密码、API Key、密钥、内部敏感信息、个人隐私
3. **骚扰/攻击**：是否包含人身攻击、歧视性言论、威胁
4. **垃圾信息**：是否为广告、垃圾推广、无关内容

回复格式（严格 JSON）：
{
  "pass": true/false,
  "level": "high"/"medium"/"low",
  "categories": ["tone", "leakage", ...],
  "warnings": ["具体问题描述1", "具体问题描述2"],
  "suggestion": "修改建议"
}

规则：
- 问候语、正常业务讨论 → pass
- 轻微语气不佳 → medium + 建议
- 含敏感信息/严重攻击 → high + 拦截
- 无法判断 → pass (宁放过勿误拦)
- 仅对明确违规内容标记风险
PROMPT,

        'review_user' => <<<'PROMPT'
请审核以下消息内容：
{message}

注意上下文：发送者是一名企业用户，接收方可能是同事或客户。
PROMPT,
    ],
];
