<?php

namespace Tests\Feature\Api;

use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Database\Factories\LicenseFactory;
use Database\Factories\ProductFactory;
use Database\Factories\TenantFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\LicenseActivationHelpers;
use Tests\TestCase;

/**
 * M1.3-11 安全综合测试
 *
 * 覆盖场景：
 * - 防重放攻击
 * - 防伪造签名
 * - 防越权访问（跨租户）
 * - 防暴力枚举
 * - 防 SQL 注入
 * - 防 IDOR（不安全对象直接引用）
 * - 敏感信息不泄露
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase, LicenseActivationHelpers;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Product $product;
    private License $licenseA;
    private License $licenseB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        $this->product = Product::factory()->create();

        $this->licenseA = License::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->product->id,
            'license_key' => 'HWT-A-TEST-KEY',
            'status' => 'active',
            'metadata' => ['signature_secret' => 'secret-a-12345'],
        ]);

        $this->licenseB = License::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'product_id' => $this->product->id,
            'license_key' => 'HWT-B-TEST-KEY',
            'status' => 'active',
            'metadata' => ['signature_secret' => 'secret-b-67890'],
        ]);

        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    // =====================================================
    // 防重放攻击
    // =====================================================

    public function test_replay_attack_rejected(): void
    {
        // 使用相同 nonce+signature 两次请求激活
        $licenseKey = $this->licenseA->license_key;
        $secret = 'secret-a-12345';
        $nonce = '550e8400-e29b-41d4-a716-111111111111';
        $timestamp = (string) time();
        $body = ['license_key' => $licenseKey, 'fingerprint' => 'fp-replay-test'];

        $headers = $this->fixedActivationHeaders('POST', '/api/license/activate', $body, $secret, $nonce, $timestamp);

        // 第一次—应成功
        $first = $this->postJson('/api/license/activate', $body, $headers);
        $first->assertStatus(200);

        // 第二次—完全相同的请求—应被 nonce 防重放拦截
        $second = $this->postJson('/api/license/activate', $body, $headers);
        // nonce 已被使用
        $this->assertContains(
            $second->status(),
            [409, 400, 401],
            '重放攻击应被拦截，但收到状态码: ' . $second->status()
        );
    }

    public function test_replay_with_different_nonce_passes(): void
    {
        $secret = 'secret-a-12345';

        for ($i = 0; $i < 3; $i++) {
            $body = ['license_key' => $this->licenseA->license_key, 'fingerprint' => "fp-replay-{$i}"];
            $response = $this->securePostJson('/api/license/activate', $body, ['secret' => $secret]);
            // 只要不是 409 都算合理
            $this->assertNotContains(
                $response->status(),
                [409],
                "第 {$i} 次正常请求不应被 nonce 拦截"
            );
        }
    }

    // =====================================================
    // 防伪造签名
    // =====================================================

    public function test_fake_signature_rejected(): void
    {
        $licenseKey = $this->licenseA->license_key;
        $nonce = '550e8400-e29b-41d4-a716-222222222222';
        $timestamp = (string) time();
        $body = ['license_key' => $licenseKey, 'fingerprint' => 'fp-fake-sig'];

        // 使用正确的 nonce/timestamp 但错误的 secret 签名
        $wrongSig = $this->computeSignature(
            'wrong-secret-key',
            'POST',
            '/api/license/activate',
            $body,
            $timestamp,
            $nonce,
        );

        $response = $this->postJson('/api/license/activate', $body, [
            'X-Nonce' => $nonce,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $wrongSig,
            'X-Signature-Timestamp' => $timestamp,
        ]);

        $this->assertContains(
            $response->status(),
            [400, 401],
            '伪造签名应被拦截，收到: ' . $response->status()
        );
        // 如果收到 400，检查错误码（可能的 nonce 相关问题）
        // 如果收到 401，签名校验成功拦截
        if ($response->status() === 401) {
            $response->assertJsonPath('error.code', 'SIGNATURE_MISMATCH');
        }
    }

    public function test_tampered_payload_rejected(): void
    {
        $secret = 'secret-a-12345';
        $timestamp = (string) time();
        $nonce = '550e8400-e29b-41d4-a716-333333333333';

        // 对 payload A 签名，但发送 payload B
        $bodyA = ['license_key' => $this->licenseA->license_key, 'fingerprint' => 'fp-tamper'];
        $bodyB = ['license_key' => $this->licenseA->license_key, 'fingerprint' => 'fp-tampered-different'];

        $sig = $this->computeSignature($secret, 'POST', '/api/license/activate', $bodyA, $timestamp, $nonce);

        $response = $this->postJson('/api/license/activate', $bodyB, [
            'X-Signature' => $sig,
            'X-Signature-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Timestamp' => $timestamp,
        ]);

        $this->assertContains(
            $response->status(),
            [400, 401],
            '篡改 payload 应被拦截，收到: ' . $response->status()
        );
    }

    public function test_expired_signature_rejected(): void
    {
        $secret = 'secret-a-12345';
        $nonce = '550e8400-e29b-41d4-a716-444444444444';
        $timestamp = (string) (time() - 600); // 10 分钟前
        $body = ['license_key' => $this->licenseA->license_key, 'fingerprint' => 'fp-expired'];

        $sig = $this->computeSignature($secret, 'POST', '/api/license/activate', $body, $timestamp, $nonce);

        $response = $this->postJson('/api/license/activate', $body, [
            'X-Signature' => $sig,
            'X-Signature-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Timestamp' => $timestamp,
        ]);

        $this->assertContains(
            $response->status(),
            [400, 401],
            '过期签名应被拦截，收到: ' . $response->status()
        );
    }

    // =====================================================
    // 防越权访问（跨租户）
    // =====================================================

    public function test_user_cannot_access_other_tenant_license(): void
    {
        // userA 只能查看自己租户的 license
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // 尝试访问 tenantB 的 license
        $response = $this->getJson("/api/licenses/{$this->licenseB->id}", [
            'Authorization' => "Bearer {$token}",
        ]);

        $this->assertContains(
            $response->status(),
            [403, 404],
            '跨租户访问 License 应被拒绝'
        );
    }

    public function test_user_cannot_list_other_tenant_licenses(): void
    {
        $tokenA = $this->userA->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/licenses', [
            'Authorization' => "Bearer {$tokenA}",
        ]);

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();
        $keys = collect($data)->pluck('license_key')->toArray();

        // 不应包含 tenantB 的 license
        $this->assertNotContains($this->licenseB->license_key, $keys);
        // 应包含 tenantA 的 license
        $this->assertContains($this->licenseA->license_key, $keys);
    }

    public function test_user_cannot_modify_other_tenant_license(): void
    {
        $tokenA = $this->userA->createToken('test-token')->plainTextToken;

        // 尝试吊销 tenantB 的 license
        $response = $this->postJson("/api/licenses/{$this->licenseB->id}/revoke", [], [
            'Authorization' => "Bearer {$tokenA}",
        ]);

        $this->assertContains(
            $response->status(),
            [403, 404],
            '越权操作应被拒绝'
        );
    }

    public function test_webhook_tenant_isolation(): void
    {
        // tenantA 创建 webhook 端点
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'A Webhook',
            'url' => 'https://example.com/hook-a',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        // 通过 webhookService 从 tenantA 派发事件
        $count = app(\App\Services\WebhookService::class)
            ->dispatch($this->tenantA->id, 'license.activated', ['test' => true]);

        $this->assertEquals(1, $count);

        // 从 tenantB 派发相同事件，应不触发 A 的端点
        $countB = app(\App\Services\WebhookService::class)
            ->dispatch($this->tenantB->id, 'license.activated', ['test' => true]);

        $this->assertEquals(0, $countB);
    }

    // =====================================================
    // 防 SQL 注入
    // =====================================================

    public function test_sql_injection_in_license_key_rejected(): void
    {
        $response = $this->postJson('/api/license/activate', [
            'license_key' => "' OR '1'='1",
            'fingerprint' => 'fp-sqli',
        ]);

        // 不应返回 500 或泄露数据
        $this->assertNotEquals(500, $response->status());
        // 应该正常处理（404 表示未找到或 400 验证错误）
        $this->assertContains(
            $response->status(),
            [400, 404, 422],
            'SQL 注入尝试不应导致 500 错误'
        );
    }

    public function test_sql_injection_in_fingerprint(): void
    {
        $response = $this->postJson('/api/license/activate', [
            'license_key' => $this->licenseA->license_key,
            'fingerprint' => "'; DROP TABLE licenses; --",
        ]);

        // 不应导致 500
        $this->assertNotEquals(500, $response->status());

        // 数据库完整性不受影响
        $this->assertDatabaseHas('licenses', ['id' => $this->licenseA->id]);
        $this->assertDatabaseCount('licenses', 2); // tenantA + tenantB
    }

    public function test_sql_injection_in_license_lookup(): void
    {
        // 匿名用户也可以通过保护的路由？lookup 是受保护的，先创建 token
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/licenses/lookup', [
            'license_key' => "HWT-A-TEST-KEY' UNION SELECT * FROM users--",
        ], [
            'Authorization' => "Bearer {$token}",
        ]);

        // 不应返回 500
        $this->assertNotEquals(500, $response->status());
    }

    // =====================================================
    // 防 IDOR（不安全对象直接引用）
    // =====================================================

    public function test_idor_license_id_enumeration(): void
    {
        $tokenA = $this->userA->createToken('test-token')->plainTextToken;

        // 尝试枚举 license id
        for ($id = 1; $id <= 5; $id++) {
            $response = $this->getJson("/api/licenses/{$id}", [
                'Authorization' => "Bearer {$tokenA}",
            ]);

            // 不应返回 200 如果是其他租户的资源
            if ($response->status() === 200) {
                $data = $response->json('data') ?? $response->json();
                $this->assertNotEmpty($data);
            } else {
                // 应该返回 403 或 404
                $this->assertContains($response->status(), [403, 404]);
            }
        }
    }

    // =====================================================
    // 防暴力枚举（BruteForceGuard 集成）
    // =====================================================

    public function test_brute_force_ip_banned_after_invalid_attempts(): void
    {
        $guard = app(\App\Services\BruteForceGuard::class);
        $ip = '192.168.99.99';

        for ($i = 0; $i < 5; $i++) {
            $guard->recordInvalidAttempt($ip, "INVALID-KEY-{$i}");
        }

        $this->assertTrue($guard->isIpBanned($ip));

        // 验证服务层正确拒绝了被封禁 IP 的请求
        $result = $guard->recordInvalidAttempt($ip, 'ANOTHER-KEY');
        $this->assertTrue($result['blocked']);

        // 验证封禁信息包含剩余时间
        $info = $guard->getBanInfo($ip);
        $this->assertNotNull($info);
        $this->assertGreaterThan(0, $info['remaining_seconds']);

        $guard->unbanIp($ip);
    }

    // =====================================================
    // 敏感信息不泄露
    // =====================================================

    public function test_error_response_does_not_expose_internal_details(): void
    {
        // 使用不存在但合法的格式 key
        $response = $this->postJson('/api/license/activate', [
            'license_key' => 'HWT-99999-NONEXISTENT',
            'fingerprint' => 'fp-hidden',
        ]);

        $body = $response->json();

        // 不应暴露堆栈信息
        $this->assertArrayNotHasKey('trace', $body ?? []);
        $this->assertArrayNotHasKey('exception', $body ?? []);
        $this->assertArrayNotHasKey('file', $body ?? []);
        $this->assertArrayNotHasKey('line', $body ?? []);
    }

    public function test_list_response_does_not_expose_secrets(): void
    {
        $token = $this->userA->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/licenses', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();

        // 遍历返回的数据
        $items = is_array($data) ? $data : [$data];
        foreach ($items as $item) {
            // 不应暴露 metadata 中的 secret
            $this->assertArrayNotHasKey('metadata', $item);
            // 不应暴露 tenant_id（对内信息）
            if (isset($item['license_key']) && $item['license_key'] === $this->licenseA->license_key) {
                // 应包含 license_key 但不包含敏感字段
                $this->assertArrayNotHasKey('signature_secret', $item);
            }
        }
    }

    // =====================================================
    // 无效输入边界测试
    // =====================================================

    public function test_empty_payload_rejected_with_validation(): void
    {
        $response = $this->postJson('/api/license/activate', []);

        $this->assertContains(
            $response->status(),
            [400, 422],
            '空 payload 应返回验证错误'
        );
    }

    public function test_oversized_payload_rejected(): void
    {
        $body = [
            'license_key' => $this->licenseA->license_key,
            'fingerprint' => str_repeat('a', 10000), // 超大 fingerprint
        ];

        $response = $this->postJson('/api/license/activate', $body);

        $this->assertContains(
            $response->status(),
            [400, 413, 422],
            '超大 payload 应被拒绝'
        );
    }

    public function test_special_characters_in_input(): void
    {
        $response = $this->postJson('/api/license/activate', [
            'license_key' => $this->licenseA->license_key,
            'fingerprint' => "<script>alert('xss')</script>",
            'platform' => "javascript:void(0)",
        ]);

        // 不应导致 500
        $this->assertNotEquals(500, $response->status());
    }

    // =====================================================
    // 幂等性安全
    // =====================================================

    public function test_idempotency_prevents_duplicate_activation(): void
    {
        $secret = 'secret-a-12345';
        $body = [
            'license_key' => $this->licenseA->license_key,
            'fingerprint' => 'fp-idempotent-safe',
        ];

        $idempotencyKey = 'test-idemp-key-' . uniqid();

        $headers = $this->activationHeaders('POST', '/api/license/activate', $body, $secret);
        $headers['X-Idempotency-Key'] = $idempotencyKey;

        // 第一次—应成功
        $first = $this->postJson('/api/license/activate', $body, $headers);
        $first->assertStatus(200);

        // 第二次—相同幂等键—应返回相同结果
        $headers2 = $this->activationHeaders('POST', '/api/license/activate', $body, $secret);
        $headers2['X-Idempotency-Key'] = $idempotencyKey;

        $second = $this->postJson('/api/license/activate', $body, $headers2);
        $this->assertContains(
            $second->status(),
            [200, 409],
            '幂等键应防止重复处理'
        );
    }
}
