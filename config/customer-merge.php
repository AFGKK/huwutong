<?php

/**
 * 客户合并配置 (M3-66)
 */
return [
    /*
    |--------------------------------------------------------------------------
    | 客户状态筛选（仅可合并这些状态的源客户）
    |--------------------------------------------------------------------------
    */
    'mergeable_source_statuses' => ['active', 'inactive'],

    /*
    |--------------------------------------------------------------------------
    | 冲突解决策略
    |--------------------------------------------------------------------------
    */
    'conflict_resolution' => [
        'prepaid_balance' => 'accumulate',  // accumulate | source | target | skip
        'credit_limit' => 'accumulate',     // accumulate | source | target | skip
        'custom_fields' => 'target_priority', // target_priority | source_priority | merge
    ],

    /*
    |--------------------------------------------------------------------------
    | 关联数据迁移配置
    |--------------------------------------------------------------------------
    */
    'migration' => [
        'move_licenses' => true,
        'move_subscriptions' => true,
        'move_invoices' => true,
        'move_prepaid_transactions' => true,
        'move_custom_fields' => true,
        'move_tags' => true,
        'move_credit_history' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 合并后操作
    |--------------------------------------------------------------------------
    */
    'after_merge' => [
        'mark_source_as_merged' => true,       // 标记源客户为 merged 状态
        'update_merge_count' => true,           // 累加目标客户的 merge_count
        'create_audit_log' => true,             // 创建审计日志
    ],
];
