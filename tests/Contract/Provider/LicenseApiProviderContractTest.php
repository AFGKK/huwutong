<?php

namespace Tests\Contract\Provider;

use Tests\Contract\PactContract;
use Tests\TestCase;

/**
 * HWT License API 提供者契约验证测试
 *
 * 验证 Laravel API 实现是否符合消费者（SDK）定义的 Pact 契约。
 * 每个测试对应一个 Pact 交互描述，验证实际 API 响应与契约一致。
 *
 * 运行方式: php artisan test --filter=LicenseApiProviderContractTest
 */
class LicenseApiProviderContractTest extends TestCase
{
    /**
     * 设置测试用户认证
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 使用 Sanctum 认证模拟
        $user = \App\Models\User::factory()->create([
            'email' => 'contract-test@huwutong.com',
            'name' => 'Contract Test User',
        ]);
        $this->actingAs($user, 'sanctum');
    }

    /**
     * 验证所有已注册的 Pact 契约
     *
     * 遍历 pacts/ 目录下的所有契约文件，逐一验证。
     */
    public function test_all_pacts_pass(): void
    {
        $contracts = PactContract::listContracts();
        $this->assertNotEmpty($contracts, '没有找到 Pact 契约文件，请先运行消费者测试生成契约');

        $totalInteractions = 0;
        $passedInteractions = 0;
        $allErrors = [];

        foreach ($contracts as $contract) {
            $pact = PactContract::loadFromFile($contract['consumer'], $contract['provider']);
            if (!$pact) continue;

            foreach ($pact['interactions'] as $interaction) {
                $totalInteractions++;
                $errors = [];

                try {
                    $result = $this->executeInteraction($interaction);
                    $verified = PactContract::verifyResponse($interaction, $result['status'], $result['body'], $errors);

                    if ($verified) {
                        $passedInteractions++;
                    } else {
                        $allErrors = array_merge($allErrors, $errors);
                    }
                } catch (\Throwable $e) {
                    $allErrors[] = "[{$interaction['description']}] 执行异常: " . $e->getMessage();
                }
            }
        }

        foreach ($allErrors as $error) {
            $this->addWarning($error);
        }

        $this->assertEquals(
            $totalInteractions,
            $passedInteractions,
            "契约验证: {$passedInteractions}/{$totalInteractions} 通过\n" . implode("\n", $allErrors)
        );
    }

    /**
     * 验证: License 激活成功
     */
    public function test_activate_license_success(): void
    {
        $pact = PactContract::loadFromFile('PHP SDK', 'HWT License API');
        $this->assertNotNull($pact, 'Pact 契约未找到，请先运行消费者测试');

        $interaction = $this->findInteraction($pact, '成功激活 License');
        $this->assertNotNull($interaction);

        // 创建测试数据：产品和 License
        $product = \App\Models\Product::factory()->create(['name' => '企业版 License']);
        $license = \App\Models\License::factory()->create([
            'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
            'status' => 'pending',
            'product_id' => $product->id,
        ]);

        $response = $this->postJson('/api/license/activate', [
            'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
            'device_fingerprint' => '2:a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b',
            'device_name' => '生产服务器-01',
            'platform' => 'linux',
        ]);

        $errors = [];
        $verified = PactContract::verifyResponse($interaction, $response->status(), $response->json() ?? [], $errors);

        foreach ($errors as $error) {
            $this->addWarning($error);
        }

        $this->assertTrue($verified, 'License 激活契约验证失败');
    }

    /**
     * 验证: License 验证成功
     */
    public function test_validate_license_success(): void
    {
        $pact = PactContract::loadFromFile('PHP SDK', 'HWT License API');
        $this->assertNotNull($pact);

        $interaction = $this->findInteraction($pact, '成功验证活跃 License');
        $this->assertNotNull($interaction);

        // 创建测试数据
        $product = \App\Models\Product::factory()->create(['name' => '企业版 License']);
        $license = \App\Models\License::factory()->create([
            'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
            'status' => 'active',
            'product_id' => $product->id,
            'expires_at' => now()->addYear(),
        ]);
        $device = \App\Models\Device::factory()->create([
            'fingerprint' => '2:a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b',
        ]);
        $license->devices()->attach($device->id, ['activated_at' => now()]);

        $response = $this->postJson('/api/license/validate', [
            'license_key' => 'HWT-ENT-2024-ABCD-1234-EFGH',
            'device_fingerprint' => '2:a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b',
        ]);

        $errors = [];
        $verified = PactContract::verifyResponse($interaction, $response->status(), $response->json() ?? [], $errors);

        foreach ($errors as $error) {
            $this->addWarning($error);
        }

        $this->assertTrue($verified, 'License 验证契约验证失败');
    }

    /**
     * 验证: License 列表分页查询
     */
    public function test_license_list_paginated(): void
    {
        $pact = PactContract::loadFromFile('PHP SDK', 'HWT License API');
        $this->assertNotNull($pact);

        $interaction = $this->findInteraction($pact, '分页查询 License 列表');
        $this->assertNotNull($interaction);

        // 创建测试数据
        \App\Models\License::factory()->count(3)->create();

        $response = $this->getJson('/api/licenses?page=1&per_page=15&sort=-created_at');

        $errors = [];
        $verified = PactContract::verifyResponse($interaction, $response->status(), $response->json() ?? [], $errors);

        foreach ($errors as $error) {
            $this->addWarning($error);
        }

        $this->assertTrue($verified, 'License 列表契约验证失败');
    }

    /**
     * 验证: 设备列表查询
     */
    public function test_device_list(): void
    {
        $pact = PactContract::loadFromFile('PHP SDK', 'HWT License API');
        $this->assertNotNull($pact);

        $interaction = $this->findInteraction($pact, '查询设备列表');
        $this->assertNotNull($interaction);

        \App\Models\Device::factory()->count(3)->create();

        $response = $this->getJson('/api/devices?page=1&per_page=15');

        $errors = [];
        $verified = PactContract::verifyResponse($interaction, $response->status(), $response->json() ?? [], $errors);

        foreach ($errors as $error) {
            $this->addWarning($error);
        }

        $this->assertTrue($verified, '设备列表契约验证失败');
    }

    /**
     * 在 Pact 中查找指定描述的交互
     */
    private function findInteraction(array $pact, string $description): ?array
    {
        foreach ($pact['interactions'] ?? [] as $interaction) {
            if ($interaction['description'] === $description) {
                return $interaction;
            }
        }
        return null;
    }

    /**
     * 执行 Pact 交互（发送真实 HTTP 请求到 API）
     */
    private function executeInteraction(array $interaction): array
    {
        $request = $interaction['request'];
        $method = strtoupper($request['method'] ?? 'GET');
        $path = $request['path'] ?? '/';
        $query = $request['query'] ?? '';
        $body = $request['body'] ?? [];
        $headers = $request['headers'] ?? [];

        $url = $path;
        if ($query) {
            $url .= '?' . (is_string($query) ? $query : http_build_query($query));
        }

        $server = $this->transformHeadersToServerVars($headers);

        switch ($method) {
            case 'GET':
                $response = $this->getJson($url, $server);
                break;
            case 'POST':
                $response = $this->postJson($url, $body, $server);
                break;
            case 'PUT':
                $response = $this->putJson($url, $body, $server);
                break;
            case 'DELETE':
                $response = $this->deleteJson($url, $server);
                break;
            case 'PATCH':
                $response = $this->patchJson($url, $body, $server);
                break;
            default:
                throw new \InvalidArgumentException("不支持的 HTTP 方法: {$method}");
        }

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    /**
     * 将 HTTP 头转换为 Laravel 测试 Server 变量
     */
    protected function transformHeadersToServerVars(array $headers): array
    {
        $server = [];
        foreach ($headers as $key => $value) {
            if (str_starts_with($key, 'Authorization')) {
                $server['HTTP_AUTHORIZATION'] = $value;
            } else {
                $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
                $server[$serverKey] = $value;
            }
        }
        return $server;
    }
}
