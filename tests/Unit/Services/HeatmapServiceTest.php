<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\HeatmapLayer;
use App\Models\License;
use App\Models\LicenseAnalyticsEvent;
use App\Models\Tenant;
use App\Services\HeatmapService;
use Tests\Concerns\RefreshDatabase;

class HeatmapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HeatmapService $service;
    protected Tenant $tenant;
    protected License $license1;
    protected License $license2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(HeatmapService::class);
        $this->tenant = Tenant::factory()->create();
        $this->license1 = License::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->license2 = License::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ─── 图层管理 ───

    public function test_creates_and_lists_layers()
    {
        HeatmapLayer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '全球激活',
            'slug' => 'global-activations',
            'data_source' => 'license_activations',
            'type' => 'heatmap_scatter',
        ]);
        HeatmapLayer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '收入地图',
            'slug' => 'revenue-map',
            'data_source' => 'revenue',
            'type' => 'country_choropleth',
        ]);

        $layers = $this->service->listLayers($this->tenant->id);

        $this->assertCount(2, $layers);
        $this->assertEquals('全球激活', $layers[0]['name']);
    }

    public function test_updates_layer()
    {
        $layer = HeatmapLayer::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        $updated = $this->service->updateLayer($layer, ['is_active' => false]);

        $this->assertFalse($updated->is_active);
    }

    public function test_deletes_layer()
    {
        $layer = HeatmapLayer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->deleteLayer($layer);

        $this->assertDatabaseMissing('heatmap_layers', ['id' => $layer->id]);
    }

    // ─── 热力图数据 ───

    public function test_get_activation_heatmap_returns_empty_when_no_data()
    {
        $result = $this->service->getMultiLayerData($this->tenant->id, ['layers' => 'license_activations']);

        $this->assertArrayHasKey('license_activations', $result);
        $this->assertEmpty($result['license_activations']['points']);
        $this->assertEmpty($result['license_activations']['countries']);
    }

    public function test_get_activation_heatmap_with_geo_events()
    {
        LicenseAnalyticsEvent::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license1->id,
            'event_type' => 'activation',
            'latitude' => 31.2304,
            'longitude' => 121.4737,
            'country_code' => 'CN',
            'country_name' => '中国',
            'city' => '上海',
            'ip_address' => '1.2.3.4',
            'occurred_at' => now()->subHours(1),
        ]);
        LicenseAnalyticsEvent::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license2->id,
            'event_type' => 'heartbeat',
            'latitude' => 31.2304,
            'longitude' => 121.4737,
            'country_code' => 'CN',
            'country_name' => '中国',
            'city' => '上海',
            'ip_address' => '5.6.7.8',
            'occurred_at' => now()->subHours(2),
        ]);
        LicenseAnalyticsEvent::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license1->id,
            'event_type' => 'activation',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'country_code' => 'US',
            'country_name' => '美国',
            'city' => '纽约',
            'ip_address' => '9.10.11.12',
            'occurred_at' => now()->subDays(5),
        ]);

        $result = $this->service->getMultiLayerData($this->tenant->id, ['layers' => 'license_activations']);

        $this->assertCount(2, $result['license_activations']['points']);
        $this->assertCount(2, $result['license_activations']['countries']);
        $this->assertEquals(3, $result['license_activations']['summary']['total_events']);

        // 中国点数较多
        $cn = $result['license_activations']['countries'][0];
        $this->assertEquals('CN', $cn['country_code']);
    }

    // ─── 国家钻取 ───

    public function test_get_country_detail()
    {
        LicenseAnalyticsEvent::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license1->id,
            'event_type' => 'activation',
            'latitude' => 31.2304,
            'longitude' => 121.4737,
            'country_code' => 'CN',
            'country_name' => '中国',
            'city' => '上海',
            'ip_address' => '1.2.3.4',
            'occurred_at' => now()->subHours(1),
        ]);
        LicenseAnalyticsEvent::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license2->id,
            'event_type' => 'activation',
            'latitude' => 31.2304,
            'longitude' => 121.4737,
            'country_code' => 'CN',
            'country_name' => '中国',
            'city' => '上海',
            'ip_address' => '5.6.7.8',
            'occurred_at' => now()->subHours(2),
        ]);

        $detail = $this->service->getCountryDetail($this->tenant->id, 'CN');

        $this->assertEquals('CN', $detail['country_code']);
        $this->assertCount(1, $detail['events']); // 只有 activation
        $this->assertCount(1, $detail['cities']);
        $this->assertEquals('上海', $detail['cities'][0]['city']);
        $this->assertEquals(2, $detail['cities'][0]['cnt']);
    }

    // ─── 仪表盘 ───

    public function test_get_dashboard_stats()
    {
        LicenseAnalyticsEvent::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license1->id,
            'event_type' => 'activation',
            'latitude' => 31.2304,
            'longitude' => 121.4737,
            'country_code' => 'CN',
            'country_name' => '中国',
            'ip_address' => '1.2.3.4',
            'occurred_at' => now()->subHours(1),
        ]);

        $stats = $this->service->getDashboardStats($this->tenant->id);

        $this->assertEquals(1, $stats['activated_countries']);
        // total_geo_points counts events with non-null lat/lng
        $this->assertEquals(1, $stats['total_geo_points']);
        $this->assertCount(1, $stats['top_countries']);
    }

    // ─── 多图层选择 ───

    public function test_get_multi_layer_data()
    {
        // 创建几条激活事件（包含坐标）
        LicenseAnalyticsEvent::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license1->id,
            'event_type' => 'activation',
            'latitude' => 31.2304,
            'longitude' => 121.4737,
            'country_code' => 'CN',
            'country_name' => '中国',
            'city' => '上海',
            'ip_address' => '1.2.3.4',
            'occurred_at' => now()->subHours(1),
        ]);
        // 创建几条心跳事件（用于 usage 地图）
        LicenseAnalyticsEvent::create([
            'tenant_id' => $this->tenant->id,
            'license_id' => $this->license1->id,
            'event_type' => 'heartbeat',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'country_code' => 'US',
            'country_name' => '美国',
            'city' => '纽约',
            'ip_address' => '9.10.11.12',
            'occurred_at' => now()->subDays(2),
        ]);

        $result = $this->service->getMultiLayerData($this->tenant->id, [
            'layers' => 'license_activations,product_usage',
        ]);

        $this->assertArrayHasKey('license_activations', $result);
        $this->assertArrayHasKey('product_usage', $result);

        // activation 图层有 2 个点（activation + heartbeat 都有坐标）
        $this->assertCount(2, $result['license_activations']['points']);
        // usage 图层有 1 个点（heartbeat）
        $this->assertCount(1, $result['product_usage']['points']);
    }

    public function test_get_revenue_heatmap_returns_countries()
    {
        // Revenue 热力图基于 Invoice 表，如果没有数据也应该返回空数组
        $result = $this->service->getMultiLayerData($this->tenant->id, ['layers' => 'revenue']);

        $this->assertArrayHasKey('revenue', $result);
        $this->assertEmpty($result['revenue']['points']);
        $this->assertEmpty($result['revenue']['countries']);
    }
}
