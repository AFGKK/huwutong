<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PortalLicenseStatsIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_user_license_stats_are_scoped_to_own_customer(): void
    {
        $tenant = Tenant::factory()->create();

        $portalUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
        Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $portalUser->id,
        ]);

        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $product = Product::factory()->create();

        License::factory()->count(3)->active()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $otherCustomer->id,
            'product_id' => $product->id,
        ]);

        Sanctum::actingAs($portalUser, ['*']);

        $this->getJson('/api/licenses/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.active', 0);
    }
}
