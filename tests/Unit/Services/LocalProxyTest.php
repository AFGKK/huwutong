<?php

namespace Tests\Unit\Services;

use App\Models\License;
use App\Models\LocalProxyConfig;
use App\Models\LocalProxyNode;
use App\Models\Tenant;
use App\Services\LocalProxyService;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class LocalProxyTest extends TestCase
{
    use RefreshDatabase;

    protected LocalProxyService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LocalProxyService();
        $this->tenant = Tenant::factory()->create();
    }

    protected function registerAndActivateNode(): LocalProxyNode
    {
        $result = $this->service->registerNode($this->tenant->id, [
            'name' => 'Test Proxy',
            'base_url' => 'http://192.168.1.100:8080',
            'capabilities' => ['offline_auth', 'heartbeat', 'crl_sync', 'cache'],
        ]);

        $this->service->activateNode($this->tenant->id, $result['node_id'], $result['register_token']);

        return LocalProxyNode::where('node_id', $result['node_id'])->first();
    }

    /** @test */
    public function registers_a_node()
    {
        $result = $this->service->registerNode($this->tenant->id, [
            'name' => 'My Proxy Node',
            'base_url' => 'http://proxy.internal:8080',
            'version' => '1.0.0',
            'os' => 'Linux',
            'architecture' => 'x86_64',
        ]);

        $this->assertArrayHasKey('node_id', $result);
        $this->assertArrayHasKey('register_token', $result);
        $this->assertArrayHasKey('api_key', $result);
        $this->assertEquals('pending', $result['status']);

        $this->assertDatabaseHas('local_proxy_nodes', [
            'name' => 'My Proxy Node',
            'status' => 'pending',
            'version' => '1.0.0',
        ]);

        // 默认配置应该已创建
        $node = LocalProxyNode::where('node_id', $result['node_id'])->first();
        $this->assertNotNull($node->config()->first());
    }

    /** @test */
    public function activates_a_pending_node()
    {
        $result = $this->service->registerNode($this->tenant->id, [
            'name' => 'To Activate',
        ]);

        $activated = $this->service->activateNode(
            $this->tenant->id, $result['node_id'], $result['register_token']
        );

        $this->assertEquals('active', $activated['status']);
        $this->assertEquals($result['node_id'], $activated['node_id']);

        $this->assertDatabaseHas('local_proxy_nodes', [
            'node_id' => $result['node_id'],
            'status' => 'active',
            'register_token' => null,
        ]);
    }

    /** @test */
    public function processes_heartbeat()
    {
        $node = $this->registerAndActivateNode();

        $response = $this->service->processHeartbeat($node->api_key, [
            'metrics' => ['cpu' => 45, 'memory' => 60, 'disk' => 30],
            'cache_stats' => ['cached_licenses' => 10, 'validated_count' => 50],
            'status' => 'healthy',
        ]);

        $this->assertTrue($response['accepted']);
        $this->assertArrayHasKey('heartbeat_id', $response);
        $this->assertArrayHasKey('next_heartbeat_seconds', $response);

        $this->assertDatabaseHas('local_proxy_heartbeats', [
            'node_id' => $node->id,
            'status' => 'healthy',
        ]);

        $node->refresh();
        $this->assertNotNull($node->last_heartbeat_at);
    }

    /** @test */
    public function proxy_validates_via_cache()
    {
        $node = $this->registerAndActivateNode();
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->addYear(),
        ]);

        // 预热缓存
        $this->service->proxyValidate($node->api_key, $license->license_key);

        // 第二次验证应该命中缓存
        $result = $this->service->proxyValidate($node->api_key, $license->license_key);

        $this->assertTrue($result['valid']);
        $this->assertEquals('cache', $result['source']);

        // 验证日志已记录
        $this->assertDatabaseHas('local_proxy_activation_logs', [
            'node_id' => $node->id,
            'license_key' => $license->license_key,
            'action' => 'validate',
        ]);
    }

    /** @test */
    public function proxy_denies_invalid_license()
    {
        $node = $this->registerAndActivateNode();

        $result = $this->service->proxyValidate($node->api_key, 'INVALID-KEY-XXXXX');

        $this->assertFalse($result['valid']);
        $this->assertEquals('license_not_found', $result['reason']);
    }

    /** @test */
    public function proxy_denies_expired_license()
    {
        $node = $this->registerAndActivateNode();
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $result = $this->service->proxyValidate($node->api_key, $license->license_key);

        $this->assertFalse($result['valid']);
        $this->assertEquals('license_expired', $result['reason']);
    }

    /** @test */
    public function gets_node_config()
    {
        $node = $this->registerAndActivateNode();
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $config = $this->service->getNodeConfig($node->api_key);

        $this->assertArrayHasKey('node', $config);
        $this->assertArrayHasKey('config', $config);
        $this->assertArrayHasKey('licenses', $config);
        $this->assertArrayHasKey('revoked_license_keys', $config);
        $this->assertGreaterThanOrEqual(1, count($config['licenses']));
        $this->assertEquals($node->node_id, $config['node']['id']);
        $this->assertFalse($config['config']['require_cloud_validation']);
    }

    /** @test */
    public function syncs_activation_logs()
    {
        $node = $this->registerAndActivateNode();
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $logs = [
            [
                'license_key' => $license->license_key,
                'action' => 'validate',
                'result' => 'allowed',
                'fingerprint' => 'fp:abc123',
                'client_ip' => '10.0.0.1',
            ],
            [
                'license_key' => 'UNKNOWN-LICENSE',
                'action' => 'validate',
                'result' => 'denied',
                'reason' => 'license_not_found',
                'client_ip' => '10.0.0.2',
            ],
        ];

        $result = $this->service->syncActivationLogs($node->api_key, $logs);

        $this->assertEquals(2, $result['synced_count']);
        $this->assertDatabaseHas('local_proxy_activation_logs', [
            'node_id' => $node->id,
            'license_key' => $license->license_key,
            'result' => 'allowed',
            'client_ip' => '10.0.0.1',
        ]);
    }

    /** @test */
    public function updates_node_status()
    {
        $node = $this->registerAndActivateNode();

        $updated = $this->service->updateNodeStatus($this->tenant->id, $node->id, 'paused');
        $this->assertEquals('paused', $updated->status);

        $updated = $this->service->updateNodeStatus($this->tenant->id, $node->id, 'decommissioned');
        $this->assertEquals('decommissioned', $updated->status);
    }

    /** @test */
    public function updates_node_config()
    {
        $node = $this->registerAndActivateNode();

        $config = $this->service->updateNodeConfig($this->tenant->id, $node->id, [
            'sync_mode' => 'push',
            'cache_ttl_seconds' => 43200,
            'allow_offline_activation' => false,
        ]);

        $this->assertEquals('push', $config->sync_mode);
        $this->assertEquals(43200, $config->cache_ttl_seconds);
        $this->assertFalse($config->allow_offline_activation);
    }

    /** @test */
    public function gets_dashboard_stats()
    {
        $node = $this->registerAndActivateNode();

        // 设置最近心跳
        $this->service->processHeartbeat($node->api_key, ['status' => 'healthy']);

        $stats = $this->service->getDashboardStats($this->tenant->id);

        $this->assertEquals(1, $stats['total_nodes']);
        $this->assertEquals(1, $stats['active_nodes']);
        $this->assertEquals(1, $stats['healthy_nodes']);
        $this->assertEquals(0, $stats['offline_nodes']);
    }

    /** @test */
    public function returns_node_list()
    {
        $this->registerAndActivateNode();

        $nodes = $this->service->getNodes($this->tenant->id);

        $this->assertCount(1, $nodes);
        $this->assertEquals('Test Proxy', $nodes[0]['name']);
        $this->assertEquals('active', $nodes[0]['status']);
        $this->assertArrayHasKey('config', $nodes[0]);
    }

    /** @test */
    public function gets_node_detail()
    {
        $node = $this->registerAndActivateNode();
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        // 预热缓存以产生缓存的 License
        $this->service->proxyValidate($node->api_key, $license->license_key);

        $detail = $this->service->getNodeDetail($this->tenant->id, $node->id);

        $this->assertArrayHasKey('node', $detail);
        $this->assertArrayHasKey('config', $detail);
        $this->assertArrayHasKey('heartbeats', $detail);
        $this->assertArrayHasKey('cached_licenses', $detail);
        $this->assertEquals($node->id, $detail['node']['id']);
    }
}
