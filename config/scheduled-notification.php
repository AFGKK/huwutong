<?php

// M2-114 批量通知定时发送 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 通知渠道
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'email' => '邮件',
        'in_app' => '站内信',
        'sms' => '短信',
    ],

    /*
    |--------------------------------------------------------------------------
    | 通知类型
    |--------------------------------------------------------------------------
    */
    'types' => [
        'maintenance' => '系统维护',
        'holiday' => '节日祝福',
        'promotion' => '促销活动',
        'announcement' => '平台公告',
        'update' => '版本更新',
        'policy' => '政策变更',
        'custom' => '自定义',
    ],

    /*
    |--------------------------------------------------------------------------
    | 发送配置
    |--------------------------------------------------------------------------
    */
    'sending' => [
        // 每次最多接收人数量
        'max_recipients' => env('NOTIFICATION_MAX_RECIPIENTS', 10000),
        // 每批发送数量（用于队列分片）
        'batch_size' => env('NOTIFICATION_BATCH_SIZE', 500),
        // 发送间隔（秒，避免被判定为垃圾邮件）
        'interval_seconds' => env('NOTIFICATION_INTERVAL', 1),
        // 默认发送者名称
        'sender_name' => env('NOTIFICATION_SENDER', '互物通系统'),
        // 撤销有效期（发送后多少分钟内可撤销）
        'cancel_window_minutes' => env('NOTIFICATION_CANCEL_WINDOW', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认通知模板
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'maintenance' => [
            'subject' => '【{app_name}】系统维护通知',
            'body' => "尊敬的{user_name}，\n\n{app_name}将于{start_time}至{end_time}进行系统维护升级，期间部分服务可能不可用。\n\n维护内容：{description}\n\n感谢您的理解与支持！\n\n{app_name}团队",
        ],
        'holiday' => [
            'subject' => '【{app_name}】{holiday_name}快乐！',
            'body' => "尊敬的{user_name}，\n\n值此{holiday_name}之际，{app_name}团队祝您节日快乐，万事如意！\n\n{app_name}团队",
        ],
        'promotion' => [
            'subject' => '【{app_name}】限时优惠活动',
            'body' => "尊敬的{user_name}，\n\n{app_name}推出{promotion_name}活动！\n\n活动时间：{start_time} 至 {end_time}\n活动详情：{description}\n\n立即参与：{action_url}\n\n{app_name}团队",
        ],
    ],
];
