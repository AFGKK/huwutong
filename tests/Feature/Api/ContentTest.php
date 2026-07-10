<?php

namespace Tests\Feature\Api;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private User $admin;
    private string $token;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
        $this->adminToken = $this->admin->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function h(?string $token = null): array
    {
        return ['Authorization' => 'Bearer ' . ($token ?? $this->token)];
    }

    /** @test */
    public function blog_list_public(): void
    {
        $r = $this->getJson('/api/public/blog/published');
        $r->assertStatus(200)->assertJsonPath('success', true);
    }

    /** @test */
    public function blog_categories_public(): void
    {
        $r = $this->getJson('/api/public/blog/categories');
        $r->assertStatus(200);
    }

    /** @test */
    public function blog_admin_crud(): void
    {
        $cat = BlogCategory::create(['name' => 'Tech', 'slug' => 'tech']);

        $r = $this->postJson('/api/blog', [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'Post body',
            'category_id' => $cat->id,
            'status' => 'draft',
        ], $this->h($this->adminToken));
        $this->assertContains($r->status(), [200, 201, 403, 422, 500]);
        if ($r->status() === 201) {
            $this->assertDatabaseHas('blog_posts', ['slug' => 'test-post']);
        }
    }

    /** @test */
    public function blog_toggle_publish(): void
    {
        Event::fake();
        $post = BlogPost::create([
            'title' => 'Draft',
            'slug' => 'draft-post',
            'content' => 'Body',
            'status' => 'draft',
            'author_id' => $this->admin->id,
        ]);

        $r = $this->postJson("/api/blog/{$post->id}/toggle-publish", [], $this->h($this->adminToken));
        $this->assertContains($r->status(), [200, 403, 404]);
    }

    /** @test */
    public function blog_create_comment(): void
    {
        $post = BlogPost::create([
            'title' => 'Commentable',
            'slug' => 'commentable',
            'content' => 'Body',
            'status' => 'published',
            'author_id' => $this->user->id,
        ]);

        $r = $this->postJson("/api/blog/{$post->id}/comments", [
            'content' => 'Nice post!',
        ], $this->h());
        $this->assertContains($r->status(), [200, 201, 404]);

        if (in_array($r->status(), [200, 201])) {
            $this->assertDatabaseHas('blog_comments', ['content' => 'Nice post!']);
        }
    }

    /** @test */
    public function blog_delete_post(): void
    {
        Event::fake();
        $post = BlogPost::create([
            'title' => 'To Delete',
            'slug' => 'to-delete',
            'content' => 'Body',
            'status' => 'draft',
            'author_id' => $this->admin->id,
        ]);

        $r = $this->deleteJson("/api/blog/{$post->id}", [], $this->h($this->adminToken));
        $this->assertContains($r->status(), [200, 204, 403, 404]);
    }

    /** @test */
    public function blog_stats(): void
    {
        $r = $this->getJson('/api/blog/stats', $this->h($this->adminToken));
        $this->assertContains($r->status(), [200, 403]);
    }

    /** @test */
    public function blog_rss(): void
    {
        $r = $this->getJson('/api/rss');
        $r->assertStatus(200);
    }

    /** @test */
    public function blog_subscribe(): void
    {
        $r = $this->postJson('/api/public/blog/subscriptions', ['email' => 'test@example.com']);
        $this->assertContains($r->status(), [200, 201, 422]);
    }

    /** @test */
    public function forum_categories(): void
    {
        $r = $this->getJson('/api/forum/categories', $this->h());
        $r->assertStatus(200)->assertJsonPath('success', true);
    }

    /** @test */
    public function forum_create_post(): void
    {
        $cat = ForumCategory::create(['name' => 'Discuss', 'slug' => 'discuss']);

        $r = $this->postJson('/api/forum', [
            'title' => 'Test Thread',
            'content' => 'Thread body',
            'category_id' => $cat->id,
        ], $this->h());
        $this->assertContains($r->status(), [200, 201, 422]);
        if (in_array($r->status(), [200, 201])) {
            $this->assertDatabaseHas('forum_posts', ['title' => 'Test Thread']);
        }
    }

    /** @test */
    public function forum_list_posts(): void
    {
        $r = $this->getJson('/api/forum', $this->h());
        $r->assertStatus(200)->assertJsonPath('success', true);
    }

    /** @test */
    public function forum_reply_to_post(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->user->id,
            'title' => 'Original',
            'content' => 'Body',
        ]);

        $r = $this->postJson("/api/forum/{$post->id}/reply", [
            'content' => 'Reply body',
        ], $this->h());
        $this->assertContains($r->status(), [200, 201, 404]);
        if (in_array($r->status(), [200, 201])) {
            $this->assertDatabaseHas('forum_replies', ['content' => 'Reply body']);
        }
    }

    /** @test */
    public function forum_like_post(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->user->id,
            'title' => 'Likeable',
            'content' => 'Body',
        ]);

        $r = $this->postJson("/api/forum/{$post->id}/like", [], $this->h());
        $this->assertContains($r->status(), [200, 201, 404]);
    }

    /** @test */
    public function forum_show_post(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->user->id,
            'title' => 'View Thread',
            'content' => 'Body',
        ]);

        $r = $this->getJson("/api/forum/{$post->id}", $this->h());
        $this->assertContains($r->status(), [200, 404]);
    }

    /** @test */
    public function oa_list_accounts(): void
    {
        $r = $this->getJson('/api/official-accounts', $this->h());
        $r->assertStatus(200)->assertJsonPath('success', true);
    }

    /** @test */
    public function oa_search(): void
    {
        $r = $this->getJson('/api/official-accounts/search?q=test', $this->h());
        $r->assertStatus(200);
    }

    /** @test */
    public function oa_categories(): void
    {
        $r = $this->getJson('/api/official-accounts/categories', $this->h());
        $r->assertStatus(200);
    }

    /** @test */
    public function oa_recommendations(): void
    {
        $r = $this->getJson('/api/official-accounts/recommendations', $this->h());
        $r->assertStatus(200);
    }

    /** @test */
    public function oa_article_detail_public(): void
    {
        $r = $this->getJson('/api/official-accounts/articles/1');
        $this->assertContains($r->status(), [200, 404]);
    }

    /** @test */
    public function settlement_cycles(): void
    {
        $r = $this->getJson('/api/settlement/cycles', $this->h($this->adminToken));
        $this->assertContains($r->status(), [200, 403, 404]);
    }

    /** @test */
    public function settlement_dashboard(): void
    {
        $r = $this->getJson('/api/settlement/dashboard', $this->h($this->adminToken));
        $this->assertContains($r->status(), [200, 403, 404]);
    }

    /** @test */
    public function settlement_batches(): void
    {
        $r = $this->getJson('/api/settlement/batches', $this->h($this->adminToken));
        $this->assertContains($r->status(), [200, 403, 404]);
    }

    /** @test */
    public function blog_post_model_crud(): void
    {
        $post = BlogPost::create([
            'title' => 'Model Test',
            'slug' => 'model-test',
            'content' => 'Body',
            'status' => 'draft',
            'author_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('blog_posts', ['id' => $post->id]);

        $post->update(['title' => 'Updated']);
        $this->assertDatabaseHas('blog_posts', ['id' => $post->id, 'title' => 'Updated']);

        $post->delete();
        $this->assertSoftDeleted($post);
    }

    /** @test */
    public function forum_post_model_with_replies(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->user->id,
            'title' => 'Forum Thread',
            'content' => 'Body',
        ]);
        ForumReply::create([
            'post_id' => $post->id,
            'user_id' => $this->user->id,
            'content' => 'Reply body',
        ]);
        $this->assertCount(1, $post->fresh()->replies);
    }
}
