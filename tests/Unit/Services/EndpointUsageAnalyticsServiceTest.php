<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\UsageAggregate;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\EndpointUsageAnalyticsService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndpointUsageAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EndpointUsageAnalyticsService $service;
    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EndpointUsageAnalyticsService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
    }

    protected function createUsageRecord(string $metricKey, array $overrides = []): UsageRecord
    {
        $now = now()->toDateTimeString();
        $data = array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'metric_key' => $metricKey,
            'action' => $metricKey,
            'window_type' => 'monthly',
            'quantity' => 1,
            'unit' => 'count',
            'recorded_at' => $now,
        ], $overrides);

        $record = UsageRecord::create($data);

        // 强制刷新以验证
        $record->fresh();

        return $record;
    }

    protected function createUsageAggregate(string $metricKey, string $period, int $quantity): UsageAggregate
    {
        $now = now();
        $periodStart = $period === 'monthly' ? $now->startOfMonth()->toDateString() : $now->toDateString();
        $periodEnd = $period === 'monthly' ? $now->endOfMonth()->toDateString() : $now->toDateString();

        return UsageAggregate::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'metric_key' => $metricKey,
            'period' => $period,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_quantity' => $quantity,
            'record_count' => $quantity,
        ]);
    }

    /** @test */
    public function it_returns_endpoint_overview_with_all_4_endpoints()
    {
        // 创建本月聚合数据
        foreach (['api_call.activate', 'api_call.validate', 'api_call.revoke', 'api_call.check'] as $metricKey) {
            $this->createUsageAggregate($metricKey, 'monthly', 100);
        }

        // 直接使用 DB facade 插入使用记录（避免 cast 问题）
        $now = now()->toDateTimeString();
        DB::table('usage_records')->insert([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'metric_key' => 'api_call.activate',
            'action' => 'api_call.activate',
            'window_type' => 'monthly',
            'quantity' => 5,
            'unit' => 'count',
            'recorded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $overview = $this->service->getEndpointOverview($this->tenant, $this->customer);

        $this->assertCount(4, $overview);
        $this->assertArrayHasKey('api_call.activate', $overview);

        $activate = $overview['api_call.activate'];
        $this->assertEquals('License 激活', $activate['name']);
        $this->assertEquals('POST', $activate['method']);
        $this->assertEquals(100, $activate['this_month_quantity']);
        $this->assertEquals(5, $activate['today_quantity']);
    }

    /** @test */
    public function it_returns_usage_trend()
    {
        // 今天
        $this->createUsageRecord('api_call.activate', ['quantity' => 10]);

        // 昨天
        $this->travelTo(now()->subDay());
        $this->createUsageRecord('api_call.activate', ['quantity' => 5]);
        $this->createUsageRecord('api_call.validate', ['quantity' => 3]);
        $this->travelBack();

        $trend = $this->service->getUsageTrend($this->tenant, $this->customer, 7);

        $this->assertCount(8, $trend); // 7天 + today = 8
        $this->assertArrayHasKey('api_call.activate', $trend[0]);
        $this->assertArrayHasKey('api_call.validate', $trend[0]);
    }

    /** @test */
    public function it_returns_latency_stats()
    {
        // 创建一些带延迟的记录
        foreach ([10, 20, 30, 40, 50, 100, 200] as $latency) {
            $this->createUsageRecord('api_call.activate', [
                'context' => ['latency_ms' => $latency],
                'recorded_at' => now()->subHours(rand(1, 48)),
            ]);
        }

        $latency = $this->service->getLatencyStats($this->tenant, $this->customer, 7);

        $this->assertArrayHasKey('api_call.activate', $latency);
        $this->assertGreaterThan(0, $latency['api_call.activate']['p50']);
        $this->assertGreaterThan(0, $latency['api_call.activate']['p99']);
        $this->assertGreaterThanOrEqual(7, $latency['api_call.activate']['sample_count']);
        $this->assertEquals(200, $latency['api_call.activate']['max']);
    }

    /** @test */
    public function it_returns_error_stats()
    {
        // 成功记录
        for ($i = 0; $i < 10; $i++) {
            $this->createUsageRecord('api_call.activate', [
                'context' => ['is_error' => false],
                'recorded_at' => now()->subHours(rand(1, 48)),
            ]);
        }
        // 错误记录
        for ($i = 0; $i < 2; $i++) {
            $this->createUsageRecord('api_call.activate', [
                'context' => ['is_error' => true, 'error_code' => 'RATE_LIMIT', 'error_message' => '超出频率限制'],
                'recorded_at' => now()->subHours(rand(1, 48)),
            ]);
        }

        $errors = $this->service->getErrorStats($this->tenant, $this->customer, 7);

        $this->assertArrayHasKey('api_call.activate', $errors);
        $this->assertEquals(12, $errors['api_call.activate']['total_requests']);
        $this->assertEquals(2, $errors['api_call.activate']['error_count']);
        $this->assertEquals(16.67, $errors['api_call.activate']['error_rate']);
    }

    /** @test */
    public function it_returns_error_detail()
    {
        $this->createUsageRecord('api_call.activate', [
            'context' => ['is_error' => true, 'error_code' => 'RATE_LIMIT', 'error_message' => '超出频率限制'],
            'recorded_at' => now()->subHours(2),
        ]);
        $this->createUsageRecord('api_call.activate', [
            'context' => ['is_error' => true, 'error_code' => 'RATE_LIMIT', 'error_message' => '超出频率限制'],
            'recorded_at' => now()->subHours(5),
        ]);
        $this->createUsageRecord('api_call.validate', [
            'context' => ['is_error' => true, 'error_code' => 'INVALID_KEY', 'error_message' => '无效 License Key'],
            'recorded_at' => now()->subHours(3),
        ]);

        $detail = $this->service->getErrorDetail($this->tenant, $this->customer, 7);

        $this->assertCount(1, $detail['api_call.activate']);
        $this->assertEquals('RATE_LIMIT', $detail['api_call.activate'][0]['error_code']);
        $this->assertEquals(2, $detail['api_call.activate'][0]['count']);

        $this->assertCount(1, $detail['api_call.validate']);
        $this->assertEquals('INVALID_KEY', $detail['api_call.validate'][0]['error_code']);
    }

    /** @test */
    public function it_returns_alert_data()
    {
        // 上月聚合
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();
        UsageAggregate::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'metric_key' => 'api_call.activate',
            'period' => 'monthly',
            'period_start' => $lastMonthStart->toDateString(),
            'period_end' => $lastMonthEnd->toDateString(),
            'total_quantity' => 100,
            'record_count' => 100,
        ]);

        // 本月聚合（激增至 300）
        $this->createUsageAggregate('api_call.activate', 'monthly', 300);

        $alerts = $this->service->getAlertData($this->tenant, $this->customer);

        $activateAlert = collect($alerts)->firstWhere('metric_key', 'api_call.activate');
        $this->assertNotNull($activateAlert);
        $this->assertEquals('warning', $activateAlert['level']);
        $this->assertEquals(200, $activateAlert['change_percent']); // (300-100)/100 = 200%
    }

    /** @test */
    public function endpoint_metrics_constant_is_defined()
    {
        $metrics = EndpointUsageAnalyticsService::ENDPOINT_METRICS;

        $this->assertCount(4, $metrics);
        $this->assertArrayHasKey('api_call.activate', $metrics);
        $this->assertArrayHasKey('api_call.validate', $metrics);
        $this->assertArrayHasKey('api_call.revoke', $metrics);
        $this->assertArrayHasKey('api_call.check', $metrics);

        $this->assertEquals('License 激活', $metrics['api_call.activate']['name']);
        $this->assertEquals('POST', $metrics['api_call.activate']['method']);
    }

    /** @test */
    public function it_respects_tenant_isolation()
    {
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        $customer2 = Customer::factory()->create(['tenant_id' => $tenant2->id, 'user_id' => $user2->id]);

        $this->createUsageAggregate('api_call.activate', 'monthly', 100);
        UsageAggregate::create([
            'tenant_id' => $tenant2->id,
            'customer_id' => $customer2->id,
            'metric_key' => 'api_call.activate',
            'period' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total_quantity' => 999,
            'record_count' => 999,
        ]);

        $overview1 = $this->service->getEndpointOverview($this->tenant, $this->customer);
        $this->assertEquals(100, $overview1['api_call.activate']['this_month_quantity']);

        $overview2 = $this->service->getEndpointOverview($tenant2, $customer2);
        $this->assertEquals(999, $overview2['api_call.activate']['this_month_quantity']);
    }

    /** @test */
    public function it_handles_empty_data_gracefully()
    {
        $overview = $this->service->getEndpointOverview($this->tenant, $this->customer);
        $this->assertCount(4, $overview);

        foreach ($overview as $ep) {
            $this->assertEquals(0, $ep['total_quantity']);
            $this->assertEquals(0, $ep['today_quantity']);
            $this->assertEquals(0, $ep['this_month_quantity']);
        }

        $trend = $this->service->getUsageTrend($this->tenant, $this->customer, 7);
        $this->assertCount(8, $trend);

        $latency = $this->service->getLatencyStats($this->tenant, $this->customer, 7);
        foreach ($latency as $stat) {
            $this->assertEquals(0, $stat['p50']);
            $this->assertEquals(0, $stat['sample_count']);
        }

        $errors = $this->service->getErrorStats($this->tenant, $this->customer, 7);
        foreach ($errors as $err) {
            $this->assertEquals(0, $err['total_requests']);
        }

        $alerts = $this->service->getAlertData($this->tenant, $this->customer);
        foreach ($alerts as $alert) {
            $this->assertEquals('normal', $alert['level']);
        }
    }
}
