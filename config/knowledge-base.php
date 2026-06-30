<?php

// M2-107 帮助中心/知识库 CMS 配置

return [
    'articles' => [
        'max_title_length' => 200,
        'max_content_length' => 100000,
        'allow_html' => env('KB_ALLOW_HTML', true),
        'version_history' => true,
        'max_versions' => 20,
        'auto_save_interval_seconds' => 30,
    ],

    'categories' => [
        'max_depth' => 2,
        'max_per_level' => 50,
    ],

    'search' => [
        'enabled' => true,
        'driver' => env('KB_SEARCH_DRIVER', 'database'), // database|meilisearch|algolia
        'min_query_length' => 2,
        'max_results' => 20,
        'highlight_results' => true,
    ],

    'i18n' => [
        'enabled' => true,
        'locales' => ['zh_CN', 'en'],
        'default_locale' => 'zh_CN',
    ],

    'feedback' => [
        'enabled' => true,
        'helpful_threshold' => 0.7,
    ],

    'cache_ttl_seconds' => 3600,
];
