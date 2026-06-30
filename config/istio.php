<?php

// 互物通 Istio 服务网格配置
return [
    'enabled' => env('ISTIO_ENABLED', false),
    'version' => env('ISTIO_VERSION', '1.21.x'),

    // mTLS 配置
    'mtls_enabled' => env('ISTIO_MTLS_ENABLED', true),
    'mtls_mode' => env('ISTIO_MTLS_MODE', 'STRICT'), // STRICT / PERMISSIVE / DISABLE

    // Sidecar 注入
    'sidecar_injection' => env('ISTIO_SIDECAR_INJECTION', 'enabled'),

    // 网格内服务定义
    'services' => [
        'hwt-api' => [
            'version' => 'v1',
            'port' => 80,
            'protocol' => 'http',
            'sidecar' => true,
            'mtls' => true,
            'virtual_service' => 'api.huwutong.com',
            'destination_rule' => 'hwt-api',
        ],
        'hwt-admin' => [
            'version' => 'v1',
            'port' => 80,
            'protocol' => 'http',
            'sidecar' => true,
            'mtls' => true,
            'virtual_service' => 'admin.huwutong.com',
            'destination_rule' => 'hwt-admin',
        ],
        'hwt-portal' => [
            'version' => 'v1',
            'port' => 80,
            'protocol' => 'http',
            'sidecar' => true,
            'mtls' => true,
            'virtual_service' => 'portal.huwutong.com',
            'destination_rule' => 'hwt-portal',
        ],
        'hwt-reverb' => [
            'version' => 'v1',
            'port' => 8080,
            'protocol' => 'tcp',
            'sidecar' => true,
            'mtls' => true,
            'virtual_service' => 'ws.huwutong.com',
            'destination_rule' => 'hwt-reverb',
        ],
    ],

    // 追踪配置
    'tracing' => [
        'enabled' => env('ISTIO_TRACING_ENABLED', true),
        'sampling_rate' => (float) env('ISTIO_TRACING_SAMPLING_RATE', 0.1),
    ],

    // 指标配置
    'metrics' => [
        'enabled' => env('ISTIO_METRICS_ENABLED', true),
    ],

    // 访问日志
    'access_log' => [
        'enabled' => env('ISTIO_ACCESS_LOG_ENABLED', true),
    ],

    // 可观测性 URL
    'observability' => [
        'grafana_url' => env('GRAFANA_URL', 'http://grafana:3000'),
        'jaeger_url' => env('JAEGER_URL', 'http://jaeger:16686'),
        'kiali_url' => env('KIALI_URL', 'http://kiali:20001'),
    ],

    // 流量管理默认值
    'traffic' => [
        'circuit_breaker' => [
            'max_connections' => 100,
            'max_pending_requests' => 10000,
            'max_requests_per_connection' => 10,
            'max_retries' => 3,
        ],
        'connection_pool' => [
            'tcp' => ['max_connections' => 100],
            'http' => ['http1_max_pending_requests' => 1024, 'http2_max_requests' => 1024],
        ],
        'retries' => [
            'attempts' => 3,
            'per_try_timeout' => '2s',
        ],
        'timeout' => '15s',
    ],
];
