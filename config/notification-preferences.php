<?php

/**
 * 通知偏好管理配置 (M3-29)
 */
return [
    /*
    |--------------------------------------------------------------------------
    | 支持的通知渠道
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'mail' => '邮件',
        'sms' => '短信',
        'database' => '站内信',
    ],

    /*
    |--------------------------------------------------------------------------
    | 通知分类
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'license_expiry' => [
            'label' => 'License 到期提醒',
            'default' => ['mail', 'database'],
            'description' => 'License 到期前 7/3/1 天提醒',
        ],
        'invoice' => [
            'label' => '发票/账单通知',
            'default' => ['mail'],
            'description' => '账单生成、发票开具通知',
        ],
        'payment' => [
            'label' => '支付通知',
            'default' => ['mail', 'database'],
            'description' => '支付成功/失败/退款通知',
        ],
        'security' => [
            'label' => '安全提醒',
            'default' => ['mail', 'sms', 'database'],
            'description' => '异常登录、密码修改、MFA变更',
        ],
        'system' => [
            'label' => '系统公告',
            'default' => ['database'],
            'description' => '系统维护、版本更新、服务变更',
        ],
        'im_message' => [
            'label' => '私信消息',
            'default' => ['database'],
            'description' => '一对一私信与消息请求提醒',
        ],
        'interaction' => [
            'label' => '互动通知',
            'default' => ['database'],
            'description' => '点赞、评论、@提及、新增关注',
        ],
        'promotion' => [
            'label' => '营销推广',
            'default' => ['mail'],
            'description' => '促销活动、优惠信息、产品更新',
        ],
        'commission' => [
            'label' => '佣金通知',
            'default' => ['mail', 'database'],
            'description' => '佣金入账、解冻、提现状态变更',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 默认设置
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'channels' => ['mail', 'database'],
        'quiet_hours_start' => null,   // 免打扰开始 如 '22:00'
        'quiet_hours_end' => null,     // 免打扰结束 如 '08:00'
        'digest_frequency' => 'none',  // none | daily | weekly
    ],
];
