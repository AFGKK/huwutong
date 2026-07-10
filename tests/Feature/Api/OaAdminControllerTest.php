<?php

namespace Tests\Feature\Api;

use App\Models\OaCategory;
use App\Models\OfficialAccount;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OaAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private OfficialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);

        $category = OaCategory::create(['name' => 'Test Category', 'is_active' => true, 'sort_order' => 0]);
        $this->account = OfficialAccount::create([
            'name' => 'Test OA Account',
            'slug' => 'test-oa',
            'status' => 'active',
            'category_id' => $category->id,
            'owner_id' => $this->admin->id,
            'settings' => [],
        ]);
    }

    /** @test */
    public function admin_can_list_official_accounts(): void
    {
        $response = $this->getJson('/api/admin/official-accounts');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
        $response->assertJsonFragment(['name' => 'Test OA Account']);
    }

    /** @test */
    public function admin_can_search_official_accounts(): void
    {
        $response = $this->getJson('/api/admin/official-accounts?q=Test');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test OA Account']);
    }

    /** @test */
    public function admin_can_toggle_account_status(): void
    {
        $response = $this->postJson("/api/admin/official-accounts/{$this->account->id}/toggle-status");

        $response->assertStatus(200);
        $this->assertEquals('suspended', $this->account->fresh()->status);
    }

    /** @test */
    public function admin_can_show_account_detail(): void
    {
        $response = $this->getJson("/api/admin/official-accounts/{$this->account->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test OA Account']);
    }

    /** @test */
    public function admin_can_update_account(): void
    {
        $response = $this->putJson("/api/admin/official-accounts/{$this->account->id}", [
            'name' => 'Updated Name',
            'description' => 'New description',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $this->account->fresh()->name);
        $this->assertEquals('New description', $this->account->fresh()->description);
    }

    /** @test */
    public function admin_can_delete_account_without_articles(): void
    {
        $response = $this->deleteJson("/api/admin/official-accounts/{$this->account->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('official_accounts', ['id' => $this->account->id]);
    }

    /** @test */
    public function admin_can_batch_toggle_status(): void
    {
        $account2 = OfficialAccount::create([
            'name' => 'Test OA Account 2',
            'slug' => 'test-oa-2',
            'status' => 'suspended',
            'owner_id' => $this->admin->id,
            'settings' => [],
        ]);

        $response = $this->postJson('/api/admin/official-accounts/batch-toggle-status', [
            'ids' => [$this->account->id, $account2->id],
            'status' => 'active',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('active', $this->account->fresh()->status);
        $this->assertEquals('active', $account2->fresh()->status);
    }
}
