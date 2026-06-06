<?php

namespace Tests\Unit\Services;

use App\Services\EnhancedRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnhancedRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    private EnhancedRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = app(EnhancedRateLimiter::class);
    }

    // ─── IP 限流 ───

    public function test_ip_rate_limit_allows_within_limit(): void
    {
        $request = Request::create('/api/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

        $result = $this->limiter->check($request, [
            ['key_type' => 'ip', 'max_attempts' => 10, 'window_seconds' => 60],
        ]);

        $this->assertTrue($result['allowed']);
        $this->assertArrayHasKey('X-RateLimit-Remaining-ip', $result['headers']);
    }

    public function test_ip_rate_limit_exceeds_limit(): void
    {
        $request = Request::create('/api/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.2']);

        // 消耗 10 次
        for ($i = 0; $i < 10; $i++) {
            $this->limiter->check($request, [
                ['key_type' => 'ip', 'max_attempts' => 10, 'window_seconds' => 60],
            ]);
        }

        // 第 11 次应该被限流
        $result = $this->limiter->check($request, [
            ['key_type' => 'ip', 'max_attempts' => 10, 'window_seconds' => 60],
        ]);

        $this->assertFalse($result['allowed']);
        $this->assertGreaterThan(0, $result['retry_after']);
    }

    public function test_different_ips_have_independent_limits(): void
    {
        $request1 = Request::create('/api/test', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $request2 = Request::create('/api/test', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.2']);

        // IP1 消耗到上限
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check($request1, [
                ['key_type' => 'ip', 'max_attempts' => 5, 'window_seconds' => 60],
            ]);
        }

        // IP1 应该被限流
        $result1 = $this->limiter->check($request1, [
            ['key_type' => 'ip', 'max_attempts' => 5, 'window_seconds' => 60],
        ]);
        $this->assertFalse($result1['allowed']);

        // IP2 仍然可用
        $result2 = $this->limiter->check($request2, [
            ['key_type' => 'ip', 'max_attempts' => 5, 'window_seconds' => 60],
        ]);
        $this->assertTrue($result2['allowed']);
    }

    // ─── 产品限流 ───

    public function test_product_rate_limit_from_license_key(): void
    {
        $request = Request::create('/api/activate', 'POST', [
            'license_key' => 'HWT-42-ABCDEF',
        ], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

        $result = $this->limiter->check($request, [
            ['key_type' => 'product', 'max_attempts' => 100, 'window_seconds' => 3600],
        ]);

        $this->assertTrue($result['allowed']);
        $this->assertArrayHasKey('X-RateLimit-Remaining-product', $result['headers']);
    }

    public function test_product_rate_limit_exceeds(): void
    {
        $request = Request::create('/api/activate', 'POST', [
            'license_key' => 'HWT-99-TEST',
        ], [], [], ['REMOTE_ADDR' => '10.0.0.2']);

        for ($i = 0; $i < 3; $i++) {
            $this->limiter->check($request, [
                ['key_type' => 'product', 'max_attempts' => 3, 'window_seconds' => 3600],
            ]);
        }

        $result = $this->limiter->check($request, [
            ['key_type' => 'product', 'max_attempts' => 3, 'window_seconds' => 3600],
        ]);

        $this->assertFalse($result['allowed']);
    }

    // ─── 租户限流 ───

    public function test_tenant_rate_limit_from_header(): void
    {
        $request = Request::create('/api/admin/licenses', 'GET');
        $request->headers->set('X-Tenant-Id', '42');

        $result = $this->limiter->check($request, [
            ['key_type' => 'tenant', 'max_attempts' => 1000, 'window_seconds' => 60],
        ]);

        $this->assertTrue($result['allowed']);
    }

    // ─── API 路径限流 ───

    public function test_api_path_rate_limit(): void
    {
        $request = Request::create('/api/admin/users', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check($request, [
                ['key_type' => 'api', 'max_attempts' => 5, 'window_seconds' => 60],
            ]);
        }

        $result = $this->limiter->check($request, [
            ['key_type' => 'api', 'max_attempts' => 5, 'window_seconds' => 60],
        ]);

        $this->assertFalse($result['allowed']);
    }

    public function test_different_paths_have_independent_api_limits(): void
    {
        $request1 = Request::create('/api/users', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $request2 = Request::create('/api/licenses', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check($request1, [
                ['key_type' => 'api', 'max_attempts' => 5, 'window_seconds' => 60],
            ]);
        }

        $result1 = $this->limiter->check($request1, [
            ['key_type' => 'api', 'max_attempts' => 5, 'window_seconds' => 60],
        ]);
        $this->assertFalse($result1['allowed']);

        $result2 = $this->limiter->check($request2, [
            ['key_type' => 'api', 'max_attempts' => 5, 'window_seconds' => 60],
        ]);
        $this->assertTrue($result2['allowed']);
    }

    // ─── 多级限流组合 ───

    public function test_multi_level_rate_limit_all_pass(): void
    {
        $request = Request::create('/api/activate', 'POST', [
            'license_key' => 'HWT-1-TEST',
        ], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

        $result = $this->limiter->check($request, [
            ['key_type' => 'ip', 'max_attempts' => 10, 'window_seconds' => 60],
            ['key_type' => 'product', 'max_attempts' => 100, 'window_seconds' => 3600],
            ['key_type' => 'license', 'max_attempts' => 5, 'window_seconds' => 60],
        ]);

        $this->assertTrue($result['allowed']);
        $this->assertArrayHasKey('X-RateLimit-Remaining-ip', $result['headers']);
        $this->assertArrayHasKey('X-RateLimit-Remaining-product', $result['headers']);
        $this->assertArrayHasKey('X-RateLimit-Remaining-license', $result['headers']);
    }

    public function test_multi_level_any_fail_blocks_all(): void
    {
        $request = Request::create('/api/activate', 'POST', [
            'license_key' => 'HWT-1-TEST',
        ], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

        // 消耗 product 上限
        for ($i = 0; $i < 2; $i++) {
            $this->limiter->check($request, [
                ['key_type' => 'ip', 'max_attempts' => 10, 'window_seconds' => 60],
                ['key_type' => 'product', 'max_attempts' => 2, 'window_seconds' => 3600],
            ]);
        }

        // product 已超限
        $result = $this->limiter->check($request, [
            ['key_type' => 'ip', 'max_attempts' => 10, 'window_seconds' => 60],
            ['key_type' => 'product', 'max_attempts' => 2, 'window_seconds' => 3600],
        ]);

        $this->assertFalse($result['allowed']);
        // 应该返回触发限流的规则
        $this->assertEquals('product', $result['rule']['key_type']);
    }

    // ─── 默认规则集 ───

    public function test_default_rules_for_activate(): void
    {
        $rules = EnhancedRateLimiter::getDefaultRules('activate');
        $this->assertCount(3, $rules);
        $this->assertEquals('ip', $rules[0]['key_type']);
        $this->assertEquals(30, $rules[0]['max_attempts']);
        $this->assertEquals('product', $rules[1]['key_type']);
        $this->assertEquals('license', $rules[2]['key_type']);
    }

    public function test_reset_clears_counter(): void
    {
        $request = Request::create('/api/test', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.99']);

        $key = 'ratelimit_v2:ip:10.0.0.99';

        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check($request, [
                ['key_type' => 'ip', 'max_attempts' => 5, 'window_seconds' => 60],
            ]);
        }

        $this->assertEquals(5, $this->limiter->getCurrentCount($key));

        $this->limiter->reset($key);

        $this->assertEquals(0, $this->limiter->getCurrentCount($key));
    }
}
