<?php

/**
 * 混沌工程配置 (M3-80)
 *
 * Chaos Engineering — 系统韧性测试
 * 支持 Gremlin / Chaos Mesh 故障注入
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 启用状态
    |--------------------------------------------------------------------------
    */
    'enabled' => env('CHAOS_ENGINEERING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 故障注入提供方
    |--------------------------------------------------------------------------
    | supported: chaos_mesh, gremlin, builtin
    */
    'provider' => env('CHAOS_PROVIDER', 'builtin'),

    /*
    |--------------------------------------------------------------------------
    | Chaos Mesh 配置
    |--------------------------------------------------------------------------
    */
    'chaos_mesh' => [
        'api_base' => env('CHAOS_MESH_API', 'http://chaos-dashboard:2333'),
        'namespace' => env('CHAOS_MESH_NAMESPACE', 'chaos-engineering'),
        'token' => env('CHAOS_MESH_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gremlin 配置
    |--------------------------------------------------------------------------
    */
    'gremlin' => [
        'api_key' => env('GREMLIN_API_KEY', ''),
        'team_id' => env('GREMLIN_TEAM_ID', ''),
        'api_base' => 'https://api.gremlin.com/v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | 内置故障注入
    |--------------------------------------------------------------------------
    |
    | PHP 层级的模拟故障注入（无需外部工具）
    |
    */
    'builtin' => [
        'enabled' => env('CHAOS_BUILTIN_ENABLED', true),
        'allowed_types' => [
            'redis_outage',
            'db_failover',
            'network_latency',
            'disk_full',
            'cpu_stress',
            'memory_stress',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 安全限制
    |--------------------------------------------------------------------------
    */
    'safety' => [
        'max_duration_seconds' => env('CHAOS_MAX_DURATION', 300), // 5 分钟
        'require_approval' => env('CHAOS_REQUIRE_APPROVAL', true),
        'allowed_environments' => ['staging', 'testing'],
        'auto_rollback_on_timeout' => true,
        'notify_on_start' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | 韧性评分卡
    |--------------------------------------------------------------------------
    */
    'resilience_scoring' => [
        'auto_recovery_weight' => 35,
        'degradation_behavior_weight' => 30,
        'alert_triggered_weight' => 15,
        'recovery_time_weight' => 10,
        'documentation_weight' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | GameDay 演练计划
    |--------------------------------------------------------------------------
    */
    'gameday' => [
        'default_frequency_days' => 30,
        'default_experiments_count' => 3,
        'pre_gameday_checklist' => [
            '通知所有相关团队',
            '确认监控告警正常运行',
            '备份关键数据',
            '确认回滚方案',
            '指定演练指挥 (IC)',
        ],
        'post_gameday_actions' => [
            '召开复盘会议',
            '更新韧性评分卡',
            '创建改进工单',
            '更新故障演练文档',
        ],
    ],
];
