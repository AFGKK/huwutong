<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function createPage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'slug' => 'test-page',
            'title' => '测试页面',
            'content' => '<h1>内容</h1>',
            'locale' => 'zh-CN',
            'status' => 'draft',
            'meta' => ['title' => '测试', 'description' => '描述', 'keywords' => 'kw'],
        ], $overrides));
    }

    // ─── CRUD ───

    public function test_index_returns_paginated_pages(): void
    {
        $this->createPage();

        $response = $this->getJson('/api/pages', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => [['id', 'slug', 'title', 'status']], 'meta' => ['total']]);
    }

    public function test_store_creates_page(): void
    {
        $response = $this->postJson('/api/pages', [
            'slug' => 'privacy',
            'title' => '隐私政策',
            'content' => '<h2>隐私政策内容</h2>',
            'locale' => 'zh-CN',
            'status' => 'published',
            'meta' => ['title' => '隐私', 'description' => '隐私政策说明'],
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('data.slug', 'privacy');
        $this->assertDatabaseHas('pages', ['slug' => 'privacy']);
    }

    public function test_show_returns_page(): void
    {
        $page = $this->createPage();

        $response = $this->getJson("/api/pages/{$page->id}", $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $page->id);
    }

    public function test_update_modifies_page(): void
    {
        $page = $this->createPage();

        $response = $this->putJson("/api/pages/{$page->id}", [
            'title' => '更新标题',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'title' => '更新标题',
        ]);
    }

    public function test_publish_changes_status(): void
    {
        $page = $this->createPage(['status' => 'draft']);

        $response = $this->postJson("/api/pages/{$page->id}/publish", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'status' => 'published']);
    }

    public function test_draft_reverts_status(): void
    {
        $page = $this->createPage(['status' => 'published']);

        $response = $this->postJson("/api/pages/{$page->id}/draft", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'status' => 'draft']);
    }

    public function test_destroy_deletes_page(): void
    {
        $page = $this->createPage();

        $response = $this->deleteJson("/api/pages/{$page->id}", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    // ─── 公开端点 ───

    public function test_show_by_slug_returns_published_page(): void
    {
        $this->createPage(['slug' => 'about-us', 'status' => 'published']);

        $response = $this->getJson('/api/pages/public/about-us');

        $response->assertStatus(200);
        $response->assertJsonPath('data.slug', 'about-us');
    }

    public function test_show_by_slug_returns_404_for_draft(): void
    {
        $this->createPage(['slug' => 'draft-page', 'status' => 'draft']);

        $response = $this->getJson('/api/pages/public/draft-page');

        $response->assertStatus(404);
    }

    public function test_show_by_slug_returns_404_for_unknown(): void
    {
        $response = $this->getJson('/api/pages/public/non-existent');

        $response->assertStatus(404);
    }
}
