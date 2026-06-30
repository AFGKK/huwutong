<?php

namespace Tests\Contract\Consumer;

use Tests\Contract\PactContract;
use Tests\TestCase;

/**
 * PHP SDK 消费者契约测试
 *
 * 定义 PHP SDK 消费者对 HWT License API 的期望契约。
 * 这些契约由 SDK 维护者定义，提供者（Laravel API）必须满足。
 *
 * 运行方式: php artisan test --filter=PhpSdkContractTest
 * 重新生成: php artisan contract:generate
 */
class PhpSdkContractTest extends TestCase
{
    /**
     * 测试: License 激活契约
     *
     * SDK 调用 POST /api/license/activate 激活 License
     */
    public function test_license_activate_contract(): void
    {
        $interactions = [
            [
                'description' => '成功激活 License',
                'providerState' => '存在有效的 License 和未绑定设备',
                'request' => [
                    'method' => 'POST',
                    'path' => '/api/license/activate',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer $API_TOKEN',
                    ],
                    'body' => [
                        'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
                        'device_fingerprint' => '2:a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b',
                        'device_name' => '生产服务器-01',
                        'platform' => 'linux',
                    ],
                ],
                'response' => [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [
                            'id' => 1,
                            'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
                            'status' => 'active',
                            'activated_at' => '2026-06-13T10:00:00+08:00',
                            'expires_at' => '2027-06-13T10:00:00+08:00',
                            'device' => [
                                'id' => 42,
                                'fingerprint' => '2:a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b',
                                'name' => '生产服务器-01',
                                'platform' => 'linux',
                                'is_trusted' => true,
                            ],
                        ],
                        'message' => '激活成功',
                    ],
                    'matchingRules' => [
                        '$.body.data.id' => ['pact:matcher:type' => 'type', 'value' => 1],
                        '$.body.data.activated_at' => ['pact:matcher:type' => 'timestamp'],
                        '$.body.data.expires_at' => ['pact:matcher:type' => 'timestamp'],
                        '$.body.data.device.id' => ['pact:matcher:type' => 'type', 'value' => 1],
                    ],
                ],
            ],
            [
                'description' => 'License 已过期激活失败',
                'providerState' => 'License 已过期',
                'request' => [
                    'method' => 'POST',
                    'path' => '/api/license/activate',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer $API_TOKEN',
                    ],
                    'body' => [
                        'license_key' => 'HWT-ENT-2023-EXPR-9876-DCBA',
                        'device_fingerprint' => '2:f1e2d3c4b5a69788796a5b4c3d2e1f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d',
                        'device_name' => '过期设备',
                        'platform' => 'windows',
                    ],
                ],
                'response' => [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'error' => [
                            'code' => 'LICENSE_EXPIRED',
                            'message' => 'License 已过期，请续期',
                            'details' => [
                                'expired_at' => '2026-01-01T00:00:00+08:00',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'description' => 'License Key 格式无效',
                'providerState' => '任意状态',
                'request' => [
                    'method' => 'POST',
                    'path' => '/api/license/activate',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer $API_TOKEN',
                    ],
                    'body' => [
                        'license_key' => 'invalid-key-format',
                        'device_fingerprint' => '2:0000000000000000000000000000000000000000000000000000000000000000',
                        'device_name' => '测试设备',
                        'platform' => 'unknown',
                    ],
                ],
                'response' => [
                    'status' => 422,
                    'body' => [
                        'success' => false,
                        'message' => '验证失败',
                        'errors' => [
                            'license_key' => ['License Key 格式无效'],
                        ],
                    ],
                ],
            ],
        ];

        $json = PactContract::generate('PHP SDK', 'HWT License API', $interactions);
        $path = PactContract::saveToFile('PHP SDK', 'HWT License API', $json);

        $this->assertFileExists($path, 'Pact 契约文件未生成');

        $loaded = PactContract::loadFromFile('PHP SDK', 'HWT License API');
        $this->assertNotNull($loaded);
        $this->assertCount(3, $loaded['interactions']);
    }

    /**
     * 测试: License 验证契约
     *
     * SDK 调用 POST /api/license/validate 验证 License 有效性
     */
    public function test_license_validate_contract(): void
    {
        $interactions = [
            [
                'description' => '成功验证活跃 License',
                'providerState' => 'License 处于活跃状态',
                'request' => [
                    'method' => 'POST',
                    'path' => '/api/license/validate',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer $API_TOKEN',
                    ],
                    'body' => [
                        'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
                        'device_fingerprint' => '2:a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b',
                    ],
                ],
                'response' => [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [
                            'valid' => true,
                            'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
                            'status' => 'active',
                            'product' => ['name' => '企业版 License', 'version' => '3.2.1'],
                            'expires_at' => '2027-06-13T10:00:00+08:00',
                            'days_remaining' => 365,
                            'features' => [
                                ['code' => 'multi_tenant', 'name' => '多租户支持', 'enabled' => true],
                                ['code' => 'audit_log', 'name' => '审计日志', 'enabled' => true],
                                ['code' => 'sso', 'name' => '单点登录', 'enabled' => false],
                            ],
                        ],
                        'message' => '验证成功',
                    ],
                    'matchingRules' => [
                        '$.body.data.expires_at' => ['pact:matcher:type' => 'timestamp'],
                        '$.body.data.days_remaining' => ['pact:matcher:type' => 'type', 'value' => 365],
                    ],
                ],
            ],
            [
                'description' => '设备不匹配验证失败',
                'providerState' => '设备指纹未绑定该 License',
                'request' => [
                    'method' => 'POST',
                    'path' => '/api/license/validate',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer $API_TOKEN',
                    ],
                    'body' => [
                        'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
                        'device_fingerprint' => '2:unknown-device-fingerprint-00000000000000000000000000000000',
                    ],
                ],
                'response' => [
                    'status' => 403,
                    'body' => [
                        'success' => false,
                        'error' => [
                            'code' => 'DEVICE_NOT_BOUND',
                            'message' => '设备未绑定此 License',
                            'details' => [
                                'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $json = PactContract::generate('PHP SDK', 'HWT License API', $interactions, '1.0.0');
        $path = PactContract::saveToFile('PHP SDK', 'HWT License API', $json);

        $this->assertFileExists($path);
    }

    /**
     * 测试: License 查询列表契约
     *
     * SDK 调用 GET /api/licenses 查询 License 列表
     */
    public function test_license_list_contract(): void
    {
        $interactions = [
            [
                'description' => '分页查询 License 列表',
                'providerState' => '存在多个 License 记录',
                'request' => [
                    'method' => 'GET',
                    'path' => '/api/licenses',
                    'query' => 'page=1&per_page=15&sort=-created_at',
                    'headers' => [
                        'Authorization' => 'Bearer $API_TOKEN',
                    ],
                ],
                'response' => [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [
                            [
                                'id' => 1,
                                'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
                                'status' => 'active',
                                'product' => ['id' => 1, 'name' => '企业版 License'],
                                'customer' => ['id' => 1, 'name' => '互物通科技'],
                                'expires_at' => '2027-06-13T10:00:00+08:00',
                                'created_at' => '2026-01-01T10:00:00+08:00',
                            ],
                        ],
                        'meta' => [
                            'current_page' => 1,
                            'last_page' => 5,
                            'per_page' => 15,
                            'total' => 65,
                        ],
                        'message' => 'ok',
                    ],
                    'matchingRules' => [
                        '$.body.data[0].id' => ['pact:matcher:type' => 'type', 'value' => 1],
                        '$.body.data[0].expires_at' => ['pact:matcher:type' => 'timestamp'],
                        '$.body.data[0].created_at' => ['pact:matcher:type' => 'timestamp'],
                        '$.body.meta.total' => ['pact:matcher:type' => 'type', 'value' => 65],
                    ],
                ],
            ],
            [
                'description' => '按状态筛选 License',
                'providerState' => '存在已过期的 License',
                'request' => [
                    'method' => 'GET',
                    'path' => '/api/licenses',
                    'query' => 'filter[status]=expired&per_page=15',
                    'headers' => [
                        'Authorization' => 'Bearer $API_TOKEN',
                    ],
                ],
                'response' => [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [],
                        'meta' => [
                            'current_page' => 1,
                            'last_page' => 1,
                            'per_page' => 15,
                            'total' => 0,
                        ],
                        'message' => 'ok',
                    ],
                ],
            ],
        ];

        $json = PactContract::generate('PHP SDK', 'HWT License API', $interactions, '1.0.0');
        $path = PactContract::saveToFile('PHP SDK', 'HWT License API', $json);

        $this->assertFileExists($path);
    }

    /**
     * 测试: 设备管理契约
     */
    public function test_device_management_contract(): void
    {
        $interactions = [
            [
                'description' => '查询设备列表',
                'providerState' => '存在已注册设备',
                'request' => [
                    'method' => 'GET',
                    'path' => '/api/devices',
                    'query' => 'page=1&per_page=15',
                    'headers' => ['Authorization' => 'Bearer $API_TOKEN'],
                ],
                'response' => [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [
                            [
                                'id' => 1,
                                'fingerprint' => '2:a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b',
                                'name' => '生产服务器-01',
                                'platform' => 'linux',
                                'is_trusted' => true,
                                'trust_score' => 85,
                                'last_seen_at' => '2026-06-13T08:00:00+08:00',
                                'created_at' => '2026-01-15T10:00:00+08:00',
                            ],
                        ],
                        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 3],
                        'message' => 'ok',
                    ],
                    'matchingRules' => [
                        '$.body.data[0].id' => ['pact:matcher:type' => 'type', 'value' => 1],
                        '$.body.data[0].trust_score' => ['pact:matcher:type' => 'type', 'value' => 85],
                        '$.body.data[0].last_seen_at' => ['pact:matcher:type' => 'timestamp'],
                    ],
                ],
            ],
            [
                'description' => '删除设备',
                'providerState' => '设备存在且可删除',
                'request' => [
                    'method' => 'DELETE',
                    'path' => '/api/devices/1',
                    'headers' => ['Authorization' => 'Bearer $API_TOKEN'],
                ],
                'response' => [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => ['id' => 1, 'deleted' => true],
                        'message' => '设备已删除',
                    ],
                ],
            ],
        ];

        $json = PactContract::generate('PHP SDK', 'HWT License API', $interactions);
        $path = PactContract::saveToFile('PHP SDK', 'HWT License API', $json);

        $this->assertFileExists($path);
    }

    /**
     * 测试: 客户管理契约
     */
    public function test_customer_management_contract(): void
    {
        $interactions = [
            [
                'description' => '查询客户列表',
                'providerState' => '存在多个客户',
                'request' => [
                    'method' => 'GET',
                    'path' => '/api/customers',
                    'query' => 'page=1&per_page=15',
                    'headers' => ['Authorization' => 'Bearer $API_TOKEN'],
                ],
                'response' => [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [
                            [
                                'id' => 1,
                                'name' => '互物通科技',
                                'email' => 'contact@huwutong.com',
                                'status' => 'active',
                                'licenses_count' => 5,
                                'created_at' => '2026-01-01T10:00:00+08:00',
                            ],
                        ],
                        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1],
                        'message' => 'ok',
                    ],
                ],
            ],
        ];

        $json = PactContract::generate('PHP SDK', 'HWT License API', $interactions);
        PactContract::saveToFile('PHP SDK', 'HWT License API', $json);

        $this->assertTrue(true);
    }
}
