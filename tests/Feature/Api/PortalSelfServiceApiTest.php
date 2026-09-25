<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Device;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\UpdatePackage;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * 优化方案 v1 · 门户自助 API
 */
class PortalSelfServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_endpoint_lists_published_packages(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Demo Product']);
        UpdatePackage::query()->create([
            'product_id' => $product->id,
            'version' => '1.2.3',
            'type' => 'full',
            'file_path' => 'updates/demo-1.2.3.zip',
            'file_name' => 'demo-1.2.3.zip',
            'file_size' => 1024,
            'file_hash' => hash('sha256', 'demo'),
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $user->id,
        ]);
        UpdatePackage::query()->create([
            'product_id' => $product->id,
            'version' => '9.9.9',
            'type' => 'full',
            'file_path' => 'updates/demo-9.9.9.zip',
            'file_name' => 'demo-9.9.9.zip',
            'file_size' => 1024,
            'file_hash' => hash('sha256', 'draft'),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/downloads');

        $response->assertOk();
        $data = $response->json('data.data') ?? [];
        $this->assertNotEmpty($data);
        $versions = collect($data)->pluck('version')->all();
        $this->assertContains('1.2.3', $versions);
        $this->assertNotContains('9.9.9', $versions);
    }

    public function test_user_devices_requires_auth(): void
    {
        $this->getJson('/api/user/devices')->assertUnauthorized();
    }

    public function test_user_devices_returns_customer_devices(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
        $product = Product::factory()->create();
        $license = License::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'status' => 'active',
        ]);
        Device::factory()->create([
            'tenant_id' => $tenant->id,
            'license_id' => $license->id,
            'fingerprint' => 'fp-portal-device-1',
            'platform' => 'windows',
            'is_blacklisted' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user/devices');

        $response->assertOk();
        $items = $response->json('data.data') ?? [];
        $this->assertCount(1, $items);
        $this->assertSame('fp-portal-device-1', $items[0]['fingerprint']);
    }

    public function test_license_query_alias_matches_public_lookup(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->create();
        $license = License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'status' => 'active',
            'license_key' => 'HWT-STD-QUERYALIAS000001',
        ]);

        $response = $this->postJson('/api/license/query', [
            'license_key' => $license->license_key,
        ]);

        $response->assertOk();
        $response->assertJsonPath('found', true);
    }
}
