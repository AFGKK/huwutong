<?php

namespace Tests\Unit\Services;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FineGrainedApiKeyService;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FineGrainedApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FineGrainedApiKeyService $service;
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FineGrainedApiKeyService::class);
        $this->tenant = Tenant::factory()->create(['name' => '测试租户']);
        $this->user = User::factory()->create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'tenant_id' => $this->tenant->id,
        ]);

        // 确保 ApiKey 表迁移正常
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
    }

    protected function createApiKey(array $overrides = []): ApiKey
    {
        return ApiKey::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'key_id' => 'ak_test_' . \Illuminate\Support\Str::random(16),
            'name' => '测试密钥',
            'secret' => Hash::make('test-secret-value'),
            'permissions' => 'read-write',
            'tier' => 'standard',
            'is_active' => true,
        ], $overrides));
    }

    // ─── SDK 端点元数据 ─────────────────────────────────

    /** @test */
    public function it_returns_sdk_endpoints()
    {
        $endpoints = $this->service->getSdkEndpoints();

        $this->assertCount(4, $endpoints);
        $this->assertEquals('activate', $endpoints[0]['endpoint']);
        $this->assertEquals(['POST'], $endpoints[0]['methods']);
        $this->assertEquals('validate', $endpoints[1]['endpoint']);
        $this->assertEquals(['GET'], $endpoints[1]['methods']);
        $this->assertEquals('revoke', $endpoints[2]['endpoint']);
        $this->assertEquals(['POST'], $endpoints[2]['methods']);
        $this->assertEquals('check', $endpoints[3]['endpoint']);
        $this->assertEquals(['GET'], $endpoints[3]['methods']);
    }

    // ─── 端点权限检查 ─────────────────────────────────

    /** @test */
    public function it_allows_endpoint_access_when_no_restrictions_set()
    {
        $apiKey = $this->createApiKey();

        $result = $this->service->checkEndpointAccess($apiKey, 'activate', 'POST');

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    /** @test */
    public function it_denies_access_for_disabled_key()
    {
        $apiKey = $this->createApiKey(['is_active' => false]);

        $result = $this->service->checkEndpointAccess($apiKey, 'activate', 'POST');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('禁用', $result['reason']);
    }

    /** @test */
    public function it_denies_access_for_expired_key()
    {
        $apiKey = $this->createApiKey([
            'expires_at' => now()->subHour(),
        ]);

        $result = $this->service->checkEndpointAccess($apiKey, 'activate', 'POST');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('过期', $result['reason']);
    }

    /** @test */
    public function it_allows_access_for_non_expired_key()
    {
        $apiKey = $this->createApiKey([
            'expires_at' => now()->addDays(30),
        ]);

        $result = $this->service->checkEndpointAccess($apiKey, 'activate', 'POST');

        $this->assertTrue($result['allowed']);
    }

    // ─── 细粒度 endpoint_permissions ─────────────────

    /** @test */
    public function it_respects_endpoint_permissions()
    {
        $apiKey = $this->createApiKey([
            'endpoint_permissions' => [
                'activate' => ['POST'],
                'validate' => ['GET'],
            ],
        ]);

        // 允许的端点+方法
        $this->assertTrue($this->service->checkEndpointAccess($apiKey, 'activate', 'POST')['allowed']);
        $this->assertTrue($this->service->checkEndpointAccess($apiKey, 'validate', 'GET')['allowed']);

        // 不允许的方法
        $result = $this->service->checkEndpointAccess($apiKey, 'activate', 'GET');
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('不允许', $result['reason']);

        // 未配置的端点
        $result = $this->service->checkEndpointAccess($apiKey, 'revoke', 'POST');
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('无权访问', $result['reason']);
    }

    /** @test */
    public function it_allows_all_methods_when_endpoint_permissions_list_all()
    {
        $apiKey = $this->createApiKey([
            'endpoint_permissions' => [
                'activate' => ['GET', 'POST', 'PUT', 'DELETE'],
            ],
        ]);

        $this->assertTrue($this->service->checkEndpointAccess($apiKey, 'activate', 'GET')['allowed']);
        $this->assertTrue($this->service->checkEndpointAccess($apiKey, 'activate', 'POST')['allowed']);
        $this->assertTrue($this->service->checkEndpointAccess($apiKey, 'activate', 'DELETE')['allowed']);
    }

    /** @test */
    public function it_falls_back_to_allowed_endpoints_when_no_endpoint_permissions()
    {
        $apiKey = $this->createApiKey([
            'allowed_endpoints' => ['activate', 'validate'],
            'endpoint_permissions' => null,
        ]);

        $this->assertTrue($this->service->checkEndpointAccess($apiKey, 'activate', 'POST')['allowed']);

        // 不在 allowed_endpoints 中的端点
        $result = $this->service->checkEndpointAccess($apiKey, 'revoke', 'POST');
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('无权访问', $result['reason']);
    }

    // ─── 获取 Key 端点权限配置 ───────────────────────

    /** @test */
    public function it_returns_key_endpoint_permissions_list()
    {
        $apiKey = $this->createApiKey([
            'endpoint_permissions' => [
                'activate' => ['POST'],
                'validate' => ['GET'],
            ],
        ]);

        $permissions = $this->service->getKeyEndpointPermissions($apiKey);

        $this->assertCount(4, $permissions);

        $activate = collect($permissions)->firstWhere('endpoint', 'activate');
        $this->assertNotNull($activate);
        $this->assertTrue($activate['allowed']);
        $this->assertEquals(['POST'], $activate['allowed_methods']);

        $revoke = collect($permissions)->firstWhere('endpoint', 'revoke');
        $this->assertNotNull($revoke);
        $this->assertFalse($revoke['allowed']);
        $this->assertEmpty($revoke['allowed_methods']);
    }

    // ─── 更新端点权限 ───────────────────────────────

    /** @test */
    public function it_updates_endpoint_permissions()
    {
        $apiKey = $this->createApiKey();

        $result = $this->service->updateEndpointPermissions($apiKey, [
            'activate' => ['POST'],
            'validate' => ['GET'],
        ]);

        $this->assertTrue($result['success']);
        $apiKey->refresh();

        $this->assertEquals([
            'activate' => ['POST'],
            'validate' => ['GET'],
        ], $apiKey->endpoint_permissions);
    }

    /** @test */
    public function it_validates_endpoint_names_on_update()
    {
        $apiKey = $this->createApiKey();

        $result = $this->service->updateEndpointPermissions($apiKey, [
            'nonexistent_endpoint' => ['POST'],
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('nonexistent_endpoint', $result['errors']);
    }

    /** @test */
    public function it_clears_endpoint_permissions_when_empty()
    {
        $apiKey = $this->createApiKey([
            'endpoint_permissions' => ['activate' => ['POST']],
        ]);

        $this->service->updateEndpointPermissions($apiKey, []);
        $apiKey->refresh();

        $this->assertNull($apiKey->endpoint_permissions);
    }

    // ─── 过期状态 ───────────────────────────────────

    /** @test */
    public function it_returns_expiry_status()
    {
        $apiKey = $this->createApiKey(['expires_at' => now()->addDays(5)]);

        $status = $this->service->getExpiryStatus($apiKey);

        $this->assertFalse($status['expired']);
        $this->assertNotNull($status['expires_at']);
        $this->assertGreaterThan(0, $status['remaining_hours']);
    }

    /** @test */
    public function it_returns_expired_status()
    {
        $apiKey = $this->createApiKey(['expires_at' => now()->subDays(1)]);

        $status = $this->service->getExpiryStatus($apiKey);

        $this->assertTrue($status['expired']);
        $this->assertEquals(0, $status['remaining_hours']);
    }

    /** @test */
    public function it_returns_null_expiry_for_non_expiring_key()
    {
        $apiKey = $this->createApiKey(['expires_at' => null]);

        $status = $this->service->getExpiryStatus($apiKey);

        $this->assertFalse($status['expired']);
        $this->assertNull($status['expires_at']);
        $this->assertNull($status['remaining_hours']);
    }

    // ─── 用量配额 ───────────────────────────────────

    /** @test */
    public function it_returns_usage_percentage()
    {
        $apiKey = $this->createApiKey([
            'usage_quota' => 1000,
            'usage_count' => 250,
        ]);

        $percent = $this->service->getUsagePercentage($apiKey);

        $this->assertEquals(25.0, $percent);
    }

    /** @test */
    public function it_returns_null_usage_percentage_when_no_quota()
    {
        $apiKey = $this->createApiKey(['usage_quota' => null]);

        $percent = $this->service->getUsagePercentage($apiKey);

        $this->assertNull($percent);
    }

    /** @test */
    public function it_returns_quota_snapshot()
    {
        $apiKey = $this->createApiKey([
            'usage_quota' => 5000,
            'usage_count' => 1000,
            'daily_quota' => 500,
            'daily_usage' => 100,
        ]);

        $snapshot = $this->service->getQuotaSnapshot($apiKey);

        $this->assertEquals(1000, $snapshot['usage_count']);
        $this->assertEquals(5000, $snapshot['usage_quota']);
        $this->assertEquals(20.0, $snapshot['usage_percent']);
        $this->assertEquals(100, $snapshot['daily_usage']);
        $this->assertEquals(500, $snapshot['daily_quota']);
        $this->assertEquals(20.0, $snapshot['daily_usage_percent']);
    }

    /** @test */
    public function it_checks_quota_availability()
    {
        $apiKey = $this->createApiKey([
            'usage_quota' => 100,
            'usage_count' => 100,
        ]);

        $result = $this->service->checkQuota($apiKey);

        $this->assertFalse($result['allowed']);
        $this->assertCount(1, $result['exceeded']);
    }

    /** @test */
    public function it_checks_daily_quota()
    {
        $apiKey = $this->createApiKey([
            'usage_quota' => 10000,
            'usage_count' => 100,
            'daily_quota' => 200,
            'daily_usage' => 200,
            'daily_reset_at' => now()->addDay(), // 避免重置
        ]);

        $result = $this->service->checkQuota($apiKey);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('每日配额', $result['exceeded'][0]);
    }

    // ─── IP 检查 ─────────────────────────────────────

    /** @test */
    public function it_checks_ip_access()
    {
        $apiKey = $this->createApiKey([
            'allowed_ips' => ['192.168.1.1', '10.0.0.1'],
        ]);

        $this->assertTrue($this->service->checkIpAccess($apiKey, '192.168.1.1'));
        $this->assertTrue($this->service->checkIpAccess($apiKey, '10.0.0.1'));
        $this->assertFalse($this->service->checkIpAccess($apiKey, '192.168.1.100'));
    }

    /** @test */
    public function it_allows_all_ips_when_no_restriction()
    {
        $apiKey = $this->createApiKey(['allowed_ips' => null]);

        $this->assertTrue($this->service->checkIpAccess($apiKey, '192.168.1.1'));
        $this->assertTrue($this->service->checkIpAccess($apiKey, '10.0.0.1'));
    }
}
