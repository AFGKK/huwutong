<?php

namespace Tests\Unit\Services;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceDeveloper;
use App\Models\User;
use App\Services\OpenPlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenPlatformServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OpenPlatformService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OpenPlatformService::class);
        $this->user = User::factory()->create();
    }

    public function test_register_developer(): void
    {
        $developer = $this->service->registerDeveloper($this->user, [
            'display_name' => 'Test Dev',
            'company_name' => 'Test Co',
        ]);

        $this->assertEquals('pending', $developer->status);
        $this->assertEquals('Test Dev', $developer->display_name);
    }

    public function test_verify_developer_approve(): void
    {
        $developer = MarketplaceDeveloper::factory()->pending()->create();
        $admin = User::factory()->create();

        $result = $this->service->verifyDeveloper($developer, $admin, 'approve');

        $this->assertEquals('active', $result->status);
        $this->assertNotNull($result->verified_at);
    }

    public function test_create_and_submit_app(): void
    {
        $developer = MarketplaceDeveloper::factory()->create();

        $app = $this->service->createApp($developer, [
            'name' => 'Webhook Sync',
            'category' => 'integration',
            'version' => '1.0.0',
            'changelog' => 'Initial',
        ]);

        $this->assertEquals('draft', $app->status);
        $this->assertEquals('1.0.0', $app->current_version);

        $submitted = $this->service->submitForReview($app);
        $this->assertEquals('pending_review', $submitted->status);
    }

    public function test_review_app_approve(): void
    {
        $app = MarketplaceApp::factory()->pendingReview()->create([
            'current_version' => '1.0.0',
        ]);
        $admin = User::factory()->create();

        $result = $this->service->reviewApp($app, $admin, 'approve', 'Looks good');

        $this->assertEquals('published', $result->status);
        $this->assertNotNull($result->published_at);
    }

    public function test_install_published_app(): void
    {
        $app = MarketplaceApp::factory()->published()->create(['current_version' => '1.0.0']);
        $user = User::factory()->create();

        $installation = $this->service->installApp($app, $user);

        $this->assertEquals('active', $installation->status);
        $this->assertEquals(1, $app->fresh()->install_count);
    }

    public function test_cannot_install_unpublished_app(): void
    {
        $app = MarketplaceApp::factory()->create(['status' => 'draft', 'current_version' => '1.0.0']);
        $user = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->service->installApp($app, $user);
    }

    public function test_get_stats(): void
    {
        $developer = MarketplaceDeveloper::factory()->create();
        MarketplaceDeveloper::factory()->create();
        MarketplaceApp::factory()->published()->count(3)->create(['developer_id' => $developer->id]);

        $stats = $this->service->getStats();

        $this->assertGreaterThanOrEqual(2, $stats['total_developers']);
        $this->assertGreaterThanOrEqual(3, $stats['published_apps']);
    }
}
