<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\TrialLicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialLicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrialLicenseService $service;
    private Tenant $tenant;
    private Product $product;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TrialLicenseService::class);

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ─── 创建 Trial ───

    public function test_creates_trial_license_successfully(): void
    {
        $license = $this->service->createTrial($this->tenant, $this->customer, $this->product);

        $this->assertEquals('trial', $license->type);
        $this->assertEquals('active', $license->status);
        $this->assertEquals(2, $license->max_devices);
        $this->assertNotNull($license->activated_at);
        $this->assertTrue($license->expires_at->isFuture());
        $this->assertStringStartsWith('HWT-TRIAL-', $license->license_key);
    }

    public function test_trial_has_14_days_expiry_by_default(): void
    {
        $license = $this->service->createTrial($this->tenant, $this->customer, $this->product);

        $this->assertEquals(14, (int) round(now()->diffInDays($license->expires_at)));
    }

    public function test_trial_metadata_contains_trial_info(): void
    {
        $license = $this->service->createTrial($this->tenant, $this->customer, $this->product);

        $this->assertTrue($license->metadata['trial']);
        $this->assertEquals(14, $license->metadata['trial_days']);
    }

    // ─── 防滥用 ───

    public function test_rejects_duplicate_trial_for_same_customer(): void
    {
        $this->service->createTrial($this->tenant, $this->customer, $this->product);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('该客户已使用过试用');

        $this->service->createTrial($this->tenant, $this->customer, $this->product);
    }

    // ─── 转正 ───

    public function test_converts_trial_to_standard(): void
    {
        $license = $this->service->createTrial($this->tenant, $this->customer, $this->product);

        $converted = $this->service->convertToPaid($license, 'standard', 365, 5);

        $this->assertEquals('standard', $converted->type);
        $this->assertEquals(5, $converted->max_devices);
        $this->assertTrue($converted->metadata['converted_from_trial']);
        $this->assertNotNull($converted->metadata['converted_at']);
    }

    public function test_converts_trial_to_enterprise(): void
    {
        $license = $this->service->createTrial($this->tenant, $this->customer, $this->product);

        $converted = $this->service->convertToPaid($license, 'enterprise', 730, 50);

        $this->assertEquals('enterprise', $converted->type);
        $this->assertEquals(50, $converted->max_devices);
    }

    public function test_rejects_converting_non_trial_license(): void
    {
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'standard',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('只有 Trial 类型的 License 可以转正');

        $this->service->convertToPaid($license);
    }

    // ─── Trial 状态检查 ───

    public function test_check_trial_status_returns_active(): void
    {
        $license = $this->service->createTrial($this->tenant, $this->customer, $this->product);

        $result = $this->service->checkTrialStatus($license);

        $this->assertEquals('active', $result['action']);
    }

    public function test_check_trial_status_returns_expiring_soon(): void
    {
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'trial',
            'status' => 'active',
            'expires_at' => now()->addDays(1), // 1天后过期
        ]);

        $result = $this->service->checkTrialStatus($license);

        $this->assertEquals('expiring_soon', $result['action']);
    }

    public function test_check_trial_status_returns_expired_and_expires(): void
    {
        $license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'trial',
            'status' => 'active',
            'expires_at' => now()->subDay(), // 已过期
        ]);

        $result = $this->service->checkTrialStatus($license);

        $this->assertEquals('expired', $result['action']);
        $this->assertEquals('expired', $license->fresh()->status); // 已自动过期
    }

    // ─── 批量处理 ───

    public function test_expire_overdue_trials(): void
    {
        // 创建过期的 Trial
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => 'HWT-TRIAL-EXPIRED-001',
            'type' => 'trial',
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        // 创建未过期的 Trial（不应被影响）
        $this->service->createTrial($this->tenant, $this->customer, $this->product);

        $results = $this->service->expireOverdueTrials();

        $this->assertCount(1, $results);
        $this->assertEquals('expired', License::where('license_key', 'HWT-TRIAL-EXPIRED-001')->first()->status);
    }
}
