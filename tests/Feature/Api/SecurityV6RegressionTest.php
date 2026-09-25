<?php

namespace Tests\Feature\Api;

use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * 整体测试报告 v6 回归
 */
class SecurityV6RegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_license_lookup_route_is_throttled(): void
    {
        $route = collect(Route::getRoutes())->first(function ($r) {
            return in_array('POST', $r->methods(), true)
                && str_contains($r->uri(), 'license/public-lookup');
        });

        $this->assertNotNull($route, 'public-lookup route missing');

        $middleware = $route->gatherMiddleware();
        $hasThrottle = collect($middleware)->contains(
            fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')
        );

        $this->assertTrue($hasThrottle, 'public-lookup must use throttle middleware');
        $this->assertTrue(
            collect($middleware)->contains('throttle:60,1'),
            'expected throttle:60,1 on public-lookup'
        );
    }

    public function test_public_license_lookup_still_works_under_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->create();
        $license = License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'active',
            'license_key' => 'HWT-STD-V6LOOKUPOK00001',
        ]);

        $response = $this->postJson('/api/license/public-lookup', [
            'license_key' => $license->license_key,
        ]);

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $data = $response->json('data') ?? [];
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('customer', $data);
        $this->assertArrayNotHasKey('customer_id', $data);
    }

    public function test_public_license_lookup_rejects_empty_key(): void
    {
        $response = $this->postJson('/api/license/public-lookup', [
            'license_key' => '',
        ]);

        $response->assertStatus(422);
    }
}
