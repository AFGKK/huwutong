<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\AnnounceBanner;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AnnounceBannerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_public_active_endpoint_returns_active_banners()
    {
        $banner = AnnounceBanner::factory()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'roles' => null,
        ]);

        // Create inactive banner that should not appear
        AnnounceBanner::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/announce-banners/active');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($banner->id, $response->json('data.0.id'));
    }

    public function test_public_active_endpoint_excludes_out_of_window_banners()
    {
        AnnounceBanner::factory()->create([
            'is_active' => true,
            'starts_at' => now()->addDay(),
            'ends_at' => null,
        ]);

        $response = $this->getJson('/api/announce-banners/active');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_index_requires_authentication()
    {
        $response = $this->getJson('/api/announce-banners');
        $response->assertUnauthorized();
    }

    public function test_index_returns_all_banners()
    {
        AnnounceBanner::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/announce-banners');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_store_creates_banner()
    {
        $data = [
            'title' => '系统维护通知',
            'content' => '系统将于周五晚进行维护',
            'type' => 'warning',
            'position' => 'top',
            'can_close' => true,
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/announce-banners', $data);

        $response->assertCreated();
        $response->assertJsonPath('data.title', '系统维护通知');

        $this->assertDatabaseHas('announce_banners', [
            'title' => '系统维护通知',
            'type' => 'warning',
        ]);
    }

    public function test_store_validates_required_fields()
    {
        $response = $this->actingAs($this->admin)->postJson('/api/announce-banners', []);

        $response->assertStatus(422);
        $this->assertArrayHasKey('title', $response->json('error.details') ?? []);
    }

    public function test_show_returns_banner()
    {
        $banner = AnnounceBanner::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/announce-banners/{$banner->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $banner->id);
    }

    public function test_update_modifies_banner()
    {
        $banner = AnnounceBanner::factory()->create([
            'title' => 'Old Title',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/announce-banners/{$banner->id}", [
            'title' => 'New Title',
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New Title');

        $this->assertDatabaseHas('announce_banners', [
            'id' => $banner->id,
            'title' => 'New Title',
            'is_active' => false,
        ]);
    }

    public function test_destroy_deletes_banner()
    {
        $banner = AnnounceBanner::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson("/api/announce-banners/{$banner->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('announce_banners', ['id' => $banner->id]);
    }
}
