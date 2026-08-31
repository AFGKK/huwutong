<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AdminPageDataController;
use App\Models\ConversionFunnelEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\LicenseAnalyticsEvent;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D-06 / T-10: 分析看板 AdminPageData 与专用 analytics API 数据断言
 */
class AdminPageDataTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $product = Product::factory()->create([
            'name' => '分析测试产品',
            'modules' => ['core', 'analytics'],
        ]);

        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'status' => 'active',
        ]);

        LicenseAnalyticsEvent::factory()->count(3)->create([
            'license_id' => $license->id,
            'tenant_id' => $this->tenant->id,
            'event_type' => 'activation',
            'country_code' => 'CN',
            'country_name' => '中国',
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'paid',
            'amount' => 299,
            'payment_method' => 'alipay',
            'paid_at' => now()->subDay(),
        ]);

        foreach (array_keys(config('conversion-funnel.funnel.stages', [])) as $stage) {
            ConversionFunnelEvent::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'license_id' => $license->id,
                'stage' => $stage,
                'event' => 'test_event',
                'metadata' => ['source' => 'organic'],
                'source' => 'organic',
                'occurred_at' => now(),
            ]);
        }
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function authenticatedRequest(array $query = []): Request
    {
        $request = Request::create('/', 'GET', $query);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    public function test_product_analytics_api_returns_structured_summary(): void
    {
        $response = $this->getJson('/api/product-analytics/summary', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_products',
                    'total_licenses',
                    'active_licenses',
                    'activation_rate',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.total_licenses'));
    }

    public function test_ecommerce_analytics_api_returns_structured_summary(): void
    {
        $response = $this->getJson('/api/ecommerce-analytics/summary', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_revenue',
                    'total_orders',
                    'avg_order_value',
                ],
            ]);

        $this->assertEquals(299, $response->json('data.total_revenue'));
    }

    public function test_conversion_funnel_api_returns_funnel_stages(): void
    {
        $response = $this->getJson('/api/admin/conversion-funnel/dashboard', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'funnel' => ['stages', 'overall_rate', 'total_started'],
                    'today_registered',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.funnel.stages'));
    }

    public function test_admin_page_data_controller_product_analytics_methods(): void
    {
        /** @var AdminPageDataController $controller */
        $controller = app(AdminPageDataController::class);
        $request = $this->authenticatedRequest();

        $summary = $controller->productAnalyticsSummary($request)->getData(true);
        $this->assertTrue($summary['success']);
        $this->assertArrayHasKey('total_licenses', $summary['data']);
        $this->assertGreaterThanOrEqual(1, $summary['data']['total_licenses']);

        $ranking = $controller->productAnalyticsRanking($request)->getData(true);
        $this->assertIsArray($ranking['data']);
        $this->assertNotEmpty($ranking['data']);
        $this->assertArrayHasKey('product_name', $ranking['data'][0]);

        $trend = $controller->productAnalyticsTrend($request)->getData(true);
        $this->assertIsArray($trend['data']);
        $this->assertArrayHasKey('date', $trend['data'][0]);
        $this->assertArrayHasKey('new_licenses', $trend['data'][0]);
    }

    public function test_admin_page_data_controller_ecommerce_analytics_methods(): void
    {
        /** @var AdminPageDataController $controller */
        $controller = app(AdminPageDataController::class);
        $request = $this->authenticatedRequest();

        $summary = $controller->ecommerceSummary($request)->getData(true);
        $this->assertEquals(299, $summary['data']['total_revenue']);

        $comparison = $controller->ecommerceComparison($request)->getData(true);
        $this->assertIsArray($comparison['data']);

        $salesTrend = $controller->ecommerceSalesTrend($request)->getData(true);
        $this->assertIsArray($salesTrend['data']);
        $this->assertArrayHasKey('date', $salesTrend['data'][0]);
    }

    public function test_admin_page_data_controller_funnel_and_bundle_methods(): void
    {
        /** @var AdminPageDataController $controller */
        $controller = app(AdminPageDataController::class);
        $request = $this->authenticatedRequest();

        $funnel = $controller->funnelDashboard($request)->getData(true);
        $this->assertArrayHasKey('funnel', $funnel['data']);
        $this->assertNotEmpty($funnel['data']['funnel']['stages']);

        $funnelData = $controller->funnelData($request)->getData(true);
        $this->assertArrayHasKey('stages', $funnelData['data']);

        $bundleStats = $controller->bundlesStats()->getData(true);
        $this->assertArrayHasKey('total_bundles', $bundleStats['data']);
        $this->assertIsInt($bundleStats['data']['total_bundles']);

        $preSaleStats = $controller->preSaleStats()->getData(true);
        $this->assertArrayHasKey('total', $preSaleStats['data']);
        $this->assertArrayHasKey('active', $preSaleStats['data']);
    }

    public function test_admin_page_data_controller_dashboard_stats_have_expected_types(): void
    {
        /** @var AdminPageDataController $controller */
        $controller = app(AdminPageDataController::class);
        $request = $this->authenticatedRequest();

        $cases = [
            'promotionEngineStats' => ['total', 'active'],
            'certificationStats' => ['total', 'pending', 'approved', 'rejected'],
            'licenseMergeStats' => ['total_merges', 'pending', 'completed'],
            'renewalReminderDashboard' => ['total_due', 'reminded', 'renewed'],
            'emailDripDashboard' => ['total_campaigns', 'active_campaigns', 'open_rate'],
            'flashSaleDashboard' => ['total', 'active', 'totalOrders'],
            'resaleDashboard' => ['total_listings', 'active_listings', 'total_sold'],
            'licenseMarketplaceDashboard' => ['activeListings', 'totalTransactions'],
        ];

        foreach ($cases as $method => $keys) {
            $payload = $controller->{$method}($request)->getData(true);
            $this->assertTrue($payload['success'], "Failed asserting success for {$method}");
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $payload['data'], "Missing key {$key} in {$method}");
            }
        }
    }
}
