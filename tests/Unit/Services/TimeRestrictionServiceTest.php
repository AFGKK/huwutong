<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TimeRestrictionConfig;
use App\Models\TimeRestrictionLog;
use App\Services\TimeRestrictionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TimeRestrictionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TimeRestrictionService $service;
    protected Tenant $tenant;
    protected License $license;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(TimeRestrictionService::class);
        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);
    }

    // ─── 基础检查 ───

    public function test_returns_allowed_when_no_config()
    {
        $result = $this->service->check($this->license);
        $this->assertTrue($result['allowed']);
        $this->assertEquals('未配置时段限制', $result['reason']);
    }

    public function test_returns_allowed_when_config_inactive()
    {
        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => false,
        ]);

        $result = $this->service->check($this->license);
        $this->assertTrue($result['allowed']);
    }

    // ─── 每周排期 ───

    protected function setDayAndTime(string $dayName, string $time): Carbon
    {
        // Use a known reference date to create deterministic test dates
        // 2026-06-08 is a Monday
        $reference = Carbon::parse('2026-06-08 00:00:00', 'UTC');
        $days = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
                 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
        $targetDay = $days[$dayName] ?? 1;
        $refDay = (int) $reference->format('w');
        $diff = $targetDay - $refDay;
        $date = $reference->copy()->addDays($diff);
        [$h, $m] = explode(':', $time);
        $date->setTime((int) $h, (int) $m);
        Carbon::setTestNow($date);
        return $date;
    }

    public function test_allows_within_weekly_schedule()
    {
        $this->setDayAndTime('Monday', '10:00');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'], // 周一
            ],
        ]);

        $result = $this->service->check($this->license);
        $this->assertTrue($result['allowed']);
        $this->assertEquals('在可用时段内', $result['reason']);

        Carbon::setTestNow();
    }

    public function test_denies_outside_weekly_schedule()
    {
        $this->setDayAndTime('Monday', '20:00');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);

        $result = $this->service->check($this->license);
        $this->assertFalse($result['allowed']);

        Carbon::setTestNow();
    }

    public function test_denies_when_no_schedule_for_day()
    {
        // 周日（day=0）无排期
        $this->setDayAndTime('Sunday', '12:00');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'], // 仅周一
            ],
        ]);

        $result = $this->service->check($this->license);
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('无可用时段', $result['reason']);

        Carbon::setTestNow();
    }

    // ─── 特定期日 ───

    public function test_special_schedule_overrides_weekly()
    {
        $now = $this->setDayAndTime('Monday', '10:00');
        $today = $now->format('Y-m-d');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
            'special_schedule' => [
                ['date' => $today, 'start' => '14:00', 'end' => '16:00'], // 仅14-16点可用
            ],
        ]);

        $result = $this->service->check($this->license);
        $this->assertFalse($result['allowed'], '10:00不在特殊时段14-16内，应拒绝');
        $this->assertStringContainsString('特殊时段', $result['reason']);

        Carbon::setTestNow();
    }

    // ─── 节假日 ───

    public function test_denies_on_holiday()
    {
        $now = $this->setDayAndTime('Monday', '10:00');
        $today = $now->format('Y-m-d');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
            'holidays' => [$today],
        ]);

        $result = $this->service->check($this->license);
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('节假日', $result['reason']);

        Carbon::setTestNow();
    }

    // ─── 宽限机制 ───

    public function test_grace_period_allows_use()
    {
        $this->setDayAndTime('Monday', '18:05');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
            'out_of_hours_action' => 'grace',
            'grace_minutes' => 10,
        ]);

        $result = $this->service->check($this->license);
        $this->assertTrue($result['allowed']);
        $this->assertEquals('宽限期内允许使用', $result['reason']);
        $this->assertArrayHasKey('grace_until', $result);

        Carbon::setTestNow();
    }

    public function test_grace_period_expired()
    {
        $this->setDayAndTime('Monday', '18:15');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
            'out_of_hours_action' => 'grace',
            'grace_minutes' => 10,
        ]);

        $result = $this->service->check($this->license);
        $this->assertFalse($result['allowed']);

        Carbon::setTestNow();
    }

    // ─── IP 白名单 ───

    public function test_ip_whitelist_bypasses_restriction()
    {
        $this->setDayAndTime('Monday', '20:00');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
            'allowed_ip_ranges' => '192.168.1.100',
        ]);

        // 不在白名单的 IP
        $result = $this->service->check($this->license, '10.0.0.1');
        $this->assertFalse($result['allowed']);

        // 在白名单的 IP
        $result = $this->service->check($this->license, '192.168.1.100');
        $this->assertTrue($result['allowed']);
        $this->assertEquals('IP 白名单例外', $result['reason']);

        Carbon::setTestNow();
    }

    public function test_cidr_whitelist()
    {
        $this->setDayAndTime('Monday', '20:00');

        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
            'allowed_ip_ranges' => '10.0.0.0/24',
        ]);

        $result = $this->service->check($this->license, '10.0.0.55');
        $this->assertTrue($result['allowed']);

        $result = $this->service->check($this->license, '10.0.1.1');
        $this->assertFalse($result['allowed']);

        Carbon::setTestNow();
    }

    // ─── 产品级配置继承 ───

    public function test_falls_back_to_product_config()
    {
        $this->setDayAndTime('Monday', '10:00');

        // 仅在 License 没有配置时，使用产品级配置
        TimeRestrictionConfig::create([
            'restrictable_type' => get_class($this->product),
            'restrictable_id' => $this->product->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);

        $result = $this->service->check($this->license);
        $this->assertTrue($result['allowed']);

        Carbon::setTestNow();
    }

    // ─── 日志 ───

    public function test_creates_log_on_deny()
    {
        $this->setDayAndTime('Monday', '20:00');

        $config = TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);

        $this->service->check($this->license, '10.0.0.1');

        $this->assertDatabaseHas('time_restriction_logs', [
            'config_id' => $config->id,
            'license_id' => $this->license->id,
            'result' => 'denied',
        ]);

        Carbon::setTestNow();
    }

    // ─── 配置摘要 ───

    public function test_get_config_summary()
    {
        $config = TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'Asia/Shanghai',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
            'special_schedule' => [
                ['date' => '2026-12-25', 'start' => '10:00', 'end' => '16:00'],
            ],
            'holidays' => ['2026-01-01', '2026-10-01'],
            'out_of_hours_action' => 'grace',
            'grace_minutes' => 15,
            'allowed_ip_ranges' => '10.0.0.0/8',
        ]);

        $summary = $this->service->getConfigSummary($config);

        $this->assertTrue($summary['enabled']);
        $this->assertEquals('Asia/Shanghai', $summary['timezone']);
        $this->assertCount(1, $summary['weekly_schedule']);
        $this->assertEquals(1, $summary['special_dates']);
        $this->assertEquals(2, $summary['holiday_count']);
        $this->assertEquals('grace', $summary['out_of_hours_action']);
        $this->assertEquals(15, $summary['grace_minutes']);
        $this->assertTrue($summary['has_ip_whitelist']);
    }

    public function test_config_summary_when_disabled()
    {
        $summary = $this->service->getConfigSummary(null);
        $this->assertFalse($summary['enabled']);
        $this->assertEquals('未启用时段限制', $summary['summary']);
    }
}
