<?php

/**
 * 公开集成文档（API / 错误码 / Webhook / 示例）
 * 不依赖管理端 API 文档库是否已灌数据。
 */

return [
    'base_url' => env('HWT_PUBLIC_API_BASE', 'https://api.huwutong.com'),

    'auth' => [
        'title' => '鉴权说明',
        'description' => 'SDK 与 REST 调用使用 Bearer API Key。部分端点另需 nonce / 签名（SDK 已内置）。',
        'header' => 'Authorization: Bearer {YOUR_API_KEY}',
        'content_type' => 'application/json',
    ],

    'api_groups' => [
        [
            'group' => 'license',
            'group_label' => 'License 核心',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/license/activate',
                    'summary' => '激活 License',
                    'description' => '将 License 绑定到设备并返回有效期、功能列表。',
                    'example_request' => [
                        'license_key' => 'HWT-ENT-XXXX-XXXX',
                        'machine_info' => [
                            'machine_id' => 'unique-machine-id',
                            'hostname' => 'server-01',
                            'platform' => 'linux',
                        ],
                    ],
                    'example_response' => [
                        'success' => true,
                        'data' => [
                            'license_key' => 'HWT-ENT-XXXX-XXXX',
                            'expires_at' => '2027-08-31T00:00:00Z',
                            'features' => ['offline', 'ai_features'],
                        ],
                    ],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/license/validate',
                    'summary' => '验证 License',
                    'description' => '在线校验授权状态，建议在应用启动或关键路径周期性调用。',
                    'example_request' => [
                        'license_key' => 'HWT-ENT-XXXX-XXXX',
                        'machine_info' => [
                            'machine_id' => 'unique-machine-id',
                        ],
                    ],
                    'example_response' => [
                        'success' => true,
                        'data' => [
                            'is_valid' => true,
                            'status' => 'active',
                            'expires_at' => '2027-08-31T00:00:00Z',
                            'features' => ['offline'],
                        ],
                    ],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/license/deactivate',
                    'summary' => '解除激活',
                    'description' => '释放设备占用，便于换机或卸载后重新激活。',
                    'example_request' => [
                        'license_key' => 'HWT-ENT-XXXX-XXXX',
                        'machine_info' => [
                            'machine_id' => 'unique-machine-id',
                        ],
                    ],
                    'example_response' => [
                        'success' => true,
                        'data' => ['success' => true],
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/license/info/{key}',
                    'summary' => '查询 License 信息',
                    'description' => '按 License Key 查询状态、到期时间与绑定设备概览。',
                    'example_request' => null,
                    'example_response' => [
                        'success' => true,
                        'data' => [
                            'license_key' => 'HWT-ENT-XXXX-XXXX',
                            'status' => 'active',
                            'plan' => 'enterprise',
                            'max_devices' => 5,
                        ],
                    ],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/license/check-feature',
                    'summary' => '检查 Feature Flag',
                    'description' => '判断当前授权是否启用指定功能模块。',
                    'example_request' => [
                        'license_key' => 'HWT-ENT-XXXX-XXXX',
                        'feature_key' => 'ai_features',
                    ],
                    'example_response' => [
                        'success' => true,
                        'data' => ['enabled' => true],
                    ],
                ],
            ],
        ],
        [
            'group' => 'offline',
            'group_label' => '离线授权',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/offline/generate',
                    'summary' => '获取离线 License 文件',
                    'description' => '生成可分发的离线授权包，适用于无外网环境。',
                    'example_request' => ['license_key' => 'HWT-ENT-XXXX-XXXX'],
                    'example_response' => [
                        'success' => true,
                        'data' => [
                            'license_data' => 'base64-or-signed-payload',
                            'expires_at' => '2027-08-31T00:00:00Z',
                        ],
                    ],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/offline/verify',
                    'summary' => '离线验证',
                    'description' => '在本地校验离线授权包签名与有效期。',
                    'example_request' => [
                        'license_data' => 'base64-or-signed-payload',
                        'public_key' => '',
                    ],
                    'example_response' => [
                        'success' => true,
                        'data' => ['valid' => true],
                    ],
                ],
            ],
        ],
        [
            'group' => 'telemetry',
            'group_label' => '遥测',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/telemetry/heartbeat',
                    'summary' => 'SDK 心跳',
                    'description' => '上报 SDK 版本、设备与运行状态，便于运维与兼容性统计。',
                    'example_request' => [
                        'license_key' => 'HWT-ENT-XXXX-XXXX',
                        'sdk_language' => 'php',
                        'sdk_version' => '1.0.0',
                    ],
                    'example_response' => [
                        'success' => true,
                        'data' => ['accepted' => true],
                    ],
                ],
            ],
        ],
    ],

    'error_codes' => [
        ['code' => 'LICENSE_EXPIRED', 'http' => 403, 'message' => 'License 已过期'],
        ['code' => 'LICENSE_SUSPENDED', 'http' => 403, 'message' => 'License 已被挂起'],
        ['code' => 'LICENSE_REVOKED', 'http' => 403, 'message' => 'License 已被吊销'],
        ['code' => 'LICENSE_NOT_FOUND', 'http' => 404, 'message' => 'License Key 不存在'],
        ['code' => 'DEVICE_LIMIT', 'http' => 429, 'message' => '设备数量超限'],
        ['code' => 'ACTIVATION_LIMIT', 'http' => 429, 'message' => '激活次数超限'],
        ['code' => 'FINGERPRINT_MISMATCH', 'http' => 401, 'message' => '设备指纹不匹配'],
        ['code' => 'SIGNATURE_INVALID', 'http' => 401, 'message' => '签名验证失败'],
        ['code' => 'RATE_LIMITED', 'http' => 429, 'message' => '请求频率超限'],
        ['code' => 'SDK_VERSION_DEPRECATED', 'http' => 426, 'message' => 'SDK 版本已废弃'],
    ],

    'webhooks' => [
        'overview' => '互物通可在 License 状态变更、设备激活、订单支付等事件发生时，向您配置的 URL 推送 HTTP POST 通知。',
        'setup_steps' => [
            '在管理后台打开 Webhook / 自动化配置',
            '填写 HTTPS 回调地址，并选择订阅事件',
            '保存签名密钥，在接收端校验 X-HWT-Signature（或配置中的签名头）',
            '返回 2xx；失败将按策略重试',
        ],
        'events' => [
            ['name' => 'license.activated', 'desc' => 'License 激活成功'],
            ['name' => 'license.validated', 'desc' => '在线验证完成（可选）'],
            ['name' => 'license.expired', 'desc' => 'License 到期'],
            ['name' => 'license.revoked', 'desc' => 'License 被吊销'],
            ['name' => 'device.bound', 'desc' => '设备绑定成功'],
            ['name' => 'device.unbound', 'desc' => '设备解绑'],
            ['name' => 'invoice.paid', 'desc' => '账单支付成功'],
        ],
        'example_payload' => [
            'id' => 'evt_01HXXXX',
            'type' => 'license.activated',
            'created_at' => '2026-08-31T12:00:00Z',
            'data' => [
                'license_key' => 'HWT-ENT-XXXX-XXXX',
                'machine_id' => 'unique-machine-id',
                'expires_at' => '2027-08-31T00:00:00Z',
            ],
        ],
        'verify_tip' => '请使用共享密钥对原始请求体做 HMAC-SHA256，并与签名头比对，防止伪造回调。',
    ],

    'examples' => [
        [
            'id' => 'php',
            'name' => 'PHP 示例',
            'path' => 'examples/php',
            'docs_url' => '/docs/sdk/php',
            'commands' => [
                'cd examples/php',
                'composer install',
                'php examples/activate.php HWT-XXXX-XXXX',
                'php examples/validate.php HWT-XXXX-XXXX',
            ],
        ],
        [
            'id' => 'nodejs',
            'name' => 'Node.js 示例',
            'path' => 'examples/nodejs',
            'docs_url' => '/docs/sdk/node',
            'commands' => [
                'cd examples/nodejs',
                'npm install',
                'node examples/activate.js HWT-XXXX-XXXX',
                'node examples/server.js',
            ],
        ],
        [
            'id' => 'python',
            'name' => 'Python 示例',
            'path' => 'examples/python',
            'docs_url' => '/docs/sdk/python',
            'commands' => [
                'cd sdk/python && pip install -e .',
                'cd examples/python',
                'HWT_API_KEY=sk_test_xxx python app.py',
            ],
        ],
    ],

    'hub_links' => [
        ['title' => '快速入门', 'path' => '/docs/quickstart', 'desc' => '5 分钟完成注册、安装与验证'],
        ['title' => 'SDK 下载', 'path' => '/sdk', 'desc' => '九种语言安装命令与示例'],
        ['title' => 'API 参考', 'path' => '/api-docs', 'desc' => 'License / 离线 / 遥测端点'],
        ['title' => '错误码', 'path' => '/docs/error-codes', 'desc' => 'M2-34 标准错误码'],
        ['title' => 'Webhook', 'path' => '/docs/webhooks', 'desc' => '事件推送与验签'],
        ['title' => '帮助中心', 'path' => '/help', 'desc' => '产品使用与 FAQ'],
    ],
];
