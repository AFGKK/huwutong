<?php

namespace Tests\Feature\Public;

use App\Models\MarketplaceApp;
use App\Models\MaintenanceConfig;
use App\Services\MaintenanceModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintenanceAndMarketplaceLinkageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function maintenance_mode_blocks_public_pages_and_apis(): void
    {
        MaintenanceConfig::create([
            'is_enabled' => true,
            'title' => '维护中',
            'message' => '计划维护',
            'retry_after' => 90,
            'whitelist_ips' => [],
            'whitelist_paths' => [],
        ]);
        app(MaintenanceModeService::class)->clearCache();

        $this->get('/pricing')
            ->assertStatus(503)
            ->assertSee('计划维护', false);

        $this->getJson('/api/public/pricing-plans')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'MAINTENANCE_MODE');
    }

    #[Test]
    public function maintenance_status_and_health_remain_reachable(): void
    {
        MaintenanceConfig::create([
            'is_enabled' => true,
            'title' => '维护中',
            'message' => '计划维护',
            'retry_after' => 60,
        ]);
        app(MaintenanceModeService::class)->clearCache();

        $this->getJson('/api/maintenance/status')->assertOk();
    }

    #[Test]
    public function marketplace_lists_published_apps_only(): void
    {
        $published = MarketplaceApp::factory()->published()->create([
            'name' => '公开插件甲',
            'slug' => 'public-plugin-a',
            'short_description' => '已上架应用',
        ]);
        MarketplaceApp::factory()->create([
            'name' => '草稿插件',
            'slug' => 'draft-plugin',
            'status' => 'draft',
        ]);

        $html = $this->get('/marketplace')->assertOk()->getContent();
        $this->assertStringContainsString('公开插件甲', $html);
        $this->assertStringContainsString('/marketplace/public-plugin-a', $html);
        $this->assertStringNotContainsString('草稿插件', $html);

        $this->get('/marketplace/'.$published->slug)
            ->assertOk()
            ->assertSee('公开插件甲', false);

        $this->getJson('/api/public/marketplace/apps')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->get('/')->assertOk()->assertSee('/marketplace', false);
    }
}
