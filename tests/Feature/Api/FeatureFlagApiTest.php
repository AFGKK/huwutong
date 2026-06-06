<?php

namespace Tests\Feature\Api;

use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private License $license;
    private FeatureFlag $feature;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $this->feature = FeatureFlag::create([
            'key' => 'ai_features',
            'name' => 'AI 功能',
            'description' => '人工智能功能',
            'is_active' => true,
        ]);

        $this->product->featureFlags()->attach($this->feature->id, ['is_active' => true]);

        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_check_feature_returns_granted(): void
    {
        $response = $this->postJson('/api/license/check-feature', [
            'license_key' => $this->license->license_key,
            'feature' => 'ai_features',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.granted', true);
    }

    public function test_check_feature_returns_denied_for_nonexistent(): void
    {
        $response = $this->postJson('/api/license/check-feature', [
            'license_key' => $this->license->license_key,
            'feature' => 'nonexistent_feature',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.granted', false);
    }

    public function test_check_feature_returns_404_for_invalid_license(): void
    {
        $response = $this->postJson('/api/license/check-feature', [
            'license_key' => 'INVALID-KEY',
            'feature' => 'ai_features',
        ]);

        $response->assertStatus(404);
    }

    public function test_check_features_batch(): void
    {
        $response = $this->postJson('/api/license/check-features', [
            'license_key' => $this->license->license_key,
            'features' => ['ai_features', 'nonexistent_feature'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.features.ai_features', true)
            ->assertJsonPath('data.features.nonexistent_feature', false);
    }

    public function test_license_features_returns_all(): void
    {
        $response = $this->postJson('/api/license/features', [
            'license_key' => $this->license->license_key,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.license_key', $this->license->license_key)
            ->assertJsonStructure(['data' => ['features']]);
    }

    public function test_admin_list_feature_flags(): void
    {
        $response = $this->getJson('/api/feature-flags', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_assign_feature_to_product(): void
    {
        $newFeature = FeatureFlag::create([
            'key' => 'reporting',
            'name' => '报表',
            'description' => '报表功能',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/feature-flags/assign', [
            'product_id' => $this->product->id,
            'feature_flag_id' => $newFeature->id,
            'is_active' => true,
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_assign_requires_auth(): void
    {
        $response = $this->postJson('/api/feature-flags/assign', [
            'product_id' => $this->product->id,
            'feature_flag_id' => $this->feature->id,
        ]);

        $response->assertStatus(401);
    }

    // ─── product features 端点 ───

    public function test_product_features_returns_assigned_flags(): void
    {
        $response = $this->getJson(
            "/api/products/{$this->product->id}/features",
            $this->authHeaders(),
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['assigned', 'available']]);
    }

    public function test_product_features_requires_auth(): void
    {
        $response = $this->getJson("/api/products/{$this->product->id}/features");

        $response->assertStatus(401);
    }

    public function test_admin_list_flags_requires_auth(): void
    {
        $response = $this->getJson('/api/feature-flags');

        $response->assertStatus(401);
    }
}
