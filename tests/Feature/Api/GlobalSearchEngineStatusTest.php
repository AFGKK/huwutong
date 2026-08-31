<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Services\GlobalSearchService;
use App\Services\MeilisearchService;
use Mockery;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchEngineStatusTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['product-search.engine' => 'meilisearch']);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->app->forgetInstance(MeilisearchService::class);
        Mockery::close();
        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_engine_status_reports_degraded_when_meilisearch_down(): void
    {
        config(['meilisearch.host' => 'http://127.0.0.1:1']);
        $this->app->forgetInstance(MeilisearchService::class);

        $response = $this->getJson('/api/admin/search/engine-status', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.engine', 'meilisearch')
            ->assertJsonPath('data.meilisearch_available', false)
            ->assertJsonPath('data.degraded', true);
    }

    public function test_get_engine_status_marks_degraded_when_meilisearch_down(): void
    {
        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('isAvailable')->andReturn(false);
        $this->app->instance(MeilisearchService::class, $meili);

        $status = app(GlobalSearchService::class)->getEngineStatus();

        $this->assertTrue($status['degraded']);
        $this->assertSame('meilisearch', $status['engine']);
        $this->assertFalse($status['meilisearch_available']);
        $this->assertNotEmpty($status['message']);
    }
}
