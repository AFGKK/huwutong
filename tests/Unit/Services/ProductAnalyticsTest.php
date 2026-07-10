<?php

namespace Tests\Unit\Services;

use App\Models\Device;
use App\Models\License;
use App\Models\LicenseAnalyticsEvent;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\ProductAnalyticsService;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProductAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected ProductAnalyticsService $service;
    protected Tenant $tenant;
    protected Product $product;
    protected License $license;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProductAnalyticsService();

        $this->tenant = Tenant::factory()->create();

        $this->product = Product::factory()->create([
            'name' => '测试产品',
            'slug' => 'test-product',
            'version' => '1.0',
            'modules' => ['module_a', 'module_b', 'module_c'],
            'is_active' => true,
        ]);

        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
        ]);

        // 创建一些分析事件
        LicenseAnalyticsEvent::factory()->count(5)->create([
            'license_id' => $this->license->id,
            'tenant_id' => $this->tenant->id,
            'event_type' => 'activation',
            'country_code' => 'CN',
            'country_name' => '中国',
            'city' => '北京',
            'latitude' => 39.9042,
            'longitude' => 116.4074,
            'occurred_at' => Carbon::now()->subDays(1),
            'metadata' => ['module' => 'module_a'],
        ]);

        LicenseAnalyticsEvent::factory()->count(3)->create([
            'license_id' => $this->license->id,
            'tenant_id' => $this->tenant->id,
            'event_type' => 'heartbeat',
            'country_code' => 'US',
            'country_name' => '美国',
            'city' => 'New York',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'occurred_at' => Carbon::now(),
            'metadata' => ['module' => 'module_b'],
        ]);

        // 创建设备
        Device::factory()->create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license->id,
            'platform' => 'windows',
        ]);
    }

    /** @test */
    public function gets_summary_stats()
    {
        $summary = $this->service->getSummary($this->tenant->id);

        $this->assertEquals(1, $summary['total_products']);
        $this->assertEquals(1, $summary['total_licenses']);
        $this->assertEquals(1, $summary['active_licenses']);
        $this->assertEquals(1, $summary['active_licenses']);
        $this->assertEquals(1, $summary['total_devices']);
        $this->assertGreaterThan(0, $summary['activation_rate']);
    }

    /** @test */
    public function gets_product_ranking()
    {
        $ranking = $this->service->getProductRanking($this->tenant->id);

        $this->assertCount(1, $ranking);
        $this->assertEquals('测试产品', $ranking[0]['product_name']);
        $this->assertEquals(1, $ranking[0]['total_licenses']);
        $this->assertEquals(1, $ranking[0]['active_licenses']);
        $this->assertEquals(100.0, $ranking[0]['activation_rate']);
        $this->assertEquals(8, $ranking[0]['total_events']);
        $this->assertEquals(1, $ranking[0]['total_devices']);
    }

    /** @test */
    public function gets_module_usage()
    {
        $usage = $this->service->getModuleUsage($this->tenant->id);

        $this->assertCount(1, $usage);
        $this->assertEquals('测试产品', $usage[0]['product_name']);
        $this->assertCount(3, $usage[0]['modules']);
    }

    /** @test */
    public function gets_regional_growth()
    {
        $growth = $this->service->getRegionalGrowth($this->tenant->id);

        $this->assertCount(2, $growth['countries']);
        $this->assertEquals('CN', $growth['countries'][0]['country_code']);
    }

    /** @test */
    public function gets_license_trend()
    {
        $trend = $this->service->getLicenseTrend($this->tenant->id);

        $this->assertNotEmpty($trend);
        // 应该有30天的数据（填充缺失日期）
        $this->assertCount(31, $trend); // 30天 + today
    }

    /** @test */
    public function gets_activation_trend()
    {
        $trend = $this->service->getActivationTrend($this->tenant->id);

        $this->assertNotEmpty($trend);
        $this->assertCount(31, $trend);
    }

    /** @test */
    public function gets_heatmap()
    {
        $heatmap = $this->service->getHeatmap($this->tenant->id, 30);

        $this->assertCount(2, $heatmap);
        $this->assertEquals(39.9042, $heatmap[0]['lat']);
        $this->assertEquals(116.4074, $heatmap[0]['lng']);
        $this->assertEquals('CN', $heatmap[0]['country_code']);
    }

    /** @test */
    public function gets_product_monthly_trend()
    {
        $trend = $this->service->getProductMonthlyTrend($this->tenant->id, 6);

        $this->assertNotEmpty($trend);
        $currentMonth = Carbon::now()->format('Y-m');
        $this->assertEquals($currentMonth, $trend[0]['month']);
        $this->assertEquals(1, $trend[0]['total_new_licenses']);
    }

    /** @test */
    public function gets_regional_trend()
    {
        $regionalTrend = $this->service->getRegionalTrend($this->tenant->id, 6);

        $this->assertNotEmpty($regionalTrend['monthly_trend']);
        $this->assertNotEmpty($regionalTrend['top_countries']);

        // at least CN should be in top countries
        $countryCodes = array_column($regionalTrend['top_countries'], 'country_code');
        $this->assertContains('CN', $countryCodes);
    }

    /** @test */
    public function dashboard_returns_all_sections()
    {
        $dashboard = $this->service->getDashboard($this->tenant->id, 30);

        $this->assertArrayHasKey('product_ranking', $dashboard);
        $this->assertArrayHasKey('module_usage', $dashboard);
        $this->assertArrayHasKey('regional_growth', $dashboard);
        $this->assertArrayHasKey('license_trend', $dashboard);
        $this->assertArrayHasKey('activation_trend', $dashboard);
        $this->assertArrayHasKey('summary', $dashboard);

        $this->assertCount(1, $dashboard['product_ranking']);
        $this->assertCount(2, $dashboard['regional_growth']['countries']);
    }

    /** @test */
    public function empty_tenant_returns_empty_data()
    {
        $emptyTenant = Tenant::factory()->create();

        $ranking = $this->service->getProductRanking($emptyTenant->id);
        // Products are global; empty tenant shows products with 0 licenses
        $this->assertNotEmpty($ranking);
        $this->assertEquals(0, $ranking[0]['total_licenses']);

        $moduleUsage = $this->service->getModuleUsage($emptyTenant->id);
        $this->assertEmpty($moduleUsage);

        $regionalGrowth = $this->service->getRegionalGrowth($emptyTenant->id);
        $this->assertEmpty($regionalGrowth['countries']);
    }
}
