<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\ChatFaq;
use App\Models\ConversationParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAppeal;
use App\Models\UserConversation;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class LightweightControllersTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $bannedUser;
    private User $adminUser;
    private User $memberUser;
    private string $adminToken;
    private string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->bannedUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'inactive',
        ]);
        $this->memberUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $role = Role::findOrCreate('super-admin', 'web');
        \DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => $role->id, 'model_type' => User::class, 'model_id' => $this->adminUser->id],
            ['tenant_id' => $this->tenant->id]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->adminUser->load('roles');

        $this->adminToken = $this->adminUser->createToken('admin', ['*'])->plainTextToken;
        $this->memberToken = $this->memberUser->createToken('member', ['*'])->plainTextToken;
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    private function memberHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->memberToken];
    }

    /** @test */
    public function public_can_lookup_appeal_status_by_email(): void
    {
        UserAppeal::create([
            'user_id' => $this->bannedUser->id,
            'status' => 'pending',
            'reason' => 'misunderstanding',
            'appealed_at' => now(),
        ]);

        $this->getJson('/api/appeal/lookup?email=' . urlencode($this->bannedUser->email))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_appeal', true)
            ->assertJsonPath('data.appeal.status', 'pending');
    }

    /** @test */
    public function admin_can_list_review_and_approve_appeals(): void
    {
        $appeal = UserAppeal::create([
            'user_id' => $this->bannedUser->id,
            'status' => 'pending',
            'reason' => 'misunderstanding',
            'appealed_at' => now(),
        ]);

        $this->getJson('/api/admin/appeals', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $this->postJson("/api/admin/appeals/{$appeal->id}/start-review", [], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewing');

        $this->postJson("/api/admin/appeals/{$appeal->id}/review", [
            'action' => 'approve',
            'comment' => '误封恢复',
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('users', [
            'id' => $this->bannedUser->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function member_can_crud_announcement_reads(): void
    {
        $conv = UserConversation::create([
            'type' => 'group',
            'name' => '公告群',
            'created_by' => $this->memberUser->id,
        ]);
        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->memberUser->id,
            'role' => 'owner',
        ]);

        $announcement = Announcement::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->memberUser->id,
            'title' => '测试公告',
            'content' => '内容',
        ]);

        $create = $this->postJson('/api/user-chat/announcement-reads', [
            'announcement_id' => $announcement->id,
        ], $this->memberHeaders());

        $create->assertStatus(201)
            ->assertJsonPath('success', true);

        $readId = $create->json('data.id');

        $this->getJson('/api/user-chat/announcement-reads?announcement_id=' . $announcement->id, $this->memberHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.announcement_id', $announcement->id);

        $this->deleteJson("/api/user-chat/announcement-reads/{$readId}", [], $this->memberHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('announcement_reads', ['id' => $readId]);
    }

    /** @test */
    public function admin_faq_index_includes_inactive_and_supports_reorder(): void
    {
        $active = ChatFaq::create([
            'question' => '启用问题',
            'answer' => '答案A',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $inactive = ChatFaq::create([
            'question' => '停用问题',
            'answer' => '答案B',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $this->getJson('/api/admin/chat-faqs', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->postJson('/api/admin/chat-faqs/reorder', [
            'orders' => [
                ['id' => $inactive->id, 'sort_order' => 0],
                ['id' => $active->id, 'sort_order' => 1],
            ],
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('chat_faqs', ['id' => $inactive->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('chat_faqs', ['id' => $active->id, 'sort_order' => 1]);

        $this->getJson('/api/chat-faqs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }
}
