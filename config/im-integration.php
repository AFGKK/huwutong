<?php

// M2-57 IM 通知集成 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 支持的 IM 渠道
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'slack' => [
            'name' => 'Slack',
            'enabled' => env('IM_SLACK_ENABLED', false),
        ],
        'dingtalk' => [
            'name' => '钉钉',
            'enabled' => env('IM_DINGTALK_ENABLED', false),
        ],
        'wecom' => [
            'name' => '企业微信',
            'enabled' => env('IM_WECOM_ENABLED', false),
        ],
        'feishu' => [
            'name' => '飞书',
            'enabled' => env('IM_FEISHU_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 通知级别颜色映射
    |--------------------------------------------------------------------------
    */
    'severity_colors' => [
        'critical' => '#dc3545',
        'high' => '#fd7e14',
        'medium' => '#ffc107',
        'low' => '#28a745',
        'info' => '#409eff',
    ],

    /*
    |--------------------------------------------------------------------------
    | 发送超时（秒）
    |--------------------------------------------------------------------------
    */
    'timeout' => 10,

    /*
    |--------------------------------------------------------------------------
    | 自动发送事件配置（哪些事件自动推送到哪些 IM 渠道）
    |--------------------------------------------------------------------------
    | event_key => ['slack', 'dingtalk', 'wecom', 'feishu']
    */
    'auto_send' => [
        'license.activated' => ['slack', 'dingtalk'],
        'license.expired' => ['slack', 'dingtalk', 'wecom'],
        'license.expiring_soon' => ['slack', 'dingtalk', 'wecom'],
        'license.revoked' => ['slack', 'dingtalk'],
        'payment.success' => ['slack'],
        'payment.failed' => ['slack', 'dingtalk', 'wecom'],
        'alert.critical' => ['slack', 'dingtalk', 'wecom', 'feishu'],
        'alert.high' => ['slack', 'dingtalk'],
    ],
];
