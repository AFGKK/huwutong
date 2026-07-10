<?php

namespace Tests\Feature\Api;

use App\Events\OaArticlePublished;
use App\Models\ConversationParticipant;
use App\Models\ForumPost;
use App\Models\Follow;
use App\Models\OaArticle;
use App\Models\OaCategory;
use App\Models\OfficialAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\ConversationMessage;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ImCommunityOaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;
    private OfficialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->userA = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->tokenA = $this->userA->createToken('test', ['*'])->plainTextToken;
        $this->tokenB = $this->userB->createToken('test', ['*'])->plainTextToken;

        $category = OaCategory::create(['name' => 'Tech', 'is_active' => true, 'sort_order' => 0]);
        $this->account = OfficialAccount::create([
            'name' => 'Demo OA',
            'slug' => 'demo-oa',
            'status' => 'active',
            'category_id' => $category->id,
            'owner_id' => $this->userB->id,
            'settings' => [],
        ]);
    }

    protected function headers(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    /** @test */
    public function user_can_follow_and_unfollow_official_account(): void
    {
        $follow = $this->postJson('/api/official-accounts/' . $this->account->id . '/follow', [], $this->headers($this->tokenA));
        $follow->assertStatus(200)->assertJsonPath('data.following', true);

        $this->assertDatabaseHas('follows', [
            'user_id' => $this->userA->id,
            'followable_type' => OfficialAccount::class,
            'followable_id' => $this->account->id,
        ]);

        $unfollow = $this->postJson('/api/official-accounts/' . $this->account->id . '/unfollow', [], $this->headers($this->tokenA));
        $unfollow->assertStatus(200)->assertJsonPath('data.following', false);

        $this->assertDatabaseMissing('follows', [
            'user_id' => $this->userA->id,
            'followable_type' => OfficialAccount::class,
            'followable_id' => $this->account->id,
        ]);
    }

    /** @test */
    public function user_can_share_oa_article_to_plaza(): void
    {
        $article = OaArticle::create([
            'account_id' => $this->account->id,
            'author_id' => $this->userB->id,
            'title' => 'Share Test Article',
            'content' => 'Article body',
            'summary' => 'Summary text',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->postJson('/api/official-accounts/articles/' . $article->id . '/share', [
            'target' => 'plaza',
        ], $this->headers($this->tokenA));

        $response->assertStatus(200);
        $this->assertDatabaseHas('forum_posts', [
            'user_id' => $this->userA->id,
            'title' => 'Share Test Article',
        ]);
        $this->assertDatabaseHas('oa_article_shares', [
            'article_id' => $article->id,
            'user_id' => $this->userA->id,
            'platform' => 'plaza',
        ]);
    }

    /** @test */
    public function moment_forward_updates_conversation_and_creates_message(): void
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id, 'role' => 'member']);

        $post = ForumPost::create([
            'user_id' => $this->userB->id,
            'title' => 'Plaza Post',
            'content' => 'Hello plaza',
            'status' => 'published',
        ]);

        $response = $this->postJson('/api/moments/' . $post->id . '/forward', [
            'target_conversation_id' => $conv->id,
        ], $this->headers($this->tokenA));

        $response->assertStatus(200);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
        ]);

        $conv->refresh();
        $this->assertNotNull($conv->last_message_at);
        $this->assertNotNull($conv->last_message_id);
    }

    /** @test */
    public function community_report_uses_forum_post_type(): void
    {
        $post = ForumPost::create([
            'user_id' => $this->userB->id,
            'title' => 'Bad Post',
            'content' => 'spam content',
            'status' => 'published',
        ]);

        $response = $this->postJson('/api/user-chat/reports', [
            'reportable_type' => 'forum_post',
            'reportable_id' => $post->id,
            'reason' => 'spam',
        ], $this->headers($this->tokenA));

        $response->assertStatus(201);
        $this->assertDatabaseHas('user_reports', [
            'reporter_id' => $this->userA->id,
            'reportable_type' => ForumPost::class,
            'reportable_id' => $post->id,
        ]);
    }

    /** @test */
    public function article_publish_notifies_follower_via_im_message(): void
    {
        Follow::create([
            'user_id' => $this->userA->id,
            'followable_type' => OfficialAccount::class,
            'followable_id' => $this->account->id,
        ]);

        $article = OaArticle::create([
            'account_id' => $this->account->id,
            'author_id' => $this->userB->id,
            'title' => 'Push Article',
            'content' => 'Push body',
            'summary' => 'Push summary',
            'status' => 'published',
            'published_at' => now(),
        ]);

        event(new OaArticlePublished($article));

        $this->assertTrue(
            ConversationMessage::where('sender_id', $this->userB->id)
                ->where('content', 'like', '%Push Article%')
                ->exists()
        );
    }
}
