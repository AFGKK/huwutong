<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MeilisearchService;
use Mockery;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MeilisearchApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

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
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->admin->createToken('admin-token', ['admin'])->plainTextToken;
    }

    protected function tearDown(): void
    {
        config([
            'meilisearch.host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
            'meilisearch.api_key' => env('MEILISEARCH_API_KEY', ''),
        ]);
        $this->app->forgetInstance(MeilisearchService::class);
        Mockery::close();

        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_health_endpoint_returns_structure(): void
    {
        $response = $this->getJson('/api/meilisearch/health', $this->authHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['status'],
            ]);
    }

    public function test_health_returns_setup_hints_when_unavailable(): void
    {
        config(['meilisearch.host' => 'http://127.0.0.1:1']);
        $this->app->forgetInstance(MeilisearchService::class);

        $response = $this->getJson('/api/meilisearch/health', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', 'unavailable')
            ->assertJsonPath('data.meilisearch_available', false)
            ->assertJsonStructure([
                'data' => ['hint', 'start_commands', 'rebuild_command'],
            ]);
    }

    public function test_stats_marks_meilisearch_unavailable_when_service_down(): void
    {
        config(['meilisearch.host' => 'http://127.0.0.1:1']);

        $this->app->forgetInstance(MeilisearchService::class);

        $response = $this->getJson('/api/meilisearch/stats', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.meilisearch_available', false);
    }

    public function test_sync_rejects_invalid_type(): void
    {
        $response = $this->postJson('/api/meilisearch/sync', [
            'type' => 'invalid_index',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_setup_index_accepts_official_accounts(): void
    {
        config(['meilisearch.host' => 'http://127.0.0.1:1']);
        $this->app->forgetInstance(MeilisearchService::class);

        $response = $this->postJson('/api/meilisearch/indexes/setup', [
            'index' => 'official_accounts',
        ], $this->authHeaders());

        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_unified_search_degrades_when_meilisearch_unavailable(): void
    {
        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('unifiedSearch')->andThrow(new \RuntimeException('Meilisearch 不可用'));
        $this->app->instance(MeilisearchService::class, $meili);

        $response = $this->getJson('/api/meilisearch/unified-search?q=test');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meilisearch_available', false)
            ->assertJsonPath('data.total', 0);
    }

    /**
     * @group meilisearch-integration
     */
    public function test_sync_all_when_meilisearch_running(): void
    {
        $this->app->forgetInstance(MeilisearchService::class);

        $service = app(MeilisearchService::class);
        if (! $service->isAvailable()) {
            $this->markTestSkipped('Meilisearch 未启动，跳过集成测试');
        }

        $response = $this->postJson('/api/meilisearch/sync', [
            'type' => 'all',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'products' => ['synced'],
                    'kb_articles' => ['synced'],
                ],
            ]);

        $health = $this->getJson('/api/meilisearch/health', $this->authHeaders());
        $health->assertOk()
            ->assertJsonPath('data.status', 'available');
    }
}
