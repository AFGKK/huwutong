<?php

// M2-156 🛒 商品搜索/筛选/排序配置

return [

    /*
    |--------------------------------------------------------------------------
    | 搜索引擎
    |--------------------------------------------------------------------------
    |
    | 支持两种模式: database (MySQL LIKE) / meilisearch (Meilisearch 全文搜索)
    |
    */
    'engine' => env('PRODUCT_SEARCH_ENGINE', 'database'),

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'api_key' => env('MEILISEARCH_API_KEY', ''),
        'index' => env('MEILISEARCH_PRODUCT_INDEX', 'products'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 搜索配置
    |--------------------------------------------------------------------------
    */
    'search' => [
        // 默认每页条数
        'per_page' => 20,
        // 最大每页条数
        'max_per_page' => 100,
        // 搜索结果高亮字段
        'highlight_fields' => ['name', 'description'],
        // 高亮标签
        'highlight_tag' => 'em',
    ],

    /*
    |--------------------------------------------------------------------------
    | 排序选项
    |--------------------------------------------------------------------------
    */
    'sort_options' => [
        'sales_desc' => ['label' => '销量排序', 'field' => 'sales_count', 'direction' => 'desc'],
        'price_asc' => ['label' => '价格从低到高', 'field' => 'base_price', 'direction' => 'asc'],
        'price_desc' => ['label' => '价格从高到低', 'field' => 'base_price', 'direction' => 'desc'],
        'newest' => ['label' => '最新上架', 'field' => 'created_at', 'direction' => 'desc'],
        'rating' => ['label' => '评分', 'field' => 'rating', 'direction' => 'desc'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 筛选器
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'categories' => ['enabled' => true, 'label' => '分类'],
        'price_range' => ['enabled' => true, 'label' => '价格区间'],
        'tags' => ['enabled' => true, 'label' => '标签'],
        'billing_period' => ['enabled' => true, 'label' => '计费周期'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 搜索日志
    |--------------------------------------------------------------------------
    */
    'logging' => [
        // 是否记录搜索日志（用于热门搜索词和搜索历史）
        'enabled' => true,
        // 搜索日志保留天数
        'retention_days' => 90,
        // 热门搜索词缓存时间（秒）
        'hot_terms_cache_ttl' => 3600,
        // 热门搜索词数量
        'hot_terms_limit' => 20,
    ],

];
