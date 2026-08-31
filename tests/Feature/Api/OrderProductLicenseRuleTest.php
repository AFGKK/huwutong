<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\License;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\Tenant;
use App\Services\AutoDeliveryEngine;
use App\Services\FingerprintService;
use App\Services\KeyGenerator;
use Tests\Concerns\LicenseActivationHelpers;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OrderProductLicenseRuleTest extends TestCase
{
    use RefreshDatabase;
    use LicenseActivationHelpers;

    public function test_paid_order_item_delivers_license_keys_bound_to_product(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->create();
        $sku = ProductSku::create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-'.uniqid(),
            'name' => '专业版-年付',
            'price' => 99,
            'currency' => 'CNY',
            'stock' => 100,
            'is_active' => true,
            'billing_cycle' => 'yearly',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::create([
            'order_no' => 'ORD-TEST-'.uniqid(),
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'total_amount' => 100,
            'final_amount' => 100,
            'currency' => 'CNY',
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku_id' => $sku->id,
            'name' => 'Test SKU',
            'unit_price' => 50,
            'quantity' => 2,
            'subtotal' => 100,
        ]);

        $result = app(AutoDeliveryEngine::class)->execute($order->fresh());

        $this->assertTrue($result['success'] ?? false, json_encode($result));
        $licenses = License::where('product_id', $product->id)
            ->where('metadata->order_id', $order->id)
            ->get();

        $this->assertCount(2, $licenses, 'quantity=2 should issue 2 license keys');
        foreach ($licenses as $license) {
            $this->assertSame($product->id, $license->product_id);
            $this->assertSame($sku->id, $license->sku_id);
            $this->assertNotEmpty($license->license_key);
            $this->assertSame('auto_delivery', $license->metadata['source'] ?? null);
        }
    }

    public function test_activate_rejects_license_key_for_wrong_product(): void
    {
        $keyGen = app(KeyGenerator::class);
        $fpSvc = app(FingerprintService::class);
        $tenant = Tenant::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $key = $keyGen->generate('standard');
        License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $productA->id,
            'license_key' => $key,
            'status' => 'active',
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
            'metadata' => ['signature_secret' => 'test-activation-secret'],
        ]);

        $this->activationTenantId = $tenant->id;
        $this->activationProductId = null;

        $components = [
            'mac' => '00:1A:2B:3C:4D:5E',
            'cpu_id' => 'BFEBFBFF000906E9',
            'motherboard' => 'ASUS ROG STRIX Z790-E',
            'disk_sn' => 'XY1234567890ABCD',
            'system_uuid' => '4C4C4544-004C-4410-8053-B4C04F4D3332',
        ];

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $key,
            'product_id' => $productB->id,
            'fingerprint' => $fpSvc->generate($components),
            'components' => $components,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'LICENSE_PRODUCT_MISMATCH');
    }

    public function test_license_can_exist_without_order_and_still_activate(): void
    {
        $keyGen = app(KeyGenerator::class);
        $fpSvc = app(FingerprintService::class);
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->create();
        $key = $keyGen->generate('standard');

        License::factory()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'license_key' => $key,
            'status' => 'active',
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
            'metadata' => ['signature_secret' => 'test-activation-secret', 'source' => 'admin_manual'],
        ]);

        $this->activationTenantId = $tenant->id;
        $this->activationProductId = $product->id;

        $components = [
            'mac' => '11:22:33:44:55:66',
            'cpu_id' => 'CPU-ADMIN-001',
            'motherboard' => 'MB-ADMIN',
            'disk_sn' => 'DISK-ADMIN',
            'system_uuid' => '11111111-2222-3333-4444-555555555555',
        ];

        $this->securePostJson('/api/license/activate', [
            'license_key' => $key,
            'fingerprint' => $fpSvc->generate($components),
            'components' => $components,
        ])->assertOk()->assertJsonPath('data.valid', true);
    }
}
