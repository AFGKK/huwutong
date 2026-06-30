<?php

// M2-105 工单/支持系统配置

return [
    'ticket' => [
        'priorities' => ['low', 'medium', 'high', 'urgent'],
        'default_priority' => 'medium',
        'statuses' => ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'],
        'max_attachments' => 5,
        'max_attachment_size_mb' => 10,
    ],

    'sla' => [
        'enabled' => env('TICKET_SLA_ENABLED', true),
        'urgent' => ['respond_minutes' => 30, 'resolve_hours' => 4],
        'high' => ['respond_minutes' => 60, 'resolve_hours' => 8],
        'medium' => ['respond_hours' => 4, 'resolve_hours' => 24],
        'low' => ['respond_hours' => 8, 'resolve_hours' => 72],
        'escalate_after_minutes' => 15,
        'breach_notification' => true,
    ],

    'assignment' => [
        'mode' => env('TICKET_ASSIGN_MODE', 'round_robin'), // round_robin|least_busy|manual
        'auto_assign_on_create' => true,
        'reassign_limit' => 3,
    ],

    'categories' => [
        'defaults' => [
            ['name' => '激活问题', 'description' => 'License 激活/验证相关问题', 'sla_priority' => 'high'],
            ['name' => '续费问题', 'description' => '订阅续费/支付相关问题', 'sla_priority' => 'medium'],
            ['name' => '设备问题', 'description' => '设备绑定/指纹/数量相关问题', 'sla_priority' => 'medium'],
            ['name' => '技术咨询', 'description' => 'API/SDK 集成技术支持', 'sla_priority' => 'low'],
            ['name' => '账户问题', 'description' => '登录/权限/账户管理', 'sla_priority' => 'high'],
            ['name' => '投诉建议', 'description' => '意见建议/投诉', 'sla_priority' => 'medium'],
        ],
    ],
];
