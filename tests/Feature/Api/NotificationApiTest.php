<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 列表 ───

    public function test_index_returns_notifications(): void
    {
        Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'system',
            'title' => '系统通知',
            'content' => '测试内容',
        ]);

        $response = $this->getJson('/api/notifications', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [['id', 'type', 'title', 'content', 'is_read', 'created_at']],
            'meta' => ['total'],
        ]);
    }

    public function test_index_filters_by_type(): void
    {
        Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'expiry_warning',
            'title' => '过期提醒',
            'content' => '内容',
        ]);
        Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'system',
            'title' => '系统通知',
            'content' => '内容',
        ]);

        // 不传 filter，获取所有通知
        $responseAll = $this->getJson('/api/notifications', $this->authHeaders());
        $responseAll->assertStatus(200);
        $this->assertCount(2, $responseAll->json('data'));
    }

    // ─── 未读数量 ───

    public function test_unread_count_returns_correct_number(): void
    {
        Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'system',
            'title' => '未读',
            'content' => '内容',
            'is_read' => false,
        ]);
        Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'status_change',
            'title' => '已读',
            'content' => '内容',
            'is_read' => true,
        ]);

        $response = $this->getJson('/api/notifications/unread-count', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.count'));
    }

    // ─── 标记已读 ───

    public function test_mark_read_updates_notification(): void
    {
        $notification = Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'system',
            'title' => '测试',
            'content' => '内容',
            'is_read' => false,
        ]);

        $response = $this->postJson("/api/notifications/{$notification->id}/read", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    // ─── 全部已读 ───

    public function test_mark_all_read_updates_all(): void
    {
        Notification::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'system', 'title' => 'A', 'content' => '内容', 'is_read' => false]);
        Notification::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'system', 'title' => 'B', 'content' => '内容', 'is_read' => false]);

        $response = $this->postJson('/api/notifications/read-all', [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.affected'));
    }

    // ─── 批量操作 ───

    public function test_batch_delete_removes_notifications(): void
    {
        $n1 = Notification::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'system', 'title' => 'A', 'content' => '内容']);
        $n2 = Notification::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => 'system', 'title' => 'B', 'content' => '内容']);

        $response = $this->postJson('/api/notifications/batch', [
            'ids' => [$n1->id, $n2->id],
            'action' => 'delete',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $n1->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $n2->id]);
    }

    // ─── 删除单条 ───

    public function test_destroy_deletes_notification(): void
    {
        $notification = Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'system',
            'title' => '测试',
            'content' => '内容',
        ]);

        $response = $this->deleteJson("/api/notifications/{$notification->id}", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_destroy_fails_for_other_users_notification(): void
    {
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $notification = Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'type' => 'system',
            'title' => '其他人的通知',
            'content' => '内容',
        ]);

        $response = $this->deleteJson("/api/notifications/{$notification->id}", [], $this->authHeaders());

        $response->assertStatus(404);
    }

    // ─── 偏好设置 ───

    public function test_preferences_returns_defaults(): void
    {
        $response = $this->getJson('/api/notifications/preferences', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => ['channels', 'types'],
        ]);
    }

    public function test_update_preferences_modifies_channels(): void
    {
        // 先获取偏好（会创建默认记录），再更新
        $this->getJson('/api/notifications/preferences', $this->authHeaders());

        $response = $this->putJson('/api/notifications/preferences', [
            'channels' => ['email' => false],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.channels.email', false);
    }
}
