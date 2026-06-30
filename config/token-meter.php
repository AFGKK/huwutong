<?php

// M2-77 AI Token 用量计费追踪

return [
    'models' => [
        'deepseek-chat' => ['cost_per_1k_input' => 0.0005, 'cost_per_1k_output' => 0.002, 'provider' => 'deepseek'],
        'deepseek-reasoner' => ['cost_per_1k_input' => 0.0008, 'cost_per_1k_output' => 0.004, 'provider' => 'deepseek'],
        'gpt-4o' => ['cost_per_1k_input' => 0.005, 'cost_per_1k_output' => 0.015, 'provider' => 'openai'],
        'gpt-4o-mini' => ['cost_per_1k_input' => 0.00015, 'cost_per_1k_output' => 0.0006, 'provider' => 'openai'],
        'claude-3-opus' => ['cost_per_1k_input' => 0.015, 'cost_per_1k_output' => 0.075, 'provider' => 'anthropic'],
        'claude-3-sonnet' => ['cost_per_1k_input' => 0.003, 'cost_per_1k_output' => 0.015, 'provider' => 'anthropic'],
        'qwen-max' => ['cost_per_1k_input' => 0.0004, 'cost_per_1k_output' => 0.0012, 'provider' => 'alibaba'],
        'qwen-plus' => ['cost_per_1k_input' => 0.0002, 'cost_per_1k_output' => 0.0006, 'provider' => 'alibaba'],
        'ernie-4.0' => ['cost_per_1k_input' => 0.0003, 'cost_per_1k_output' => 0.001, 'provider' => 'baidu'],
        'glm-4' => ['cost_per_1k_input' => 0.0001, 'cost_per_1k_output' => 0.0003, 'provider' => 'zhipu'],
    ],

    'features' => [
        'chat' => 'AI 客服对话',
        'diagnostic' => '错误诊断',
        'analyst' => '运营分析',
        'pricing' => '定价建议',
        'sdk_config' => 'SDK 配置生成',
        'test_gen' => '测试用例生成',
        'compliance' => '合规报告',
        'piracy' => '盗版溯源',
        'migration' => '迁移助手',
        'clustering' => '客户聚类',
        'cross_sell' => '交叉销售',
        'churn' => '流失预警',
        'revenue' => '收入预测',
        'adaptive' => '自适应安全',
    ],

    'budget' => [
        'monthly_hard_cap' => env('TOKEN_MONTHLY_HARD_CAP', 1000), // USD
        'tenant_monthly_cap' => env('TOKEN_TENANT_MONTHLY_CAP', 100),
        'alert_thresholds' => [50, 80, 90, 100], // 百分比
    ],

    'retention_days' => env('TOKEN_RETENTION_DAYS', 180),
];
