<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TimeRestrictionConfig;
use App\Models\TimeRestrictionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeRestrictionApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private License $license;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        ApiVersion::create([
            'version' => 'v1',
            'base_path' => '/api/v1',
            'name' => 'v1',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $product = Product::factory()->create();
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
        ]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_index_lists_configs(): void
    {
        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'Asia/Shanghai',
            'weekly_schedule' => [
                ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);

        $response = $this->getJson('/api/time-restriction', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.data') ?? []));
    }

    public function test_stats_returns_overview(): void
    {
        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
        ]);
        TimeRestrictionLog::create([
            'license_id' => $this->license->id,
            'result' => 'denied',
            'checked_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/api/time-restriction/stats', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_configs', 1)
            ->assertJsonPath('data.active_configs', 1);
    }

    public function test_save_creates_config_for_license(): void
    {
        $response = $this->postJson(
            "/api/licenses/{$this->license->id}/time-restriction",
            [
                'is_active' => true,
                'timezone' => 'Asia/Shanghai',
                'weekly_schedule' => [
                    ['day' => 1, 'start' => '09:00', 'end' => '18:00'],
                ],
                'out_of_hours_action' => 'deny',
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('time_restriction_configs', [
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
        ]);
    }

    public function test_show_returns_license_config(): void
    {
        TimeRestrictionConfig::create([
            'restrictable_type' => License::class,
            'restrictable_id' => $this->license->id,
            'is_active' => true,
            'timezone' => 'UTC',
        ]);

        $response = $this->getJson(
            "/api/licenses/{$this->license->id}/time-restriction",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_metadata_returns_field_options(): void
    {
        $response = $this->getJson('/api/time-restriction/metadata', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    public function test_global_logs_returns_paginated_entries(): void
    {
        TimeRestrictionLog::create([
            'license_id' => $this->license->id,
            'result' => 'allowed',
            'checked_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/api/time-restriction/logs', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/time-restriction/stats')->assertStatus(401);
    }
}
