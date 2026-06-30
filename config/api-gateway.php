<?php

/**
 * API 网关统一层配置 (M1.3-20)
 *
 * 按 M0-11 ADR 网关职责划分：
 *   [网关层 — Kong/APISIX]
 *     全局限流 / IP 黑名单 / CC 防护 / SSL 终止 / 认证卸载 / 日志采集
 *   [应用层 — Laravel 中间件]
 *     按租户/API 分级业务限流 / 熔断降级 / CORS/CSP/安全头 / 数据脱敏 / 幂等性
 *
 * 支持双网关引擎：Kong（传统 API 网关） / APISIX（云原生）
 */

return [

    /*
    |--------------------------------------------------------------------------
    | 引擎类型
    |--------------------------------------------------------------------------
    | kong:      Kong API Gateway (传统/企业)
    | apisix:    Apache APISIX (云原生)
    | none:      无网关（直连模式，开发/单机环境）
    */
    'engine' => env('API_GATEWAY_ENGINE', 'none'),

    /*
    |--------------------------------------------------------------------------
    | 管理 API 端点
    |--------------------------------------------------------------------------
    */
    'admin_api' => [
        'kong' => [
            'base_url' => env('KONG_ADMIN_URL', 'http://localhost:8001'),
            'api_key' => env('KONG_ADMIN_KEY'),
            'timeout' => env('KONG_ADMIN_TIMEOUT', 10),
        ],
        'apisix' => [
            'base_url' => env('APISIX_ADMIN_URL', 'http://localhost:9180'),
            'api_key' => env('APISIX_ADMIN_KEY'),
            'timeout' => env('APISIX_ADMIN_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 路由同步
    |--------------------------------------------------------------------------
    |
    | 自动从 Laravel 路由表同步到 API 网关。
    | 白名单模式：仅同步匹配 prefix 的路由。
    */
    'route_sync' => [
        'enabled' => env('API_GATEWAY_ROUTE_SYNC', false),
        'prefixes' => ['api/v1', 'api/v2'],
        'strip_prefix' => true,                 // 网关转发时移除 prefix
        'upstream_url' => env('API_GATEWAY_UPSTREAM', 'http://localhost:8000'),
        'upstream_host' => env('API_GATEWAY_UPSTREAM_HOST', 'localhost'),
        'healthcheck_path' => '/health/live',
        'retries' => env('API_GATEWAY_RETRIES', 3),
        'connect_timeout' => env('API_GATEWAY_CONNECT_TIMEOUT', 60000),   // ms
        'send_timeout' => env('API_GATEWAY_SEND_TIMEOUT', 60000),
        'read_timeout' => env('API_GATEWAY_READ_TIMEOUT', 60000),
    ],

    /*
    |--------------------------------------------------------------------------
    | 插件配置
    |--------------------------------------------------------------------------
    */
    'plugins' => [
        // 全局限流
        'rate_limiting' => [
            'enabled' => env('GATEWAY_RATE_LIMIT', true),
            'policy' => env('GATEWAY_RATE_LIMIT_POLICY', 'local'),  // local | redis | cluster
            'limits' => [
                'default' => [
                    'minute' => env('GATEWAY_RPM', 600),
                    'hour' => env('GATEWAY_RPH', 30000),
                    'day' => env('GATEWAY_RPD', 500000),
                ],
                'auth_routes' => [
                    'minute' => env('GATEWAY_AUTH_RPM', 20),
                    'hour' => env('GATEWAY_AUTH_RPH', 500),
                ],
                'activate_routes' => [
                    'minute' => env('GATEWAY_ACTIVATE_RPM', 100),
                    'hour' => env('GATEWAY_ACTIVATE_RPH', 3000),
                ],
            ],
        ],

        // IP 黑名单/白名单
        'ip_restriction' => [
            'enabled' => env('GATEWAY_IP_RESTRICTION', true),
            'blacklist' => env('GATEWAY_IP_BLACKLIST', ''),
            'whitelist' => env('GATEWAY_IP_WHITELIST', ''),
        ],

        // CORS
        'cors' => [
            'enabled' => env('GATEWAY_CORS', true),
            'origins' => explode(',', env('GATEWAY_CORS_ORIGINS', '*')),
            'methods' => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
            'headers' => 'Content-Type,Authorization,X-Requested-With,X-API-Key,X-Signature,X-Nonce,X-Timestamp',
            'credentials' => env('GATEWAY_CORS_CREDENTIALS', false),
            'max_age' => env('GATEWAY_CORS_MAX_AGE', 3600),
        ],

        // SSL 终止
        'ssl' => [
            'enabled' => env('GATEWAY_SSL', true),
            'redirect_http_to_https' => env('GATEWAY_SSL_REDIRECT', true),
            'tls_versions' => ['TLSv1.2', 'TLSv1.3'],
            'cert_file' => env('SSL_CERT_PATH'),
            'key_file' => env('SSL_KEY_PATH'),
        ],

        // 请求体限制
        'body_limit' => [
            'enabled' => env('GATEWAY_BODY_LIMIT', true),
            'max_body_size' => env('GATEWAY_MAX_BODY', 10485760), // 10MB
        ],

        // 认证卸载
        'auth_unloading' => [
            'enabled' => env('GATEWAY_AUTH_UNLOAD', false),
            'method' => env('GATEWAY_AUTH_METHOD', 'jwt'),  // jwt | apikey | oauth2
            'jwt_secret' => env('GATEWAY_JWT_SECRET'),
            'forward_headers' => ['X-User-ID', 'X-Tenant-ID', 'X-User-Roles'],
        ],

        // 日志采集
        'logging' => [
            'enabled' => env('GATEWAY_LOGGING', true),
            'format' => env('GATEWAY_LOG_FORMAT', 'json'),   // json | nginx | custom
            'log_all_requests' => env('GATEWAY_LOG_ALL', false),
            'sample_rate' => env('GATEWAY_LOG_SAMPLE', 0.1), // 采样率 0.0-1.0
        ],

        // Prometheus 监控
        'prometheus' => [
            'enabled' => env('GATEWAY_PROMETHEUS', false),
        ],

        // 缓存
        'proxy_cache' => [
            'enabled' => env('GATEWAY_CACHE', false),
            'strategy' => env('GATEWAY_CACHE_STRATEGY', 'cache_first'),
            'ttl_seconds' => env('GATEWAY_CACHE_TTL', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kong 专属配置
    |--------------------------------------------------------------------------
    */
    'kong' => [
        'version' => env('KONG_VERSION', '3.x'),
        'database' => env('KONG_DATABASE', 'postgres'), // postgres | cassandra | off
        'declarative_config' => env('KONG_DECLARATIVE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | APISIX 专属配置
    |--------------------------------------------------------------------------
    */
    'apisix' => [
        'version' => env('APISIX_VERSION', '3.x'),
        'data_plane' => env('APISIX_DATA_PLANE', ''),
        'control_plane' => env('APISIX_CONTROL_PLANE', ''),
        'enable_admin_api' => env('APISIX_ENABLE_ADMIN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 健康检查
    |--------------------------------------------------------------------------
    */
    'healthcheck' => [
        'enabled' => env('GATEWAY_HEALTHCHECK', true),
        'active' => [
            'type' => 'http',
            'concurrency' => 10,
            'healthy' => ['interval' => 30, 'successes' => 3],
            'unhealthy' => ['interval' => 5, 'failures' => 5, 'http_failures' => 3],
        ],
        'passive' => [
            'type' => 'http',
            'healthy' => ['successes' => 5],
            'unhealthy' => ['failures' => 3, 'http_failures' => 3, 'timeouts' => 3],
        ],
        'threshold' => env('GATEWAY_HEALTHY_THRESHOLD', 80), // 健康节点百分比阈值
    ],

    /*
    |--------------------------------------------------------------------------
    | 监控与告警
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'stats_ttl' => env('GATEWAY_STATS_TTL', 300),
        'alert_on_node_down' => env('GATEWAY_ALERT_NODE_DOWN', true),
        'alert_on_high_latency' => env('GATEWAY_ALERT_HIGH_LATENCY', false),
        'latency_threshold_ms' => env('GATEWAY_LATENCY_THRESHOLD', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | 降级策略
    |--------------------------------------------------------------------------
    */
    'degradation' => [
        'bypass_auth_on_failure' => env('GATEWAY_BYPASS_AUTH', false),
        'fallback_upstream' => env('GATEWAY_FALLBACK_UPSTREAM', ''),
        'graceful_shutdown' => env('GATEWAY_GRACEFUL_SHUTDOWN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 部署配置（docker-compose / 声明式配置）
    |--------------------------------------------------------------------------
    */
    'deploy' => [
        'kong' => [
            'docker_image' => 'kong:3.8',
            'container_name' => 'huwutong-kong',
            'ports' => ['8000:8000', '8443:8443', '8001:8001'],
            'env_file' => '.env.kong',
            'volumes' => [
                './deploy/gateway/kong/declarative.yml:/etc/kong/declarative/declarative.yml',
            ],
        ],
        'apisix' => [
            'docker_image' => 'apache/apisix:3.9',
            'container_name' => 'huwutong-apisix',
            'ports' => ['9080:9080', '9443:9443', '9180:9180'],
            'env_file' => '.env.apisix',
            'volumes' => [
                './deploy/gateway/apisix/config.yml:/usr/local/apisix/conf/config.yaml',
            ],
        ],
    ],
];
