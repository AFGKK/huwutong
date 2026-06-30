<?php

// M2-157 🛒 订单售后工单配置

return [
    'reasons' => [
        'not_received' => ['label' => '未收到货', 'priority' => 'high'],
        'wrong_item' => ['label' => '发错货', 'priority' => 'high'],
        'quality_issue' => ['label' => '质量问题', 'priority' => 'medium'],
        'activation_failed' => ['label' => '激活失败', 'priority' => 'urgent'],
        'license_mismatch' => ['label' => 'License 不符', 'priority' => 'high'],
        'delivery_delay' => ['label' => '发货延迟', 'priority' => 'medium'],
        'refund_request' => ['label' => '申请退款', 'priority' => 'medium'],
        'other' => ['label' => '其他', 'priority' => 'low'],
    ],

    'auto_create_ticket' => true,
    'require_order_for_ticket' => true,
    'max_tickets_per_order' => 3,
];
