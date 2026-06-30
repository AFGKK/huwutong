<?php

return [
    /*
    |--------------------------------------------------------------------------
    | On-Call 值班轮换配置
    |--------------------------------------------------------------------------
    */

    'enabled' => env('ON_CALL_ENABLED', true),

    // 告警路由：检测到告警时查找当前值班人
    'alert_routing' => [
        'enabled' => env('ON_CALL_ALERT_ROUTING', true),
        // 未找到值班人时的默认通知用户ID（系统管理员）
        'fallback_user_id' => env('ON_CALL_FALLBACK_USER_ID', 1),
        // 路由延迟（告警触发后延迟N秒再路由，等待去重）
        'route_delay_seconds' => 5,
    ],

    // 值班状态检查间隔（分钟）
    'status_check_interval' => 5,

    // 自动生成未来N天的值班安排
    'auto_schedule_days' => 90,

    // 值班角色
    'roles' => [
        'primary' => '一线值班',
        'backup' => '二线备份',
        'escalation' => '三线升级',
    ],

    // 轮换类型
    'rotation_types' => [
        'daily' => ['label' => '每日轮换', 'days' => 1],
        'weekly' => ['label' => '每周轮换', 'days' => 7],
        'biweekly' => ['label' => '双周轮换', 'days' => 14],
        'monthly' => ['label' => '每月轮换', 'days' => 30],
        'custom' => ['label' => '自定义', 'days' => null],
    ],
];
