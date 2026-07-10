<?php

namespace Tests\Feature\Api;

use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OpenFeatureApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private License $license;
    private User $user;
    private string $token;
    private FeatureFlag $feature;

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

    // ─── Provider Metadata ───

    public function test_metadata(): void
    {
        $response = $this->getJson('/api/openfeature/metadata');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['name', 'version', 'supported_types']]);
    }

    // ─── Evaluate ───

    public function test_evaluate_boolean_enabled(): void
    {
        $response = $this->postJson('/api/openfeature/evaluate', [
            'flag_key' => 'ai_features',
            'type' => 'boolean',
            'default_value' => false,
            'targeting_key' => $this->license->license_key,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', true)
            ->assertJsonPath('data.reason', 'TARGETING_MATCH')
            ->assertJsonPath('data.variant', 'on');
    }

    public function test_evaluate_boolean_disabled(): void
    {
        $this->product->featureFlags()->updateExistingPivot($this->feature->id, ['is_active' => false]);

        $response = $this->postJson('/api/openfeature/evaluate', [
            'flag_key' => 'ai_features',
            'type' => 'boolean',
            'default_value' => true,
            'targeting_key' => $this->license->license_key,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', false)
            ->assertJsonPath('data.reason', 'TARGETING_MATCH')
            ->assertJsonPath('data.variant', 'off');
    }

    public function test_evaluate_boolean_without_context(): void
    {
        $response = $this->postJson('/api/openfeature/evaluate', [
            'flag_key' => 'ai_features',
            'type' => 'boolean',
            'default_value' => false,
        ]);

        // 全局开启了 ai_features，所以为 true
        $response->assertStatus(200)
            ->assertJsonPath('data.value', true)
            ->assertJsonPath('data.reason', 'TARGETING_MATCH');
    }

    public function test_evaluate_nonexistent_flag(): void
    {
        $response = $this->postJson('/api/openfeature/evaluate', [
            'flag_key' => 'nonexistent',
            'type' => 'boolean',
            'default_value' => true,
            'targeting_key' => $this->license->license_key,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', false)
            ->assertJsonPath('data.reason', 'TARGETING_MATCH');
    }

    public function test_evaluate_string_from_metadata(): void
    {
        $this->license->update(['metadata' => ['theme' => 'dark']]);

        $response = $this->postJson('/api/openfeature/evaluate', [
            'flag_key' => 'theme',
            'type' => 'string',
            'default_value' => 'light',
            'targeting_key' => $this->license->license_key,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', 'dark');
    }

    public function test_evaluate_string_default(): void
    {
        $response = $this->postJson('/api/openfeature/evaluate', [
            'flag_key' => 'theme',
            'type' => 'string',
            'default_value' => 'light',
            'targeting_key' => $this->license->license_key,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.value', 'light')
            ->assertJsonPath('data.reason', 'DEFAULT');
    }

    public function test_evaluate_invalid_type_returns_422(): void
    {
        $response = $this->postJson('/api/openfeature/evaluate', [
            'flag_key' => 'test',
            'type' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ─── Bulk Evaluation ───

    public function test_evaluate_bulk(): void
    {
        $response = $this->postJson('/api/openfeature/evaluate/bulk', [
            'flags' => [
                'ai_features' => ['type' => 'boolean', 'default' => false],
                'nonexistent' => ['type' => 'boolean', 'default' => true],
            ],
            'targeting_key' => $this->license->license_key,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.flags.ai_features.value', true)
            ->assertJsonPath('data.flags.nonexistent.value', false);
    }

    // ─── All Flags ───

    public function test_all_flags(): void
    {
        $response = $this->postJson('/api/openfeature/flags', [
            'targeting_key' => $this->license->license_key,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['flags' => ['ai_features']]])
            ->assertJsonPath('data.flags.ai_features.value', true);
    }

    // ─── Flagd Compatible ───

    public function test_flagd_health(): void
    {
        $response = $this->getJson('/api/flagd/evaluation/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'SERVING');
    }

    public function test_flagd_evaluate_boolean(): void
    {
        $response = $this->postJson('/api/flagd/evaluation/v1/boolean', [
            'flagKey' => 'ai_features',
            'context' => ['targetingKey' => $this->license->license_key],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('value', true)
            ->assertJsonPath('reason', 'TARGETING_MATCH')
            ->assertJsonPath('variant', 'on');
    }

    public function test_flagd_evaluate_bulk(): void
    {
        $response = $this->postJson('/api/flagd/evaluation/v1/bulk', [
            'flags' => [
                [
                    'flagKey' => 'ai_features',
                    'type' => 'boolean',
                    'context' => ['targetingKey' => $this->license->license_key],
                ],
                [
                    'flagKey' => 'nonexistent',
                    'type' => 'boolean',
                    'context' => ['targetingKey' => $this->license->license_key],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['flags'])
            ->assertJsonCount(2, 'flags');
    }

    public function test_flagd_evaluate_invalid_type(): void
    {
        $response = $this->postJson('/api/flagd/evaluation/v1/invalid', [
            'flagKey' => 'test',
        ]);

        $response->assertStatus(404);
    }

    // ─── Management (Protected) ───

    public function test_manage_all_flags_requires_auth(): void
    {
        $response = $this->getJson('/api/openfeature/manage/flags');

        $response->assertStatus(401);
    }

    public function test_manage_all_flags(): void
    {
        $response = $this->getJson('/api/openfeature/manage/flags?' . http_build_query([
            'targeting_key' => $this->license->license_key,
        ]), $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                ['key', 'name', 'is_active', 'evaluated'],
            ]]);
    }
}
