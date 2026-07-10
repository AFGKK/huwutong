<?php

namespace Tests\Contract\Provider;

use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FingerprintService;
use App\Services\KeyGenerator;
use Tests\Concerns\LicenseActivationHelpers;
use Tests\Concerns\RefreshDatabase;
use Tests\Contract\PactContract;
use Tests\TestCase;

/**
 * HWT License API 提供者契约验证测试
 *
 * 验证 Laravel API 实现是否符合 SDK 消费者定义的核心交互契约。
 */
class LicenseApiProviderContractTest extends TestCase
{
    use LicenseActivationHelpers;
    use RefreshDatabase;

    private Tenant $tenant;

    private Product $product;

    private User $user;

    private KeyGenerator $keyGenerator;

    private FingerprintService $fingerprintService;

    /** @var array<string, string> */
    private array $deviceComponents = [
        'mac' => '00:1A:2B:3C:4D:5E',
        'cpu_id' => 'BFEBFBFF000906E9',
        'motherboard' => 'ASUS ROG STRIX Z790-E',
        'disk_sn' => 'XY1234567890ABCD',
        'system_uuid' => '4C4C4544-004C-4410-8053-B4C04F4D3332',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyGenerator = app(KeyGenerator::class);
        $this->fingerprintService = app(FingerprintService::class);

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->activationTenantId = $this->tenant->id;
        $this->activationProductId = $this->product->id;

        $this->actingAs($this->user, 'sanctum');
    }

    public function test_all_pacts_pass(): void
    {
        $contracts = PactContract::listContracts();
        if (empty($contracts)) {
            $this->markTestSkipped('没有找到 Pact 契约文件');

            return;
        }

        $this->markTestSkipped('全量 Pact 轮询验证需要 Provider State 基础设施，见单交互用例');
    }

    public function test_activate_license_success(): void
    {
        $licenseKey = $this->keyGenerator->generate('standard');
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => $licenseKey,
            'status' => 'pending',
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
            'metadata' => ['signature_secret' => 'test-activation-secret'],
        ]);

        $fingerprint = $this->fingerprintService->generate($this->deviceComponents);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
            'components' => $this->deviceComponents,
            'platform' => 'linux',
            'os_version' => '22.04',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.license_key', $licenseKey)
            ->assertJsonStructure([
                'data' => ['valid', 'license_key', 'status', 'expires_at', 'activation_id', 'device_id'],
            ]);
    }

    public function test_validate_license_success(): void
    {
        $licenseKey = $this->keyGenerator->generate('standard');
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => $licenseKey,
            'status' => 'active',
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
            'metadata' => ['signature_secret' => 'test-activation-secret'],
        ]);

        $response = $this->securePostJson('/api/license/validate', [
            'license_key' => $licenseKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.license_key', $licenseKey);
    }

    public function test_license_list_paginated(): void
    {
        License::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->getJson('/api/licenses?page=1&per_page=15&sort=-created_at');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_device_list(): void
    {
        \App\Models\Device::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/devices?page=1&per_page=15');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }
}
