<?php

/**
 * gRPC 服务间通信配置 (M1.3-28)
 *
 * 基于 M1.1-23 Protobuf 定义实现内部服务 gRPC 调用。
 * 支持与 REST API 双模并存：外部 REST + 内部 gRPC。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | 启用开关
    |--------------------------------------------------------------------------
    */
    'enabled' => env('GRPC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | gRPC 服务器配置
    |--------------------------------------------------------------------------
    */
    'server' => [
        'host' => env('GRPC_SERVER_HOST', '0.0.0.0'),
        'port' => env('GRPC_SERVER_PORT', 50051),
        'max_workers' => env('GRPC_MAX_WORKERS', 4),
        'max_message_size' => env('GRPC_MAX_MESSAGE_SIZE', 4 * 1024 * 1024), // 4MB
    ],

    /*
    |--------------------------------------------------------------------------
    | gRPC 客户端配置
    |--------------------------------------------------------------------------
    */
    'client' => [
        'timeout' => env('GRPC_CLIENT_TIMEOUT', 10),       // 默认超时（秒）
        'retries' => env('GRPC_CLIENT_RETRIES', 3),        // 重试次数
        'retry_delay_ms' => env('GRPC_CLIENT_RETRY_DELAY', 100), // 重试间隔（毫秒）

        'license_service' => [
            'host' => env('GRPC_LICENSE_HOST', 'localhost'),
            'port' => env('GRPC_LICENSE_PORT', 50052),
            'timeout' => env('GRPC_LICENSE_TIMEOUT', 10),
        ],
        'device_service' => [
            'host' => env('GRPC_DEVICE_HOST', 'localhost'),
            'port' => env('GRPC_DEVICE_PORT', 50053),
            'timeout' => env('GRPC_DEVICE_TIMEOUT', 10),
        ],
        'billing_service' => [
            'host' => env('GRPC_BILLING_HOST', 'localhost'),
            'port' => env('GRPC_BILLING_PORT', 50054),
            'timeout' => env('GRPC_BILLING_TIMEOUT', 15),
        ],
        'notification_service' => [
            'host' => env('GRPC_NOTIFICATION_HOST', 'localhost'),
            'port' => env('GRPC_NOTIFICATION_PORT', 50055),
            'timeout' => env('GRPC_NOTIFICATION_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 运行模式
    |--------------------------------------------------------------------------
    | grpc:       真实 gRPC 扩展（生产推荐，需安装 grpc PHP 扩展）
    | http2:      HTTP/2 模拟（无需 gRPC 扩展，通过 HTTP/2 + JSON）
    | rest:       REST 回退（通过 REST API 转发，开发环境）
    */
    'mode' => env('GRPC_MODE', 'rest'),

    /*
    |--------------------------------------------------------------------------
    | 服务发现
    |--------------------------------------------------------------------------
    | static:   静态配置（配置文件定义）
    | consul:   Consul 服务发现
    | kubernetes: K8s DNS 服务发现
    */
    'discovery' => [
        'type' => env('GRPC_DISCOVERY', 'static'),
        'consul_host' => env('GRPC_CONSUL_HOST', 'consul:8500'),
        'kubernetes_namespace' => env('GRPC_K8S_NAMESPACE', 'huwutong'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 健康检查
    |--------------------------------------------------------------------------
    */
    'healthcheck' => [
        'enabled' => env('GRPC_HEALTHCHECK', true),
        'interval_seconds' => env('GRPC_HEALTHCHECK_INTERVAL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | 监控
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => env('GRPC_MONITORING', true),
        'slow_threshold_ms' => env('GRPC_SLOW_THRESHOLD', 1000),
        'metrics_prefix' => 'grpc',
    ],

    /*
    |--------------------------------------------------------------------------
    | 服务定义（Proto 文件路径）
    |--------------------------------------------------------------------------
    */
    'protos' => [
        'base_path' => base_path('protos'),
        'generated_path' => base_path('protos/generated'),
        'services' => [
            'license' => [
                'proto' => 'license.proto',
                'service' => 'LicenseService',
                'package' => 'huwutong.license',
            ],
            'device' => [
                'proto' => 'device.proto',
                'service' => 'DeviceService',
                'package' => 'huwutong.device',
            ],
            'billing' => [
                'proto' => 'billing.proto',
                'service' => 'BillingService',
                'package' => 'huwutong.billing',
            ],
            'notification' => [
                'proto' => 'notification.proto',
                'service' => 'NotificationService',
                'package' => 'huwutong.notification',
            ],
        ],
    ],
];
