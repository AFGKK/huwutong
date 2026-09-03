<?php

namespace Tests\Feature\Api;

use App\Events\OaArticlePublished;
use App\Models\OaArticle;
use App\Models\OfficialAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OfficialAccountPublishArticleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private OfficialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($this->user);
        $this->account = OfficialAccount::create([
            'name' => '发布测试号',
            'slug' => 'publish-test-oa',
            'owner_id' => $this->user->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function can_publish_article_directly(): void
    {
        Event::fake([OaArticlePublished::class]);

        $response = $this->postJson('/api/official-accounts/'.$this->account->id.'/articles', [
            'title' => '直接发布标题',
            'content' => '<p>'.str_repeat('这是一篇用于直接发布的测试正文。', 3).'</p>',
            'status' => 'published',
            'is_original' => true,
            'allow_comments' => true,
            'summary' => '摘要',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.title', '直接发布标题')
            ->assertJsonPath('data.is_original', true)
            ->assertJsonPath('data.allow_comments', true);

        $this->assertNotNull($response->json('data.published_at'));
        Event::assertDispatched(OaArticlePublished::class);
    }

    /** @test */
    public function inactive_account_cannot_publish(): void
    {
        $this->account->update(['status' => 'pending']);

        $this->postJson('/api/official-accounts/'.$this->account->id.'/articles', [
            'title' => '审核中不可发',
            'content' => str_repeat('正文内容足够长。', 5),
            'status' => 'published',
        ])->assertStatus(422);
    }

    /** @test */
    public function owner_edit_endpoint_and_publish_flow_work_end_to_end(): void
    {
        Event::fake([OaArticlePublished::class]);

        $draft = $this->postJson('/api/official-accounts/'.$this->account->id.'/articles', [
            'title' => '端到端草稿',
            'content' => '<p>'.str_repeat('草稿正文。', 8).'</p>',
            'status' => 'draft',
            'allow_comments' => true,
            'is_original' => false,
        ])->assertCreated()->json('data');

        $id = $draft['id'];

        $this->getJson('/api/official-accounts/articles/'.$id.'/edit')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->putJson('/api/official-accounts/articles/'.$id, [
            'title' => '端到端草稿-发布',
            'content' => '<p>'.str_repeat('发布正文。', 10).'</p>',
            'status' => 'published',
            'allow_comments' => true,
            'is_original' => false,
        ])->assertOk()
            ->assertJsonPath('data.status', 'published');

        Event::assertDispatched(OaArticlePublished::class);

        $this->assertDatabaseHas('oa_articles', [
            'id' => $id,
            'status' => 'published',
            'title' => '端到端草稿-发布',
        ]);
    }

    /** @test */
    public function published_article_is_visible_on_public_detail(): void
    {
        $article = OaArticle::create([
            'account_id' => $this->account->id,
            'author_id' => $this->user->id,
            'title' => '公开可见',
            'content' => '公开正文内容足够长',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->getJson('/api/official-accounts/articles/'.$article->id)
            ->assertOk()
            ->assertJsonPath('data.title', '公开可见');
    }
}
