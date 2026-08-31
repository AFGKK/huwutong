<?php

namespace Tests\Feature\Api;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogSubscription;
use App\Models\RssFeed;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Blog / 互物号 / 结算 Feature Test
 *
 * 覆盖范围:
 * 1. 公开 Blog API（列表/详情/RSS/订阅/分类）
 * 2. 管理端 Blog CRUD（需认证）
 * 3. 文章社交互动（点赞/收藏/评论）
 */
class BlogApiFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->adminToken = $this->admin->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    // ─── 公开 API (public/blog) ───

    public function test_public_blog_categories_returns_list(): void
    {
        BlogCategory::factory()->create(['name' => '开发教程', 'is_active' => true]);
        BlogCategory::factory()->create(['name' => '产品更新', 'is_active' => true]);

        $response = $this->getJson('/api/public/blog/categories');

        $response->assertOk();
        $response->assertJsonFragment(['name' => '开发教程']);
        $response->assertJsonFragment(['name' => '产品更新']);
    }

    public function test_public_blog_published_list_returns_published_posts(): void
    {
        BlogPost::factory()->published()->create([
            'title' => '公开文章',
            'type' => 'blog',
        ]);
        BlogPost::factory()->draft()->create([
            'title' => '草稿文章',
        ]);

        $response = $this->getJson('/api/public/blog/published');

        $response->assertOk();
        $response->assertJsonFragment(['title' => '公开文章']);
        $response->assertJsonMissing(['title' => '草稿文章']);
    }

    public function test_public_blog_published_list_filters_by_type(): void
    {
        BlogPost::factory()->published()->create([
            'title' => '博客文章',
            'type' => 'blog',
        ]);
        BlogPost::factory()->published()->changelog()->create([
            'title' => '更新日志',
        ]);

        $response = $this->getJson('/api/public/blog/published/changelog');

        $response->assertOk();
        $response->assertJsonFragment(['title' => '更新日志']);
        $response->assertJsonMissing(['title' => '博客文章']);
    }

    public function test_public_blog_show_by_slug(): void
    {
        BlogPost::factory()->published()->create([
            'title' => '测试文章',
            'slug' => 'test-article',
        ]);

        $response = $this->getJson('/api/public/blog/test-article');

        $response->assertOk();
        $response->assertJsonPath('data.title', '测试文章');
    }

    public function test_public_blog_show_by_slug_returns_404_for_draft(): void
    {
        BlogPost::factory()->draft()->create([
            'slug' => 'draft-post',
        ]);

        $response = $this->getJson('/api/public/blog/draft-post');

        $response->assertStatus(404);
    }

    public function test_public_blog_changelog_versions(): void
    {
        BlogPost::factory()->published()->changelog()->create(['version' => 'v2.0.0']);
        BlogPost::factory()->published()->changelog()->create(['version' => 'v1.9.0']);

        $response = $this->getJson('/api/public/blog/changelog/versions');

        $response->assertOk();
    }

    public function test_public_rss_endpoint_returns_xml(): void
    {
        RssFeed::factory()->create(['feed_type' => 'blog', 'title' => '开发者博客']);
        BlogPost::factory()->published()->count(3)->create();

        $response = $this->get('/api/rss/blog');

        $response->assertOk();
        $response->assertSee('<rss version="2.0"', false);
    }

    public function test_public_blog_subscribe_creates_subscription(): void
    {
        $response = $this->postJson('/api/public/blog/subscriptions', [
            'email' => 'test@example.com',
            'subscribed_types' => ['blog', 'changelog'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('blog_subscriptions', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_public_blog_related_posts(): void
    {
        $cat = BlogCategory::factory()->create();
        $main = BlogPost::factory()->published()->create([
            'title' => '主文章',
            'category_id' => $cat->id,
            'tags' => ['php', 'laravel'],
        ]);
        BlogPost::factory()->published()->create([
            'title' => '相关文章',
            'category_id' => $cat->id,
            'tags' => ['php'],
        ]);

        $response = $this->getJson("/api/public/blog/{$main->id}/related");

        $response->assertOk();
        $response->assertJsonFragment(['title' => '相关文章']);
    }

    // ─── 社交互动（需 auth） ───

    public function test_authenticated_user_can_like_post(): void
    {
        $post = BlogPost::factory()->published()->create();

        $response = $this->postJson("/api/public/blog/{$post->id}/like", [], $this->authHeaders());

        $response->assertOk();
        $this->assertDatabaseHas('likes', [
            'likeable_id' => $post->id,
            'likeable_type' => BlogPost::class,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_authenticated_user_can_toggle_favorite(): void
    {
        $post = BlogPost::factory()->published()->create();

        $response = $this->postJson("/api/public/blog/{$post->id}/favorite", [], $this->authHeaders());

        $response->assertOk();
        $this->assertDatabaseHas('favorites', [
            'favorable_id' => $post->id,
            'favorable_type' => BlogPost::class,
            'user_id' => $this->admin->id,
        ]);
    }

    // ─── 管理端 Blog CRUD ───

    public function test_admin_can_list_posts(): void
    {
        BlogPost::factory()->count(3)->create();

        $response = $this->getJson('/api/blog', $this->authHeaders());

        $response->assertOk();
    }

    public function test_admin_can_create_post(): void
    {
        $response = $this->postJson('/api/blog', [
            'title' => '新文章',
            'type' => 'blog',
            'content' => '文章内容',
        ], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertDatabaseHas('blog_posts', ['title' => '新文章']);
    }

    public function test_admin_can_show_post(): void
    {
        $post = BlogPost::factory()->create(['title' => '查看文章']);

        $response = $this->getJson("/api/blog/{$post->id}", $this->authHeaders());

        $response->assertOk();
        $response->assertJsonFragment(['title' => '查看文章']);
    }

    public function test_admin_can_update_post(): void
    {
        $post = BlogPost::factory()->create(['title' => '原标题']);

        $response = $this->putJson("/api/blog/{$post->id}", [
            'title' => '更新标题',
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertDatabaseHas('blog_posts', ['title' => '更新标题']);
    }

    public function test_admin_can_delete_post(): void
    {
        $post = BlogPost::factory()->create();

        $response = $this->deleteJson("/api/blog/{$post->id}", [], $this->authHeaders());

        $response->assertOk();
        $this->assertSoftDeleted('blog_posts', ['id' => $post->id]);
    }

    public function test_admin_can_toggle_publish(): void
    {
        $post = BlogPost::factory()->draft()->create();

        $response = $this->postJson("/api/blog/{$post->id}/toggle-publish", [], $this->authHeaders());

        $response->assertOk();
        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'is_published' => true,
        ]);
    }

    public function test_admin_can_toggle_featured(): void
    {
        $post = BlogPost::factory()->create(['is_featured' => false]);

        $response = $this->postJson("/api/blog/{$post->id}/toggle-featured", [], $this->authHeaders());

        $response->assertOk();
        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'is_featured' => true,
        ]);
    }

    public function test_admin_can_get_stats(): void
    {
        BlogPost::factory()->published()->count(2)->create();
        BlogPost::factory()->draft()->create();

        $response = $this->getJson('/api/blog/stats', $this->authHeaders());

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['total', 'published', 'drafts', 'by_type'],
        ]);
        $this->assertEquals(2, $response->json('data.published'));
        $this->assertEquals(1, $response->json('data.drafts'));
    }

    public function test_admin_can_manage_categories(): void
    {
        // Create
        $createResp = $this->postJson('/api/blog/categories', [
            'name' => '新分类',
            'slug' => 'new-cat',
        ], $this->authHeaders());
        $createResp->assertStatus(201);
        $this->assertDatabaseHas('blog_categories', ['name' => '新分类']);

        // Update
        $cat = BlogCategory::where('slug', 'new-cat')->first();
        $updateResp = $this->putJson("/api/blog/categories/{$cat->id}", [
            'name' => '更新分类',
        ], $this->authHeaders());
        $updateResp->assertOk();
        $this->assertDatabaseHas('blog_categories', ['name' => '更新分类']);

        // Delete
        $deleteResp = $this->deleteJson("/api/blog/categories/{$cat->id}", [], $this->authHeaders());
        $deleteResp->assertOk();
        $this->assertDatabaseMissing('blog_categories', ['id' => $cat->id]);
    }

    public function test_unauthenticated_request_to_admin_blog_returns_401(): void
    {
        $response = $this->getJson('/api/blog');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_request_to_like_returns_401(): void
    {
        $post = BlogPost::factory()->published()->create();

        $response = $this->postJson("/api/public/blog/{$post->id}/like");
        $response->assertStatus(401);
    }
}
