<?php

// M2-103 在线客服 Live Chat 配置

return [
    'ai' => [
        'enabled' => true,
        'provider' => env('LIVE_CHAT_AI_PROVIDER', 'deepseek'),
        'context_window' => 10,
        'confidence_threshold' => 0.7,
        'max_response_length' => 500,
    ],

    'handoff' => [
        'auto_handoff_after_messages' => 5,
        'auto_handoff_after_seconds' => 120,
        'max_queue_length' => 20,
        'agent_timeout_seconds' => 300,
    ],

    'widget' => [
        'position' => 'right', // right|left
        'primary_color' => env('LIVE_CHAT_COLOR', '#409EFF'),
        'title' => env('LIVE_CHAT_TITLE', '在线客服'),
        'subtitle' => env('LIVE_CHAT_SUBTITLE', '我们通常会在几分钟内回复'),
        'placeholder' => '请输入您的问题...',
        'show_in_portal' => true,
        'show_on_landing' => env('LIVE_CHAT_LANDING', false),
    ],

    'messages' => [
        'greeting' => env('LIVE_CHAT_GREETING', '您好！👋 欢迎来到互物通客服中心，请问有什么可以帮助您的？'),
        'offline' => env('LIVE_CHAT_OFFLINE', '当前非工作时间，请留言，我们会在上班后第一时间回复您。'),
        'handoff' => '正在为您转接人工客服，请稍候...',
        'ratings' => '请对本次服务进行评价（1-5星）',
    ],

    'retention_days' => env('LIVE_CHAT_RETENTION', 90),
];
