<?php

namespace Tests\Feature\Api;

use App\Models\OaArticle;
use App\Models\OaCategory;
use App\Models\OaComment;
use App\Models\OfficialAccount;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OfficialAccountCoreTest extends TestCase
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
    public function public_list_and_comments_endpoints_work(): void
    {
        $account = OfficialAccount::create([
            'name' => 'Demo OA',
            'slug' => 'demo-oa',
            'owner_id' => $this->user->id,
            'status' => 'active',
        ]);
        $article = OaArticle::create([
            'account_id' => $account->id,
            'author_id' => $this->user->id,
            'title' => 'Hello',
            'content' => 'World',
            'status' => 'published',
            'published_at' => now(),
        ]);
        OaComment::create([
            'article_id' => $article->id,
            'user_id' => $this->user->id,
            'content' => 'Nice',
            'status' => 'approved',
        ]);

        $this->getJson('/api/official-accounts/public')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/official-accounts/articles/'.$article->id.'/comments')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function user_can_create_official_account(): void
    {
        OaCategory::create(['name' => 'Tech', 'is_active' => true, 'sort_order' => 0]);

        $response = $this->postJson('/api/official-accounts', [
            'name' => '我的互物号',
            'description' => 'desc',
        ], $this->authHeaders());

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('official_accounts', [
            'name' => '我的互物号',
            'owner_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function user_can_comment_on_article(): void
    {
        $account = OfficialAccount::create([
            'name' => 'OA',
            'slug' => 'oa-1',
            'owner_id' => $this->user->id,
            'status' => 'active',
        ]);
        $article = OaArticle::create([
            'account_id' => $account->id,
            'author_id' => $this->user->id,
            'title' => 'Post',
            'content' => 'Body',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->postJson('/api/official-accounts/articles/'.$article->id.'/comment', [
            'content' => 'Owner comment',
        ], $this->authHeaders());

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('oa_comments', [
            'article_id' => $article->id,
            'content' => 'Owner comment',
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function owner_can_toggle_article_status(): void
    {
        $account = OfficialAccount::create([
            'name' => 'OA',
            'slug' => 'oa-2',
            'owner_id' => $this->user->id,
            'status' => 'active',
        ]);
        $article = OaArticle::create([
            'account_id' => $account->id,
            'author_id' => $this->user->id,
            'title' => 'Post',
            'content' => 'Body',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->postJson('/api/official-accounts/articles/'.$article->id.'/toggle-status', [], $this->authHeaders())
            ->assertOk();
        $this->assertSame('draft', $article->fresh()->status);
    }

    /** @test */
    public function owner_can_apply_verify(): void
    {
        $account = OfficialAccount::create([
            'name' => 'OA',
            'slug' => 'oa-3',
            'owner_id' => $this->user->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/official-accounts/'.$account->id.'/apply-verify', [
                'type' => 'enterprise',
                'name' => 'ACME',
                'reason' => 'Need badge',
            ])->assertOk();

        $this->assertNotEmpty($account->fresh()->settings['verify_request'] ?? null);
    }

    /** @test */
    public function admin_can_review_verify_request(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $account = OfficialAccount::create([
            'name' => 'OA',
            'slug' => 'oa-4',
            'owner_id' => $this->user->id,
            'status' => 'active',
            'settings' => [
                'verify_request' => [
                    'type' => 'enterprise',
                    'name' => 'ACME',
                    'reason' => 'Need badge',
                    'rejected' => false,
                ],
            ],
        ]);

        $this->postJson('/api/admin/official-accounts/'.$account->id.'/review-verify', [
            'action' => 'approve',
        ])->assertOk();

        $this->assertNotNull($account->fresh()->verified_at);
    }
}
