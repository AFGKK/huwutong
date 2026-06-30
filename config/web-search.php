<?php

return [
    /*
    | 联网搜索配置
    | 支持以下 Provider:
    | - tavily: Tavily Search API (推荐，专为 AI 设计)
    | - serpapi: SerpAPI (Google 搜索结果)
    | - google_cse: Google Custom Search JSON API
    | - searxng: 自建 SearXNG 实例
    | - none: 禁用联网搜索
    */
    'default' => env('WEB_SEARCH_PROVIDER', 'none'),

    'providers' => [
        'tavily' => [
            'api_key' => env('TAVILY_API_KEY', ''),
            'api_url' => 'https://api.tavily.com/search',
        ],

        'serpapi' => [
            'api_key' => env('SERPAPI_API_KEY', ''),
            'api_url' => 'https://serpapi.com/search',
        ],

        'google_cse' => [
            'api_key' => env('GOOGLE_API_KEY', ''),
            'cx' => env('GOOGLE_CSE_CX', ''),
            'api_url' => 'https://www.googleapis.com/customsearch/v1',
        ],

        'searxng' => [
            'instance_url' => env('SEARXNG_INSTANCE_URL', ''),
            'api_url' => '/search',
        ],
    ],

    // 每次搜索最多返回结果数
    'max_results' => env('WEB_SEARCH_MAX_RESULTS', 5),

    // 请求超时（秒）
    'timeout' => env('WEB_SEARCH_TIMEOUT', 15),

    // 缓存搜索结果时间（分钟，0=不缓存）
    'cache_ttl' => env('WEB_SEARCH_CACHE_TTL', 10),
];
