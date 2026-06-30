<?php

// M3-21 调用链追踪（Jaeger）+ SLO 错误预算配置

return [
    'tracing' => [
        'sampling_rate' => env('TRACING_SAMPLING_RATE', 0.1), // 10% 采样
        'slow_request_threshold_ms' => 1000,
        'max_spans_per_trace' => 100,
        'retention_days' => 30,
        'exporters' => [
            'jaeger' => [
                'enabled' => env('JAEGER_ENABLED', false),
                'host' => env('JAEGER_HOST', 'localhost'),
                'port' => env('JAEGER_PORT', 6831),
            ],
            'otlp' => [
                'enabled' => env('OTLP_ENABLED', false),
                'endpoint' => env('OTLP_ENDPOINT', 'http://localhost:4318'),
            ],
        ],
    ],

    'slo' => [
        'default_target' => 99.9,
        'default_window_days' => 30,
        'burn_rate_thresholds' => [
            'warning' => 2,    // 2x 燃烧率告警
            'critical' => 5,   // 5x 燃烧率告警
        ],
        'sli_types' => [
            'availability' => ['metric' => 'success_rate', 'unit' => '%'],
            'latency_p95' => ['metric' => 'p95_latency', 'unit' => 'ms'],
            'latency_p99' => ['metric' => 'p99_latency', 'unit' => 'ms'],
            'error_rate' => ['metric' => 'error_rate', 'unit' => '%'],
            'throughput' => ['metric' => 'requests_per_minute', 'unit' => 'rpm'],
        ],
        'auto_remediation' => [
            'enabled' => false,
            'actions' => ['scale_up', 'circuit_breaker', 'redirect_traffic'],
        ],
    ],
];
