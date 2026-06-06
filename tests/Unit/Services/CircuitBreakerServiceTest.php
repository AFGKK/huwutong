<?php

namespace Tests\Unit\Services;

use App\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerServiceTest extends TestCase
{
    use RefreshDatabase;

    private CircuitBreakerService $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaker = app(CircuitBreakerService::class);
    }

    // ─── 状态管理 ───

    public function test_initial_state_is_closed(): void
    {
        $state = $this->breaker->getState('license');
        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $state);
    }

    public function test_record_success_resets_failures(): void
    {
        // 先制造几次失败
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure('license');
        }
        $this->assertEquals(3, $this->breaker->getFailureCount('license'));

        // 成功重置
        $this->breaker->recordSuccess('license');
        $this->assertEquals(0, $this->breaker->getFailureCount('license'));
    }

    // ─── 熔断触发 ───

    public function test_failure_threshold_opens_circuit(): void
    {
        $service = 'license';

        // 连续失败达到阈值（默认 5）
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure($service);
        }

        $this->assertEquals(CircuitBreakerService::STATE_OPEN, $this->breaker->getState($service));
        $this->assertFalse($this->breaker->checkCustomService($service));
    }

    public function test_check_custom_service_returns_false_when_open(): void
    {
        $service = 'webhook';

        // 熔断
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure($service);
        }

        $this->assertFalse($this->breaker->checkCustomService($service));
    }

    public function test_check_custom_service_returns_true_when_closed(): void
    {
        $this->assertTrue($this->breaker->checkCustomService('feature_flag'));
    }

    // ─── 半开恢复 ───

    public function test_attempt_reset_transitions_to_half_open_after_timeout(): void
    {
        $service = 'sso';

        // 打开熔断
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure($service);
        }
        $this->assertEquals(CircuitBreakerService::STATE_OPEN, $this->breaker->getState($service));

        // 尝试恢复（但没到恢复时间—应该不行，只能等默认 30s）
        // 模拟时间流逝不可行，但我们可以设置过去的时间
        $reflection = new \ReflectionClass($this->breaker);
        $setState = $reflection->getMethod('setState');

        // 直接检查 attemptReset 逻辑—当 open 且时间过期才允许
        // 这里用 Cache 存过去的时间戳来模拟
        Cache::put('circuit_breaker:state_changed:' . $service, time() - 60, 300);

        $result = $this->breaker->attemptReset($service);
        $this->assertTrue($result);
        $this->assertEquals(CircuitBreakerService::STATE_HALF_OPEN, $this->breaker->getState($service));
    }

    public function test_half_open_success_closes_circuit(): void
    {
        $service = 'payment';

        // 模拟在半开状态
        Cache::put('circuit_breaker:state:' . $service, CircuitBreakerService::STATE_HALF_OPEN, 300);

        // 成功
        $this->breaker->recordSuccess($service);

        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState($service));
    }

    public function test_half_open_failure_reopens_circuit(): void
    {
        $service = 'payment';

        Cache::put('circuit_breaker:state:' . $service, CircuitBreakerService::STATE_HALF_OPEN, 300);

        // 失败
        $this->breaker->recordFailure($service);

        $this->assertEquals(CircuitBreakerService::STATE_OPEN, $this->breaker->getState($service));
    }

    // ─── 半开限流 ───

    public function test_half_open_limits_concurrent_requests(): void
    {
        $service = 'webhook';

        Cache::put('circuit_breaker:state:' . $service, CircuitBreakerService::STATE_HALF_OPEN, 300);

        // 前 3 次允许
        $this->assertTrue($this->breaker->checkCustomService($service));
        $this->breaker->recordHalfOpenRequest($service);
        $this->assertTrue($this->breaker->checkCustomService($service));
        $this->breaker->recordHalfOpenRequest($service);
        $this->assertTrue($this->breaker->checkCustomService($service));
        $this->breaker->recordHalfOpenRequest($service);

        // 第 4 次不允许
        $this->assertFalse($this->breaker->checkCustomService($service));
    }

    // ─── 基础设施检查 ───

    public function test_redis_availability(): void
    {
        // Redis 通常可用，这里不假设失败
        $available = $this->breaker->isRedisAvailable();
        $this->assertIsBool($available);
    }

    public function test_database_availability(): void
    {
        $available = $this->breaker->isDatabaseAvailable();
        $this->assertTrue($available);
    }

    // ─── 获取所有状态 ───

    public function test_get_all_states(): void
    {
        $states = $this->breaker->getAllStates();

        $this->assertArrayHasKey('redis', $states);
        $this->assertArrayHasKey('db', $states);
        $this->assertArrayHasKey('license', $states);
        $this->assertArrayHasKey('webhook', $states);

        foreach ($states as $svc => $info) {
            $this->assertArrayHasKey('state', $info);
            $this->assertArrayHasKey('failures', $info);
            $this->assertArrayHasKey('available', $info);
        }
    }

    // ─── 重置 ───

    public function test_reset_all_clears_all_states(): void
    {
        // 先让一些服务熔断
        for ($i = 0; $i < 6; $i++) {
            $this->breaker->recordFailure('license');
            $this->breaker->recordFailure('webhook');
        }

        $this->assertEquals(CircuitBreakerService::STATE_OPEN, $this->breaker->getState('license'));
        $this->assertEquals(CircuitBreakerService::STATE_OPEN, $this->breaker->getState('webhook'));

        $this->breaker->resetAll();

        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState('license'));
        $this->assertEquals(CircuitBreakerService::STATE_CLOSED, $this->breaker->getState('webhook'));
        $this->assertEquals(0, $this->breaker->getFailureCount('license'));
    }
}
