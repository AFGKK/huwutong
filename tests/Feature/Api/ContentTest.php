<?php

namespace Tests\Feature\Api;

use App\Models\BlogPost;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\OfficialAccount;
use App\Models\OaArticle;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * 内容模块 Feature 测试（T-08）
 *
 * 覆盖范围（不重复 BlogApiFeatureTest 已有内容）:
 * 1. Blog 评论 — 创建评论、列表、认证保护
 * 2. 互物号（OfficialAccount）— 列表、搜索、分类、文章详情
 * 3. Forum 论坛 — 帖子 CRUD、回复、点赞、软删除
 */
class ContentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private User $otherUser;
    private string $token;
    private string $otherToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
        $this->otherToken = $this->otherUser->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function h(?string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    // ──────────────────────────────────────────
    //  Blog 评论
    // ──────────────────────────────────────────

    /** @test */
    public function blog_public_comment_list_works(): void
    {
        $post = BlogPost::factory()->published()->create();

        $response = $this->getJson("/api/public/blog/id/{$post->id}/comments");

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    /** @test */
    public function blog_authenticated_user_can_create_comment(): void
    {
        $post = BlogPost::factory()->published()->create();

        $response = $this->postJson("/api/public/blog/id/{$post->id}/comments", [
            'content' => 'Nice article!',
        ], $this->h());

        $response->assertStatus(201);
        $this->assertDatabaseHas('blog_comments', [
            'blog_id' => $post->id,
            'user_id' => $this->user->id,
            'content' => 'Nice article!',
        ]);
    }

    /** @test */
    public function blog_comment_requires_auth(): void
    {
        $post = BlogPost::factory()->published()->create();

        $response = $this->postJson("/api/public/blog/id/{$post->id}/comments", [
            'content' => 'Should fail',
        ]);

        $response->assertStatus(401);
    }

    // ──────────────────────────────────────────
    //  互物号（Official Account）
    // ──────────────────────────────────────────

    /** @test */
    public function oa_list_accounts_returns_list(): void
    {
        OfficialAccount::create([
            'name' => '号1', 'slug' => 'hao-1', 'description' => 'd1', 'owner_id' => $this->user->id, 'status' => 'active',
        ]);
        OfficialAccount::create([
            'name' => '号2', 'slug' => 'hao-2', 'description' => 'd2', 'owner_id' => $this->user->id, 'status' => 'active',
        ]);

        $response = $this->getJson('/api/official-accounts', $this->h());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    /** @test */
    public function oa_search_returns_results(): void
    {
        OfficialAccount::create([
            'name' => '测试互物号', 'slug' => 'test-oa', 'description' => '测试用', 'owner_id' => $this->user->id, 'status' => 'active',
        ]);

        $response = $this->getJson('/api/official-accounts/search?q=测试', $this->h());

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    /** @test */
    public function oa_categories_returns_list(): void
    {
        $response = $this->getJson('/api/official-accounts/categories', $this->h());

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    /** @test */
    public function oa_recommendations_returns_results(): void
    {
        $response = $this->getJson('/api/official-accounts/recommendations', $this->h());

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    /** @test */
    public function oa_article_detail_public_shows_published(): void
    {
        $account = OfficialAccount::create([
            'name' => '测试号', 'slug' => 'test-slug', 'description' => 'desc', 'owner_id' => $this->user->id, 'status' => 'active',
        ]);
        $article = OaArticle::create([
            'account_id' => $account->id,
            'title' => '测试文章',
            'content' => '正文内容',
            'status' => 'published',
        ]);

        $response = $this->getJson("/api/official-accounts/articles/{$article->id}");

        $response->assertOk();
        $this->assertEquals($article->id, $response->json('data.id'));
    }

    /** @test */
    public function oa_article_detail_public_returns_404_for_missing(): void
    {
        $response = $this->getJson('/api/official-accounts/articles/99999');

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────
    //  Forum 论坛
    // ──────────────────────────────────────────

    /** @test */
    public function forum_categories_returns_list(): void
    {
        ForumCategory::create(['name' => '讨论', 'slug' => 'discuss']);

        $response = $this->getJson('/api/forum/categories', $this->h());

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    /** @test */
    public function forum_create_post_saves_thread(): void
    {
        $category = ForumCategory::create(['name' => '技术', 'slug' => 'tech']);

        $response = $this->postJson('/api/forum', [
            'title' => '测试帖子',
            'content' => '这是内容',
            'category_id' => $category->id,
        ], $this->h());

        $response->assertStatus(201);
        $this->assertDatabaseHas('forum_posts', [
            'title' => '测试帖子',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function forum_list_posts_returns_paginated(): void
    {
        ForumPost::create(['user_id' => $this->user->id, 'title' => '贴1', 'content' => 'c1']);
        ForumPost::create(['user_id' => $this->user->id, 'title' => '贴2', 'content' => 'c2']);

        $response = $this->getJson('/api/forum', $this->h());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertArrayHasKey('data', $response->json());
    }

    /** @test */
    public function forum_reply_to_post_creates_reply(): void
    {
        $post = ForumPost::create(['user_id' => $this->user->id, 'title' => '原帖', 'content' => '正文']);

        $response = $this->postJson("/api/forum/{$post->id}/reply", [
            'content' => '好文!',
        ], $this->h());

        $response->assertStatus(201);
        $this->assertDatabaseHas('forum_replies', [
            'post_id' => $post->id,
            'user_id' => $this->user->id,
            'content' => '好文!',
        ]);
    }

    /** @test */
    public function forum_like_post_toggles_like(): void
    {
        $post = ForumPost::create(['user_id' => $this->user->id, 'title' => '点赞帖', 'content' => '正文']);

        $response = $this->postJson("/api/forum/{$post->id}/like", [], $this->h());

        $response->assertOk();
    }

    /** @test */
    public function forum_show_post_returns_detail(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->user->id,
            'title' => '查看帖子',
            'content' => '详情',
        ]);

        $response = $this->getJson("/api/forum/{$post->id}", $this->h());

        $response->assertOk();
        $this->assertEquals('查看帖子', $response->json('data.title'));
    }

    /** @test */
    public function forum_delete_own_post_soft_deletes(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->user->id,
            'title' => '删除我',
            'content' => '正文',
        ]);

        $response = $this->deleteJson("/api/forum/{$post->id}", [], $this->h());

        $response->assertOk();
        $this->assertSoftDeleted($post);
    }

    /** @test */
    public function forum_post_with_replies_count(): void
    {
        $post = ForumPost::create(['user_id' => $this->user->id, 'title' => '统计', 'content' => '正文']);
        ForumReply::create(['post_id' => $post->id, 'user_id' => $this->user->id, 'content' => '回复1']);
        ForumReply::create(['post_id' => $post->id, 'user_id' => $this->user->id, 'content' => '回复2']);

        $this->assertCount(2, $post->fresh()->replies);
    }

    /** @test */
    public function forum_requires_auth(): void
    {
        $response = $this->getJson('/api/forum');
        $response->assertStatus(401);
    }
}
