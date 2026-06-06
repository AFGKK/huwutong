<?php

namespace Tests\Unit\Services;

use App\Services\BruteForceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BruteForceGuardTest extends TestCase
{
    use RefreshDatabase;

    private BruteForceGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = app(BruteForceGuard::class);
    }

    // ─── 失败计数 ───

    public function test_records_invalid_attempt(): void
    {
        $result = $this->guard->recordInvalidAttempt('192.168.1.1', 'INVALID-KEY');
        $this->assertFalse($result['blocked']);
        $this->assertNull($result['ban_level']);

        $this->assertEquals(1, $this->guard->getFailCount('192.168.1.1'));
    }

    public function test_fail_count_tracks_multiple_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->guard->recordInvalidAttempt('10.0.0.1', "INVALID-{$i}");
        }

        $this->assertEquals(3, $this->guard->getFailCount('10.0.0.1'));
    }

    // ─── 封禁触发 ───

    public function test_default_threshold_triggers_ban(): void
    {
        $ip = '192.168.1.50';

        // 5 次无效尝试
        for ($i = 0; $i < 5; $i++) {
            $result = $this->guard->recordInvalidAttempt($ip, "KEY-{$i}");
        }

        // 第 5 次应该触发封禁
        $this->assertTrue($this->guard->isIpBanned($ip));
        $this->assertGreaterThan(0, $this->guard->getBanRemainingTtl($ip));

        // 后续尝试应被阻断
        $this->assertTrue($this->guard->recordInvalidAttempt($ip, 'ANOTHER')['blocked']);
    }

    public function test_multiple_ban_levels_escalate(): void
    {
        $ip = '10.0.0.100';

        // 5 次 → 短封
        for ($i = 0; $i < 5; $i++) {
            $this->guard->recordInvalidAttempt($ip, "K{$i}");
        }
        $this->assertTrue($this->guard->isIpBanned($ip));

        // 解封后继续
        $this->guard->unbanIp($ip);

        // 10 次 → 中等封禁（30min）
        for ($i = 0; $i < 10; $i++) {
            $this->guard->recordInvalidAttempt($ip, "K{$i}");
        }
        $banLevel = $this->guard->getBanLevel($ip);
        $this->assertNotNull($banLevel);
    }

    // ─── 解封 ───

    public function test_unban_clears_ip(): void
    {
        $ip = '10.0.0.99';

        for ($i = 0; $i < 5; $i++) {
            $this->guard->recordInvalidAttempt($ip, "T{$i}");
        }

        $this->assertTrue($this->guard->isIpBanned($ip));

        $this->guard->unbanIp($ip);

        $this->assertFalse($this->guard->isIpBanned($ip));
        $this->assertEquals(0, $this->guard->getFailCount($ip));
    }

    // ─── 不同 IP 独立计数 ───

    public function test_different_ips_have_independent_counts(): void
    {
        // IP1 触发封禁
        for ($i = 0; $i < 5; $i++) {
            $this->guard->recordInvalidAttempt('10.0.0.1', 'BAD-KEY');
        }
        $this->assertTrue($this->guard->isIpBanned('10.0.0.1'));

        // IP2 未被影响
        $this->assertFalse($this->guard->isIpBanned('10.0.0.2'));
        $this->assertEquals(0, $this->guard->getFailCount('10.0.0.2'));
    }

    // ─── 总失败计数 ───

    public function test_total_fail_count_tracks_globally(): void
    {
        $ip = '10.0.0.50';

        $this->assertEquals(0, $this->guard->getTotalFailCount($ip));

        for ($i = 0; $i < 8; $i++) {
            $this->guard->recordInvalidAttempt($ip, "K{$i}");
        }

        $this->assertEquals(8, $this->guard->getTotalFailCount($ip));
    }

    // ─── 封禁信息 ───

    public function test_get_ban_info_returns_details(): void
    {
        $ip = '10.0.0.200';

        for ($i = 0; $i < 5; $i++) {
            $this->guard->recordInvalidAttempt($ip, "T{$i}");
        }

        $info = $this->guard->getBanInfo($ip);

        $this->assertNotNull($info);
        $this->assertEquals($ip, $info['ip']);
        $this->assertGreaterThan(0, $info['remaining_seconds']);
        $this->assertArrayHasKey('reason', $info);
        $this->assertArrayHasKey('recent_failures', $info);
        $this->assertArrayHasKey('total_failures', $info);
    }

    public function test_get_ban_info_returns_null_for_unbanned_ip(): void
    {
        $this->assertNull($this->guard->getBanInfo('10.0.0.1'));
    }

    // ─── 激活失败区分 ───

    public function test_activation_failure_with_valid_key_does_not_count(): void
    {
        // 有效的 Key 但在数据库中不存在
        $result = $this->guard->recordActivationFailure('10.0.0.1', 'HWT-NONEXISTENT');
        $this->assertFalse($result['blocked']);
    }
}
