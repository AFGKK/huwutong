<?php

// M3-44 客户反馈收集组件配置

return [
    'widget' => [
        'types' => ['bug', 'feature_request', 'general', 'improvement'],
        'allow_screenshot' => true,
        'allow_annotation' => true,
        'auto_attach_context' => true,
        'max_screenshots_per_feedback' => 3,
        'max_file_size_kb' => 5120,
    ],

    'feedback' => [
        'require_user_login' => true,
        'auto_assign_category' => true,
        'default_status' => 'open',
        'priorities' => ['low', 'medium', 'high', 'critical'],
    ],

    'context' => [
        'include' => [
            'url' => true,
            'user_agent' => true,
            'browser' => true,
            'os' => true,
            'screen_resolution' => true,
            'app_version' => true,
            'license_key' => false,
            'user_role' => true,
        ],
    ],

    'notifications' => [
        'on_new_feedback' => true,
        'on_status_change' => true,
        'channels' => ['database', 'mail'],
    ],
];
