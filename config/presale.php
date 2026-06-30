<?php

// M3-87 商品预售/众筹模式配置

return [
    'presale' => [
        'max_deposit_percent' => 50,
        'min_deposit_percent' => 10,
        'max_duration_days' => 60,
        'auto_cancel_unfunded' => true,
        'auto_refund_on_fail' => true,
    ],

    'crowdfunding' => [
        'goal_types' => ['amount', 'quantity'],
        'allow_overfunding' => true,
        'max_overfunding_percent' => 200,
        'auto_settle_on_success' => true,
        'settle_after_days' => 3,
    ],

    'payment' => [
        'deposit_methods' => ['balance', 'alipay', 'wechat'],
        'final_payment_reminder_days' => [7, 3, 1],
        'final_payment_grace_days' => 7,
    ],
];
