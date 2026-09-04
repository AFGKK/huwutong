<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meilisearch 配置
    |--------------------------------------------------------------------------
    */
    'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
    'api_key' => env('MEILISEARCH_API_KEY', ''),
    'master_key' => env('MEILISEARCH_MASTER_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | 索引配置
    |--------------------------------------------------------------------------
    */
    'indexes' => [
        'products' => [
            'name' => env('MEILISEARCH_PRODUCT_INDEX', 'products'),
            'primary_key' => 'id',
            'searchable_attributes' => ['name', 'slug', 'description', 'long_description', 'tags'],
            'filterable_attributes' => ['category_id', 'is_active', 'is_sellable', 'base_price', 'merchant_id'],
            'sortable_attributes' => ['base_price', 'sales_count', 'created_at', 'updated_at'],
        ],
        'kb_articles' => [
            'name' => env('MEILISEARCH_KB_INDEX', 'kb_articles'),
            'primary_key' => 'id',
            'searchable_attributes' => ['title', 'content', 'excerpt', 'tags'],
            'filterable_attributes' => ['category_id', 'status', 'locale', 'author_id'],
            'sortable_attributes' => ['view_count', 'helpful_count', 'created_at', 'published_at'],
        ],
        'marketplace_apps' => [
            'name' => env('MEILISEARCH_MARKETPLACE_INDEX', 'marketplace_apps'),
            'primary_key' => 'id',
            'searchable_attributes' => ['name', 'slug', 'short_description', 'description', 'category', 'developer_name'],
            'filterable_attributes' => ['category', 'status', 'pricing_type', 'developer_id'],
            'sortable_attributes' => ['install_count', 'avg_rating', 'price', 'created_at', 'published_at'],
        ],
        'forum_posts' => [
            'name' => env('MEILISEARCH_FORUM_INDEX', 'forum_posts'),
            'primary_key' => 'id',
            'searchable_attributes' => ['title', 'content', 'tags'],
            'filterable_attributes' => ['status', 'category_id', 'user_id'],
            'sortable_attributes' => ['views_count', 'likes_count', 'created_at'],
        ],
        'blog_posts' => [
            'name' => env('MEILISEARCH_BLOG_INDEX', 'blog_posts'),
            'primary_key' => 'id',
            'searchable_attributes' => ['title', 'content', 'excerpt', 'tags', 'author'],
            'filterable_attributes' => ['is_published', 'category_id', 'author_id'],
            'sortable_attributes' => ['created_at', 'published_at'],
        ],
        'oa_articles' => [
            'name' => env('MEILISEARCH_OA_INDEX', 'oa_articles'),
            'primary_key' => 'id',
            'searchable_attributes' => ['title', 'content', 'summary', 'tags'],
            'filterable_attributes' => ['status', 'account_id', 'author_id'],
            'sortable_attributes' => ['created_at', 'published_at'],
        ],
        'users' => [
            'name' => env('MEILISEARCH_USER_INDEX', 'users'),
            'primary_key' => 'id',
            'searchable_attributes' => ['name', 'email'],
            'filterable_attributes' => ['status'],
            'sortable_attributes' => ['created_at'],
        ],
        'official_accounts' => [
            'name' => env('MEILISEARCH_OA_ACCOUNT_INDEX', 'official_accounts'),
            'primary_key' => 'id',
            'searchable_attributes' => ['name', 'slug', 'description'],
            'filterable_attributes' => ['status', 'category_id', 'owner_id'],
            'sortable_attributes' => ['follower_count', 'article_count', 'created_at'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 同步配置
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'chunk_size' => 100,
        'queue' => env('MEILISEARCH_SYNC_QUEUE', false),
        'queue_name' => env('MEILISEARCH_SYNC_QUEUE_NAME', 'default'),
        // 定时全量补齐（依赖 schedule:run）；增量仍由 Observer 自动完成
        'scheduled' => env('MEILISEARCH_SCHEDULED_SYNC', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Observer 增量同步（D-36）
    |--------------------------------------------------------------------------
    */
    'observer' => [
        'enabled' => env('MEILISEARCH_OBSERVER_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 搜索设置
    |--------------------------------------------------------------------------
    */
    'search' => [
        'limit' => 20,
        'matches_position' => true,
        'show_ranking_score' => false,
    ],
];
