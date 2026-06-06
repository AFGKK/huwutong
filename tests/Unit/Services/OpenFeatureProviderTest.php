<?php

namespace Tests\Unit\Services;

use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\FeatureFlagService;
use App\Services\OpenFeature\EvaluationContext;
use App\Services\OpenFeature\OpenFeatureProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenFeatureProviderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private License $license;
    private FeatureFlagService $featureFlagService;
    private OpenFeatureProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        // 注册 Feature Flag
        $feature = FeatureFlag::create([
            'key' => 'ai_features',
            'name' => 'AI 功能',
            'description' => '人工智能功能',
            'is_active' => true,
        ]);
        $this->product->featureFlags()->attach($feature->id, ['is_active' => true]);

        $this->featureFlagService = $this->app->make(FeatureFlagService::class);
        $this->provider = new OpenFeatureProvider($this->featureFlagService);
    }

    public function test_metadata_returns_provider_info(): void
    {
        $meta = $this->provider->metadata();

        $this->assertArrayHasKey('name', $meta);
        $this->assertArrayHasKey('version', $meta);
        $this->assertArrayHasKey('supported_types', $meta);
        $this->assertEquals('HWT OpenFeature Provider', $meta['name']);
    }

    public function test_resolve_boolean_returns_true_for_enabled_feature(): void
    {
        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveBooleanEvaluation('ai_features', false, $context);

        $this->assertTrue($result->value);
        $this->assertEquals('TARGETING_MATCH', $result->reason);
        $this->assertEquals('on', $result->variant);
    }

    public function test_resolve_boolean_returns_false_for_disabled_feature(): void
    {
        // 禁用产品的 feature flag
        $feature = FeatureFlag::where('key', 'ai_features')->first();
        $this->product->featureFlags()->updateExistingPivot($feature->id, ['is_active' => false]);
        $this->featureFlagService->clearProductCache($this->product);

        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveBooleanEvaluation('ai_features', true, $context);

        $this->assertFalse($result->value);
        $this->assertEquals('TARGETING_MATCH', $result->reason);
        $this->assertEquals('off', $result->variant);
    }

    public function test_resolve_boolean_returns_default_for_nonexistent_flag(): void
    {
        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveBooleanEvaluation('nonexistent_flag', true, $context);

        $this->assertFalse($result->value);
        $this->assertEquals('TARGETING_MATCH', $result->reason);
    }

    public function test_resolve_boolean_returns_default_without_context(): void
    {
        // 无 context 时，如果 flag 全局开启了，返回其全局值
        $result = $this->provider->resolveBooleanEvaluation('ai_features', true);

        $this->assertTrue($result->value);
        $this->assertEquals('TARGETING_MATCH', $result->reason);
    }

    public function test_resolve_boolean_for_expired_license_returns_false(): void
    {
        $this->license->update([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveBooleanEvaluation('ai_features', true, $context);

        $this->assertFalse($result->value);
    }

    public function test_resolve_string_from_license_metadata(): void
    {
        $this->license->update([
            'metadata' => ['theme' => 'dark', 'region' => 'us-east-1'],
        ]);

        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveStringEvaluation('theme', 'light', $context);

        $this->assertEquals('dark', $result->value);
        $this->assertEquals('TARGETING_MATCH', $result->reason);
    }

    public function test_resolve_string_returns_default_when_not_in_metadata(): void
    {
        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveStringEvaluation('nonexistent_config', 'default_val', $context);

        $this->assertEquals('default_val', $result->value);
        $this->assertEquals('DEFAULT', $result->reason);
    }

    public function test_resolve_integer_from_license_metadata(): void
    {
        $this->license->update([
            'metadata' => ['max_concurrent_users' => 100],
        ]);

        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveIntegerEvaluation('max_concurrent_users', 10, $context);

        $this->assertEquals(100, $result->value);
    }

    public function test_resolve_float_from_license_metadata(): void
    {
        $this->license->update([
            'metadata' => ['sampling_rate' => 0.5],
        ]);

        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveFloatEvaluation('sampling_rate', 0.1, $context);

        $this->assertEquals(0.5, $result->value);
    }

    public function test_resolve_object_from_license_metadata(): void
    {
        $config = ['max_retries' => 3, 'timeout_ms' => 5000];
        $this->license->update([
            'metadata' => ['rate_limit_config' => $config],
        ]);

        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $result = $this->provider->resolveObjectEvaluation('rate_limit_config', [], $context);

        $this->assertEquals($config, $result->value);
    }

    public function test_get_all_flags_returns_known_flags(): void
    {
        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $flags = $this->provider->getAllFlags($context);

        $this->assertArrayHasKey('ai_features', $flags);
        $this->assertTrue($flags['ai_features']->value);
    }

    public function test_get_all_flags_returns_empty_without_context(): void
    {
        $flags = $this->provider->getAllFlags();
        $this->assertEmpty($flags);
    }

    public function test_resolve_bulk_returns_multiple_flags(): void
    {
        $context = new EvaluationContext(
            targetingKey: $this->license->license_key,
        );

        $results = $this->provider->resolveBulk([
            'ai_features' => ['type' => 'boolean', 'default' => false],
            'nonexistent' => ['type' => 'boolean', 'default' => true],
        ], $context);

        $this->assertCount(2, $results);
        $this->assertTrue($results['ai_features']->value);
        $this->assertFalse($results['nonexistent']->value);
    }

    public function test_evaluate_with_invalid_license_returns_error(): void
    {
        $context = new EvaluationContext(
            targetingKey: 'INVALID-LICENSE-KEY',
        );

        $result = $this->provider->resolveBooleanEvaluation('ai_features', false, $context);

        $this->assertFalse($result->value);
        $this->assertEquals('DEFAULT', $result->reason);
    }

    public function test_global_flag_without_context(): void
    {
        // 无 context 时，如果 flag 全局开启了，则返回 true
        $result = $this->provider->resolveBooleanEvaluation('ai_features', false);

        // ai_features 全局 is_active=true
        $this->assertTrue($result->value);
        $this->assertEquals('TARGETING_MATCH', $result->reason);
    }

    public function test_known_flag_returns_default_when_not_active(): void
    {
        FeatureFlag::where('key', 'ai_features')->update(['is_active' => false]);

        $result = $this->provider->resolveBooleanEvaluation('ai_features', true);

        $this->assertFalse($result->value);
    }

    public function test_flag_value_to_array(): void
    {
        $value = \App\Services\OpenFeature\FlagValue::match(true, 'on');

        $array = $value->toArray();

        $this->assertEquals([
            'value' => true,
            'reason' => 'TARGETING_MATCH',
            'variant' => 'on',
        ], $array);
    }

    public function test_flag_value_default(): void
    {
        $value = \App\Services\OpenFeature\FlagValue::default(false);

        $this->assertFalse($value->value);
        $this->assertEquals('DEFAULT', $value->reason);
        $this->assertNull($value->variant);
        $this->assertNull($value->errorCode);
    }

    public function test_evaluation_context_merge(): void
    {
        $base = new EvaluationContext('key-1', ['a' => 1]);
        $override = new EvaluationContext('key-2', ['b' => 2]);

        $merged = $base->merge($override);

        $this->assertEquals('key-2', $merged->targetingKey);
        $this->assertEquals(['a' => 1, 'b' => 2], $merged->attributes);
    }

    public function test_evaluation_context_from_request(): void
    {
        $context = EvaluationContext::fromRequest([
            'targeting_key' => 'key-1',
            'context' => ['license_id' => 123],
        ]);

        $this->assertEquals('key-1', $context->targetingKey);
        $this->assertEquals(['license_id' => 123], $context->attributes);
    }

    public function test_flag_value_error(): void
    {
        $value = \App\Services\OpenFeature\FlagValue::error(false, 'FLAG_NOT_FOUND');

        $this->assertFalse($value->value);
        $this->assertEquals('ERROR', $value->reason);
        $this->assertEquals('FLAG_NOT_FOUND', $value->errorCode);
    }
}
