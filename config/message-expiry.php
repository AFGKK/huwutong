<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 消息定时销毁配置
    |--------------------------------------------------------------------------
    */

    // 全局开关
    'enabled' => env('MESSAGE_EXPIRY_ENABLED', true),

    // 默认过期策略
    'default_ttl' => [
        // 普通文本消息默认保留天数（null = 永不过期）
        'text' => env('MESSAGE_EXPIRY_TEXT_DAYS', 180),
        // 图片消息保留天数
        'image' => env('MESSAGE_EXPIRY_IMAGE_DAYS', null),
        // 文件消息保留天数
        'file' => env('MESSAGE_EXPIRY_FILE_DAYS', null),
        // 语音消息保留天数
        'voice' => env('MESSAGE_EXPIRY_VOICE_DAYS', 30),
    ],

    // 清理任务配置
    'cleanup' => [
        // 每次清理的最大条数
        'batch_size' => env('MESSAGE_EXPIRY_BATCH', 500),
        // 是否物理删除（false = 软删除，只设 deleted_at）
        'force_delete' => env('MESSAGE_EXPIRY_FORCE_DELETE', false),
    ],

    // 客户端支持的过期单位
    'units' => [
        'minutes' => '分钟',
        'hours' => '小时',
        'days' => '天',
        'weeks' => '周',
        'months' => '月',
    ],
];
