<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\MarketplaceApp;
use App\Models\MarketplaceDeveloper;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OpenPlatformApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
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
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_stats_returns_marketplace_counts(): void
    {
        $developer = MarketplaceDeveloper::factory()->create();
        MarketplaceDeveloper::factory()->pending()->create();
        MarketplaceApp::factory()->published()->count(3)->create(['developer_id' => $developer->id]);
        MarketplaceApp::factory()->pendingReview()->create(['developer_id' => $developer->id]);

        $response = $this->getJson('/api/open-platform/stats', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_developers', 2)
            ->assertJsonPath('data.pending_developers', 1)
            ->assertJsonPath('data.published_apps', 3)
            ->assertJsonPath('data.pending_review_apps', 1);
    }

    public function test_metadata_returns_categories_and_statuses(): void
    {
        $response = $this->getJson('/api/open-platform/metadata', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['categories', 'app_statuses', 'pricing_types'],
            ]);
    }

    public function test_apps_lists_paginated(): void
    {
        MarketplaceApp::factory()->count(3)->create();

        $response = $this->getJson('/api/open-platform/apps?per_page=2', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_pending_apps_only_returns_pending_review(): void
    {
        MarketplaceApp::factory()->published()->create();
        MarketplaceApp::factory()->pendingReview()->count(2)->create();

        $response = $this->getJson('/api/open-platform/apps/pending', $this->authHeaders());

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_verify_developer_approves_pending(): void
    {
        $developer = MarketplaceDeveloper::factory()->pending()->create();

        $response = $this->postJson(
            "/api/open-platform/developers/{$developer->id}/verify",
            ['action' => 'approve', 'notes' => 'Verified'],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_review_app_approves_pending_review(): void
    {
        $app = MarketplaceApp::factory()->pendingReview()->create([
            'current_version' => '1.0.0',
        ]);

        $response = $this->postJson(
            "/api/open-platform/apps/{$app->id}/review",
            ['action' => 'approve', 'notes' => 'Ship it'],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_marketplace_lists_published_apps(): void
    {
        MarketplaceApp::factory()->published()->count(2)->create();
        MarketplaceApp::factory()->pendingReview()->create();

        $response = $this->getJson('/api/open-platform/marketplace', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/open-platform/stats')->assertStatus(401);
    }
}
