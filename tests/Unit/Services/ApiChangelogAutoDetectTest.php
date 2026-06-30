<?php

namespace Tests\Unit\Services;

use App\Models\ApiChangelog;
use App\Models\ApiDocEndpoint;
use App\Models\ApiEndpointSnapshot;
use App\Models\ApiVersion;
use App\Services\ApiDocsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiChangelogAutoDetectTest extends TestCase
{
    use RefreshDatabase;

    protected ApiDocsService $service;
    protected ApiVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ApiDocsService::class);

        $this->version = ApiVersion::create([
            'version' => 'v2.1.0',
            'base_path' => '/api/v2.1',
            'name' => 'v2.1',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    /** @test */
    public function can_create_endpoint_snapshot()
    {
        $ep1 = ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'GET',
            'path' => '/api/users',
            'summary' => 'List users',
            'group' => 'users',
            'status' => 'active',
        ]);

        $ep2 = ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'POST',
            'path' => '/api/users',
            'summary' => 'Create user',
            'group' => 'users',
            'status' => 'active',
        ]);

        $count = $this->service->createSnapshot($this->version->id, 'v2.1.0');

        $this->assertEquals(2, $count);
        $this->assertEquals(2, ApiEndpointSnapshot::where('api_version_id', $this->version->id)->count());
    }

    /** @test */
    public function first_auto_detect_creates_snapshot()
    {
        ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'GET',
            'path' => '/api/health',
            'summary' => 'Health check',
            'status' => 'active',
        ]);

        $result = $this->service->autoGenerateChangelog($this->version->id);

        $this->assertEquals('snapshot_created', $result['status']);
        $this->assertEquals(0, $result['changelogs_created']);
    }

    /** @test */
    public function detects_new_endpoints()
    {
        // First snapshot
        ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'GET',
            'path' => '/api/users',
            'summary' => 'List users',
            'status' => 'active',
        ]);
        $this->service->createSnapshot($this->version->id, 'v2.0.0');

        // Add a new endpoint
        ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'POST',
            'path' => '/api/users',
            'summary' => 'Create user',
            'status' => 'active',
        ]);

        $result = $this->service->autoGenerateChangelog($this->version->id);

        $this->assertGreaterThanOrEqual(1, $result['changelogs_created']);
        $this->assertGreaterThanOrEqual(1, $result['changes']['added']);
        $this->assertCount(1, $result['added']);
    }

    /** @test */
    public function detects_removed_endpoints()
    {
        $ep = ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'DELETE',
            'path' => '/api/old-endpoint',
            'summary' => 'Old deprecated endpoint',
            'status' => 'deprecated',
        ]);
        $this->service->createSnapshot($this->version->id, 'v2.0.0');

        // Remove the endpoint
        $ep->delete();

        $result = $this->service->autoGenerateChangelog($this->version->id);

        $this->assertGreaterThanOrEqual(1, $result['changelogs_created']);
        $this->assertGreaterThanOrEqual(1, $result['changes']['removed']);
    }

    /** @test */
    public function creates_changelog_entries_from_auto_detect()
    {
        ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'GET',
            'path' => '/api/users',
            'status' => 'active',
        ]);
        $this->service->createSnapshot($this->version->id, 'v2.0.0');

        ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'POST',
            'path' => '/api/users',
            'summary' => 'Create user',
            'status' => 'active',
        ]);

        $this->service->autoGenerateChangelog($this->version->id);

        $autoLogs = ApiChangelog::where('source', 'auto_detect')->get();

        $this->assertGreaterThanOrEqual(1, $autoLogs->count());
        $this->assertEquals('new', $autoLogs->first()->type);
    }

    /** @test */
    public function auto_detect_history_returns_auto_generated_logs()
    {
        ApiChangelog::create([
            'version' => 'v2.1.0',
            'release_date' => now(),
            'type' => 'new',
            'title' => 'Auto detected changes',
            'description' => 'Added new endpoint GET /api/users',
            'source' => 'auto_detect',
        ]);
        ApiChangelog::create([
            'version' => 'v2.1.0',
            'release_date' => now(),
            'type' => 'update',
            'title' => 'Manual update',
            'source' => 'manual',
        ]);

        $history = $this->service->getAutoDetectionHistory();

        $this->assertCount(1, $history);
        $this->assertEquals('auto_detect', $history[0]->source);
    }

    /** @test */
    public function detects_multiple_change_types_simultaneously()
    {
        // Initial state: 2 endpoints
        $ep1 = ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'GET',
            'path' => '/api/users',
            'summary' => 'List users',
            'status' => 'active',
        ]);
        ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'DELETE',
            'path' => '/api/legacy',
            'status' => 'deprecated',
        ]);
        $this->service->createSnapshot($this->version->id, 'v2.0.0');

        // New state: add 1, remove 1, change 1
        ApiDocEndpoint::create([
            'api_version_id' => $this->version->id,
            'method' => 'POST',
            'path' => '/api/users',
            'summary' => 'Create user',
            'status' => 'active',
        ]);
        $ep1->update(['summary' => 'List all users with pagination']);
        ApiDocEndpoint::where('path', '/api/legacy')->delete();

        $result = $this->service->autoGenerateChangelog($this->version->id);

        $this->assertGreaterThanOrEqual(1, $result['changes']['added']);
        $this->assertGreaterThanOrEqual(1, $result['changes']['changed']);
        $this->assertGreaterThanOrEqual(1, $result['changes']['removed']);
        $this->assertEquals(3, $result['changelogs_created']);
    }
}
