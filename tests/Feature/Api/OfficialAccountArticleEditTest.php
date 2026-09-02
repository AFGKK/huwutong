<?php

namespace Tests\Feature\Api;

use App\Models\OaArticle;
use App\Models\OfficialAccount;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OfficialAccountArticleEditTest extends TestCase
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
            'name' => '编辑测试号',
            'slug' => 'edit-test-oa',
            'owner_id' => $this->user->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function owner_can_load_draft_via_edit_endpoint(): void
    {
        $article = OaArticle::create([
            'account_id' => $this->account->id,
            'author_id' => $this->user->id,
            'title' => '草稿标题',
            'content' => str_repeat('草稿正文内容', 5),
            'status' => 'draft',
        ]);

        $this->getJson('/api/official-accounts/articles/'.$article->id)
            ->assertNotFound();

        $this->getJson('/api/official-accounts/articles/'.$article->id.'/edit')
            ->assertOk()
            ->assertJsonPath('data.title', '草稿标题')
            ->assertJsonPath('data.status', 'draft');
    }

    /** @test */
    public function draft_can_be_saved_then_published(): void
    {
        $create = $this->postJson('/api/official-accounts/'.$this->account->id.'/articles', [
            'title' => '先存草稿',
            'content' => str_repeat('足够长的正文内容用于发布测试。', 3),
            'status' => 'draft',
            'is_original' => false,
            'allow_comments' => true,
        ]);

        $create->assertCreated();
        $id = $create->json('data.id');
        $this->assertNotNull($id);

        // 再次保存草稿（模拟编辑器存草稿后 isEdit=true）
        $this->putJson('/api/official-accounts/articles/'.$id, [
            'title' => '先存草稿-改',
            'content' => str_repeat('足够长的正文内容用于发布测试。', 4),
            'status' => 'draft',
        ])->assertOk();

        // 再发布
        $this->putJson('/api/official-accounts/articles/'.$id, [
            'title' => '先存草稿-改',
            'content' => str_repeat('足够长的正文内容用于发布测试。', 4),
            'status' => 'published',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    /** @test */
    public function published_article_edit_once_still_enforced(): void
    {
        $article = OaArticle::create([
            'account_id' => $this->account->id,
            'author_id' => $this->user->id,
            'title' => '已发布',
            'content' => str_repeat('已发布正文', 5),
            'status' => 'published',
            'published_at' => now(),
            'edited_at' => now(),
        ]);

        $this->putJson('/api/official-accounts/articles/'.$article->id, [
            'title' => '二次修改',
            'content' => str_repeat('二次修改正文', 5),
        ])->assertStatus(422);
    }
}
