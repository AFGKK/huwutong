<?php

namespace Tests\Unit\Services;

use App\Enums\LicenseStatus;
use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\FeatureFlagService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private License $license;
    private FeatureFlag $aiFeature;
    private FeatureFlag $apiFeature;
    private FeatureFlag $advancedFeature;
    private FeatureFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeatureFlagService::class);

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();

        // 创建 Feature Flag
        $this->aiFeature = FeatureFlag::create([
            'key' => 'ai',
            'name' => 'AI 功能',
            'description' => '深度学习/AI 功能支持',
            'is_active' => true,
        ]);
        $this->apiFeature = FeatureFlag::create([
            'key' => 'api_access',
            'name' => 'API 访问',
            'description' => 'API 接口访问权限',
            'is_active' => true,
        ]);
        $this->advancedFeature = FeatureFlag::create([
            'key' => 'advanced_features',
            'name' => '高级功能',
            'description' => '高级企业功能',
            'is_active' => false, // 全局关闭
        ]);

        // 关联产品
        $this->product->featureFlags()->attach([
            $this->aiFeature->id => ['is_active' => true],
            $this->apiFeature->id => ['is_active' => true],
            $this->advancedFeature->id => ['is_active' => true],
        ]);

        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => LicenseStatus::Active->value,
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_has_feature_returns_true_for_active_license(): void
    {
        $this->assertTrue($this->service->hasFeature($this->license, 'ai'));
        $this->assertTrue($this->service->hasFeature($this->license, 'api_access'));
    }

    public function test_has_feature_returns_false_for_globally_disabled_feature(): void
    {
        $this->assertFalse($this->service->hasFeature($this->license, 'advanced_features'));
    }

    public function test_has_feature_returns_false_for_nonexistent_feature(): void
    {
        $this->assertFalse($this->service->hasFeature($this->license, 'nonexistent_feature'));
    }

    public function test_has_feature_returns_false_for_expired_license(): void
    {
        $this->license->update(['status' => LicenseStatus::Expired->value]);

        $this->assertFalse($this->service->hasFeature($this->license, 'ai'));
    }

    public function test_has_feature_returns_false_for_revoked_license(): void
    {
        $this->license->update(['status' => LicenseStatus::Revoked->value]);

        $this->assertFalse($this->service->hasFeature($this->license, 'ai'));
    }

    public function test_has_feature_returns_false_for_suspended_license(): void
    {
        $this->license->update(['status' => LicenseStatus::Suspended->value]);

        $this->assertFalse($this->service->hasFeature($this->license, 'ai'));
    }

    public function test_has_feature_considers_license_expiry_date(): void
    {
        $this->license->update(['expires_at' => now()->subDay()]);

        $this->assertFalse($this->service->hasFeature($this->license, 'ai'));
    }

    public function test_check_features_returns_correct_results(): void
    {
        $results = $this->service->checkFeatures($this->license, ['ai', 'advanced_features', 'nonexistent']);

        $this->assertTrue($results['ai']);
        $this->assertFalse($results['advanced_features']);
        $this->assertFalse($results['nonexistent']);
    }

    public function test_get_license_features_returns_all_features_with_status(): void
    {
        $features = $this->service->getLicenseFeatures($this->license);

        $this->assertCount(3, $features);

        $aiFeature = collect($features)->firstWhere('key', 'ai');
        $this->assertTrue($aiFeature['enabled']);

        $advancedFeature = collect($features)->firstWhere('key', 'advanced_features');
        $this->assertFalse($advancedFeature['enabled']);
    }

    public function test_get_product_features_returns_cached_results(): void
    {
        $features = $this->service->getProductFeatures($this->product);
        $this->assertCount(3, $features);
    }

    public function test_assign_feature_to_product(): void
    {
        $newFeature = FeatureFlag::create([
            'key' => 'reporting',
            'name' => '报表功能',
            'description' => '数据报表',
            'is_active' => true,
        ]);

        $this->service->assignFeatureToProduct($this->product, $newFeature);

        $features = $this->service->getProductFeatures($this->product);
        $this->assertCount(4, $features);
    }

    public function test_update_product_feature(): void
    {
        $this->service->updateProductFeature($this->product, $this->aiFeature, false);

        $this->assertFalse($this->service->hasFeature($this->license, 'ai'));
    }

    public function test_on_license_expired_disables_all_features(): void
    {
        $this->license->update(['status' => LicenseStatus::Expired->value]);
        $disabled = $this->service->onLicenseStatusChanged($this->license, 'expired');
        $this->assertEquals(['*'], $disabled);

        $this->assertFalse($this->service->hasFeature($this->license, 'ai'));
    }

    public function test_license_metadata_can_override_features(): void
    {
        $this->license->update([
            'metadata' => ['features' => ['ai' => false]],
        ]);

        // 清除缓存
        $this->service->clearProductCache($this->product);

        $this->assertFalse($this->service->hasFeature($this->license, 'ai'));
    }
}
