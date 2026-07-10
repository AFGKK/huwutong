<?php

namespace Tests\Unit\Services;

use App\Models\ApiChangelog;
use App\Models\ApiDocCodeSnippet;
use App\Models\ApiDocEndpoint;
use App\Models\ApiDocFavorite;
use App\Models\ApiDocSchema;
use App\Models\ApiTestRequest;
use App\Models\User;
use App\Services\ApiDocsService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ApiDocsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ApiDocsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ApiDocsService::class);
    }

    // ─── 端点收藏 ───

    public function test_toggle_favorite_adds_favorite()
    {
        $user = User::factory()->create();
        $endpoint = ApiDocEndpoint::factory()->create();

        $result = $this->service->toggleFavorite($user->id, $endpoint->id);

        $this->assertTrue($result['favorited']);
        $this->assertDatabaseHas('api_doc_favorites', [
            'user_id' => $user->id,
            'endpoint_id' => $endpoint->id,
        ]);
    }

    public function test_toggle_favorite_removes_favorite()
    {
        $user = User::factory()->create();
        $endpoint = ApiDocEndpoint::factory()->create();
        ApiDocFavorite::factory()->create([
            'user_id' => $user->id,
            'endpoint_id' => $endpoint->id,
        ]);

        $result = $this->service->toggleFavorite($user->id, $endpoint->id);

        $this->assertFalse($result['favorited']);
        $this->assertDatabaseMissing('api_doc_favorites', [
            'user_id' => $user->id,
            'endpoint_id' => $endpoint->id,
        ]);
    }

    public function test_get_user_favorites_returns_ordered_favorites()
    {
        $user = User::factory()->create();
        $endpoint1 = ApiDocEndpoint::factory()->create(['path' => '/api/test1']);
        $endpoint2 = ApiDocEndpoint::factory()->create(['path' => '/api/test2']);

        ApiDocFavorite::factory()->create([
            'user_id' => $user->id,
            'endpoint_id' => $endpoint1->id,
            'note' => 'my favorite',
        ]);
        ApiDocFavorite::factory()->create([
            'user_id' => $user->id,
            'endpoint_id' => $endpoint2->id,
        ]);

        $favorites = $this->service->getUserFavorites($user->id);

        $this->assertCount(2, $favorites);
        $this->assertEquals('my favorite', $favorites[0]->note);
        $this->assertEquals('/api/test1', $favorites[0]->endpoint->path);
    }

    // ─── OpenAPI 导出 ───

    public function test_export_open_api_returns_valid_spec()
    {
        ApiDocEndpoint::factory()->create([
            'method' => 'GET',
            'path' => '/api/admin/licenses',
            'summary' => 'List licenses',
            'group' => 'licenses',
            'status' => 'active',
        ]);
        ApiDocEndpoint::factory()->create([
            'method' => 'POST',
            'path' => '/api/admin/licenses',
            'summary' => 'Create license',
            'group' => 'licenses',
            'status' => 'active',
        ]);
        ApiDocSchema::factory()->create([
            'name' => 'License',
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
        ]);

        $result = $this->service->exportOpenApi();

        $this->assertEquals(2, $result['endpoint_count']);
        $this->assertNotEmpty($result['spec']);

        $spec = json_decode($result['spec'], true);
        $this->assertEquals('3.0.3', $spec['openapi']);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('/api/admin/licenses', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/admin/licenses']);
        $this->assertArrayHasKey('post', $spec['paths']['/api/admin/licenses']);
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('securitySchemes', $spec['components']);
        $this->assertArrayHasKey('BearerAuth', $spec['components']['securitySchemes']);
    }

    // ─── cURL 片段生成 ───

    public function test_generate_curl_snippet_get_request()
    {
        $endpoint = ApiDocEndpoint::factory()->create([
            'method' => 'GET',
            'path' => '/api/admin/licenses',
        ]);

        $curl = $this->service->generateCurlSnippet($endpoint);

        $this->assertStringContainsString('curl -X GET', $curl);
        $this->assertStringContainsString('/api/admin/licenses', $curl);
        $this->assertStringContainsString('Bearer YOUR_API_KEY', $curl);
    }

    public function test_generate_curl_snippet_post_request_with_body()
    {
        $endpoint = ApiDocEndpoint::factory()->create([
            'method' => 'POST',
            'path' => '/api/admin/licenses',
            'request_body' => ['name' => ['type' => 'string']],
            'example_request' => ['name' => 'test-license'],
        ]);

        $curl = $this->service->generateCurlSnippet($endpoint);

        $this->assertStringContainsString('curl -X POST', $curl);
        $this->assertStringContainsString('-d', $curl);
        $this->assertStringContainsString('test-license', $curl);
    }

    // ─── 自动生成代码片段 ───

    public function test_auto_generate_snippets_creates_snippets()
    {
        $endpoint = ApiDocEndpoint::factory()->create([
            'method' => 'GET',
            'path' => '/api/admin/licenses',
        ]);

        $snippets = $this->service->autoGenerateSnippets($endpoint);

        $this->assertCount(5, $snippets);

        $languages = array_map(fn($s) => $s['language'], $snippets);
        $this->assertContains('curl', $languages);
        $this->assertContains('php', $languages);
        $this->assertContains('javascript', $languages);
        $this->assertContains('python', $languages);
        $this->assertContains('go', $languages);

        // cURL snippet
        $this->assertStringContainsString('curl -X GET', $snippets[0]['code']);
        // PHP snippet
        $this->assertStringContainsString('Http::withHeaders', $snippets[1]['code']);
        // Python snippet
        $this->assertStringContainsString('requests.get', $snippets[3]['code']);
    }

    // ─── 批量端点统计 ───

    public function test_endpoint_stats_returns_correct_counts()
    {
        $user = User::factory()->create();
        $endpoint = ApiDocEndpoint::factory()->create();

        ApiTestRequest::factory()->count(3)->create([
            'endpoint_id' => $endpoint->id,
            'user_id' => $user->id,
            'status' => 'success',
            'response_time_ms' => 100,
        ]);
        ApiTestRequest::factory()->create([
            'endpoint_id' => $endpoint->id,
            'user_id' => $user->id,
            'status' => 'failed',
        ]);
        ApiDocFavorite::factory()->create([
            'user_id' => $user->id,
            'endpoint_id' => $endpoint->id,
        ]);

        $this->assertEquals(4, ApiTestRequest::where('endpoint_id', $endpoint->id)->count());
        $this->assertEquals(1, ApiDocFavorite::where('endpoint_id', $endpoint->id)->count());
    }
}
