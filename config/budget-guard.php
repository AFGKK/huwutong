<?php

// M2-79 消费预警+预算上限 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 预算配置
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('BUDGET_DEFAULT_CURRENCY', 'CNY'),
    'supported_periods' => ['monthly', 'quarterly', 'yearly'],

    /*
    |--------------------------------------------------------------------------
    | 预警阈值
    |--------------------------------------------------------------------------
    */
    'alert_thresholds' => [
        'warning' => 80,    // 80% → 预警通知
        'critical' => 95,   // 95% → 紧急通知
        'hard_limit' => 100,// 100% → 自动拦截
    ],

    /*
    |--------------------------------------------------------------------------
    | 拦截行为
    |--------------------------------------------------------------------------
    */
    'hard_limit_action' => env('BUDGET_HARD_LIMIT_ACTION', 'block'), // block / warn / notify_only
    'allow_override' => env('BUDGET_ALLOW_OVERRIDE', true),          // 是否允许审批解锁
    'override_expiry_hours' => env('BUDGET_OVERRIDE_EXPIRY', 24),    // 审批解锁有效期

    /*
    |--------------------------------------------------------------------------
    | 通知配置
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'warning' => [
            'channels' => ['mail', 'in_app'],
            'subject' => '预算使用已超过 :threshold%',
            'template' => 'emails.budget.warning',
        ],
        'critical' => [
            'channels' => ['mail', 'in_app'],
            'subject' => '预算使用已超过 :threshold%，即将拦截',
            'template' => 'emails.budget.critical',
        ],
        'blocked' => [
            'channels' => ['mail', 'in_app'],
            'subject' => '预算已用完，消费已被拦截',
            'template' => 'emails.budget.blocked',
        ],
        'override_granted' => [
            'channels' => ['mail', 'in_app'],
            'subject' => '预算超额审批已通过',
            'template' => 'emails.budget.override',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 审核配置
    |--------------------------------------------------------------------------
    */
    'approval' => [
        'required_for_override' => true,
        'auto_approve_threshold' => 120, // 超出120%以内可自动审批
        'max_override_percentage' => 200, // 最大可审批超出比例
    ],
];
