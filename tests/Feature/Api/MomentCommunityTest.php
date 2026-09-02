<?php

namespace Tests\Feature\Api;

use App\Models\ForumLike;
use App\Models\ForumPost;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MomentCommunityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->token = $this->user->createToken('test', ['*'])->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    /** @test */
    public function public_feed_returns_published_posts_with_favorites_count(): void
    {
        ForumPost::create([
            'user_id' => $this->user->id,
            'title' => '公开帖',
            'content' => '社区内容',
            'status' => 'published',
            'likes_count' => 0,
            'replies_count' => 0,
        ]);

        $response = $this->getJson('/api/moments/public');

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('favorites_count', $response->json('data.0'));
    }

    /** @test */
    public function smart_tab_does_not_query_missing_post_id_column(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->user->id,
            'title' => '点赞帖',
            'content' => '正文',
            'status' => 'published',
        ]);
        ForumLike::create([
            'user_id' => $this->user->id,
            'likeable_type' => ForumPost::class,
            'likeable_id' => $post->id,
        ]);

        $response = $this->getJson('/api/moments?tab=smart', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    /** @test */
    public function recommended_tab_does_not_query_missing_post_id_column(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->user->id,
            'title' => '推荐帖',
            'content' => '正文',
            'status' => 'published',
        ]);
        ForumLike::create([
            'user_id' => $this->user->id,
            'likeable_type' => ForumPost::class,
            'likeable_id' => $post->id,
        ]);

        $response = $this->getJson('/api/moments?tab=recommended', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    /** @test */
    public function show_user_profile_returns_stats(): void
    {
        ForumPost::create([
            'user_id' => $this->user->id,
            'title' => '主页帖',
            'content' => '正文',
            'status' => 'published',
            'likes_count' => 3,
        ]);

        $response = $this->getJson('/api/moments/public/users/'.$this->user->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonPath('data.stats.posts_count', 1)
            ->assertJsonPath('data.stats.likes_count', 3);
    }

    /** @test */
    public function index_filters_by_user_id(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->user->tenant_id]);
        ForumPost::create(['user_id' => $this->user->id, 'title' => '我的', 'content' => 'a', 'status' => 'published']);
        ForumPost::create(['user_id' => $other->id, 'title' => '别人的', 'content' => 'b', 'status' => 'published']);

        $response = $this->getJson('/api/moments/public?user_id='.$this->user->id);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('user.id')->unique()->values()->all();
        $this->assertSame([$this->user->id], $ids);
    }

    /** @test */
    public function user_likes_returns_liked_posts(): void
    {
        $author = User::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $post = ForumPost::create([
            'user_id' => $author->id,
            'title' => '被赞',
            'content' => '正文',
            'status' => 'published',
        ]);
        ForumLike::create([
            'user_id' => $this->user->id,
            'likeable_type' => ForumPost::class,
            'likeable_id' => $post->id,
        ]);

        $response = $this->getJson('/api/moments/public/users/'.$this->user->id.'/likes');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame($post->id, $response->json('data.0.id') ?? $response->json('data.data.0.id'));
    }
}
