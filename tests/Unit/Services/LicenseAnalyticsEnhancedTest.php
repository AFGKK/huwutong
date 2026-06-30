<?php

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\LicenseAnalyticsEvent;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\LicenseAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseAnalyticsEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected LicenseAnalyticsService $service;
    protected Tenant $tenant;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LicenseAnalyticsService();
        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
    }

    /** @test */
    public function gets_summary()
    {
        License::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);

        $active = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        Device::factory()->create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $active->id,
        ]);

        $summary = $this->service->getSummary($this->tenant->id);

        $this->assertEquals(4, $summary['total_licenses']);
        $this->assertEquals(1, $summary['active_licenses']);
        $this->assertGreaterThan(0, $summary['activation_rate']);
        $this->assertEquals(1, $summary['total_devices']);
    }

    /** @test */
    public function gets_license_type_distribution()
    {
        License::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'enterprise']);
        License::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'enterprise']);
        License::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'professional']);
        License::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'standard']);

        $distribution = $this->service->getLicenseTypeDistribution($this->tenant->id);

        $this->assertCount(3, $distribution); // 3 types
        $enterprise = collect($distribution)->firstWhere('type', 'enterprise');
        $this->assertEquals(2, $enterprise['count']);
    }

    /** @test */
    public function gets_license_status_distribution()
    {
        License::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        License::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        License::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'expired']);
        License::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'pending']);

        $distribution = $this->service->getLicenseStatusDistribution($this->tenant->id);

        $active = collect($distribution)->firstWhere('status', 'active');
        $this->assertEquals(2, $active['count']);
    }

    /** @test */
    public function gets_device_platform_distribution()
    {
        $license = License::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        Device::factory()->create(['tenant_id' => $this->tenant->id, 'license_id' => $license->id, 'platform' => 'windows']);
        Device::factory()->create(['tenant_id' => $this->tenant->id, 'license_id' => $license->id, 'platform' => 'windows']);
        Device::factory()->create(['tenant_id' => $this->tenant->id, 'license_id' => $license->id, 'platform' => 'linux']);

        $distribution = $this->service->getDevicePlatformDistribution($this->tenant->id);

        $windows = collect($distribution)->firstWhere('platform', 'windows');
        $this->assertEquals(2, $windows['count']);
    }

    /** @test */
    public function gets_license_creation_trend()
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => Carbon::now()->subDays(2),
        ]);
        License::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => Carbon::now()->subDay(),
        ]);
        License::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => Carbon::now(),
        ]);

        $trend = $this->service->getLicenseCreationTrend(7, $this->tenant->id);

        $this->assertCount(7, $trend);
        $dayMinus2 = collect($trend)->firstWhere('date', '2026-05-30');
        $this->assertEquals(1, $dayMinus2['count']);
        $dayMinus1 = collect($trend)->firstWhere('date', '2026-05-31');
        $this->assertEquals(3, $dayMinus1['count']);
        $today = collect($trend)->firstWhere('date', '2026-06-01');
        $this->assertEquals(2, $today['count']);

        Carbon::setTestNow();
    }

    /** @test */
    public function gets_license_dashboard()
    {
        License::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'enterprise', 'status' => 'active']);
        License::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'professional', 'status' => 'expired']);

        $dashboard = $this->service->getLicenseDashboard($this->tenant->id);

        $this->assertArrayHasKey('summary', $dashboard);
        $this->assertArrayHasKey('type_distribution', $dashboard);
        $this->assertArrayHasKey('status_distribution', $dashboard);
        $this->assertArrayHasKey('platform_distribution', $dashboard);
        $this->assertArrayHasKey('activation_trend', $dashboard);
        $this->assertArrayHasKey('license_creation_trend', $dashboard);
        $this->assertArrayHasKey('geo_distribution', $dashboard);
        $this->assertArrayHasKey('utilization', $dashboard);
        $this->assertArrayHasKey('violations_by_type', $dashboard);

        $this->assertEquals(2, $dashboard['summary']['total_licenses']);
        $this->assertEquals(1, $dashboard['summary']['active_licenses']);
    }

    /** @test */
    public function empty_tenant_returns_empty_data()
    {
        $otherTenant = Tenant::factory()->create();
        License::factory()->create(['tenant_id' => $otherTenant->id, 'status' => 'active']);

        $summary = $this->service->getSummary($this->tenant->id);
        $this->assertEquals(0, $summary['total_licenses']);

        $typeDist = $this->service->getLicenseTypeDistribution($this->tenant->id);
        $this->assertEmpty($typeDist);

        $statusDist = $this->service->getLicenseStatusDistribution($this->tenant->id);
        $this->assertEmpty($statusDist);

        $dashboard = $this->service->getLicenseDashboard($this->tenant->id);
        $this->assertEquals(0, $dashboard['summary']['total_licenses']);
    }

    /** @test */
    public function gets_existing_event_trend()
    {
        // 创建一些分析事件
        LicenseAnalyticsEvent::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'activation',
            'occurred_at' => Carbon::now()->subDays(1),
        ]);

        LicenseAnalyticsEvent::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'activation',
            'occurred_at' => Carbon::now(),
        ]);

        $trend = $this->service->getEventTrend('activation', 7, $this->tenant->id);

        $this->assertCount(7, $trend);

        $yesterday = Carbon::now()->subDays(1)->format('Y-m-d');
        $yesterdayData = collect($trend)->firstWhere('date', $yesterday);
        $this->assertEquals(5, $yesterdayData['count']);

        $today = Carbon::now()->format('Y-m-d');
        $todayData = collect($trend)->firstWhere('date', $today);
        $this->assertEquals(3, $todayData['count']);
    }
}
