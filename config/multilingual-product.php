<?php

// M3-88 多语言商品详情页配置

return [
    'languages' => [
        'supported' => ['zh-CN', 'en', 'ja', 'ko', 'de', 'fr', 'es'],
        'default' => 'zh-CN',
        'fallback' => 'en',
    ],

    'fields' => [
        'product' => ['name', 'description', 'short_description', 'seo_title', 'seo_description', 'features'],
        'category' => ['name', 'description'],
    ],

    'seo' => [
        'auto_generate_meta' => true,
        'include_hreflang' => true,
        'max_title_length' => 60,
        'max_description_length' => 160,
    ],

    'translation' => [
        'provider' => env('TRANSLATION_PROVIDER', 'manual'),
        'auto_translate_on_create' => false,
    ],
];
