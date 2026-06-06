<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── /health/live 存活探针 ───

    public function test_live_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'hwt-api',
        ]);
        $response->assertJsonStructure(['timestamp']);
    }

    // ─── /health/ready 就绪探针 ───

    public function test_ready_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health/ready');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
        ]);
        $response->assertJsonStructure([
            'checks' => [
                'database' => ['healthy'],
                'redis' => ['healthy'],
            ],
            'timestamp',
        ]);

        // 数据库应可用
        $json = $response->json();
        $this->assertTrue($json['checks']['database']['healthy']);
    }

    // ─── /health/status 详细状态 ───

    public function test_status_endpoint_returns_detailed_health(): void
    {
        $response = $this->getJson('/api/health/status');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'hwt-api',
        ]);

        $json = $response->json();

        // 检查结构完整性
        $this->assertArrayHasKey('version', $json);
        $this->assertArrayHasKey('environment', $json);
        $this->assertArrayHasKey('uptime', $json);
        $this->assertArrayHasKey('php', $json);
        $this->assertArrayHasKey('circuit_breakers', $json);

        // PHP 信息
        $this->assertArrayHasKey('version', $json['php']);
        $this->assertArrayHasKey('memory_mb', $json['php']);

        // 熔断器状态
        $this->assertArrayHasKey('redis', $json['circuit_breakers']);
        $this->assertArrayHasKey('db', $json['circuit_breakers']);

        // 数据库和 Redis 应健康
        $this->assertTrue($json['checks']['database']['healthy']);
        $this->assertTrue($json['checks']['redis']['healthy']);
    }

    public function test_status_includes_latency(): void
    {
        $response = $this->getJson('/api/health/status');

        $json = $response->json();

        $this->assertArrayHasKey('latency_ms', $json['checks']['database']);
        $this->assertGreaterThan(0, $json['checks']['database']['latency_ms']);
    }
}
