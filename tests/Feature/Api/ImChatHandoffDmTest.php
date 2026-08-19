<?php

namespace Tests\Feature\Api;

use App\Models\ConversationParticipant;
use App\Models\HandoffRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserPrivacySetting;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * 人工客服队列已退役：accept / messages 等变更接口统一 410。
 */
class ImChatHandoffDmTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $userA;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\ApiVersion::create([
            'version' => 'v1',
            'base_path' => '/api/v1',
            'name' => 'v1',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->userA = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Customer']);
        $this->agent = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Agent']);

        UserPrivacySetting::defaultFor($this->userA->id)->update(['dm_policy' => 'everyone']);

        $role = Role::findOrCreate('super-admin', 'web');
        \DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => $role->id, 'model_type' => User::class, 'model_id' => $this->agent->id],
            ['tenant_id' => $this->tenant->id]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->agent->load('roles');
    }

    private function agentHeaders(): array
    {
        $token = $this->agent->createToken('agent-test', ['*'])->plainTextToken;

        return ['Authorization' => 'Bearer ' . $token];
    }

    /** @test */
    public function accept_user_chat_handoff_is_disabled(): void
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);

        $handoff = HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_conversation_id' => $conv->id,
            'user_id' => $this->userA->id,
            'reason' => 'user_request',
            'status' => 'queued',
            'priority' => 'medium',
            'queue_position' => 1,
            'conversation_context' => ['source' => 'user_chat'],
            'metadata' => ['source' => 'user_chat'],
            'queued_at' => now(),
        ]);

        $this->postJson("/api/handoff/{$handoff->id}/accept", [], $this->agentHeaders())
            ->assertStatus(410)
            ->assertJsonPath('success', false);

        $handoff->refresh();
        $this->assertEquals('queued', $handoff->status);
        $this->assertNull($handoff->assigned_to);
    }

    /** @test */
    public function agent_reply_via_handoff_messages_is_disabled(): void
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);

        $handoff = HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_conversation_id' => $conv->id,
            'user_id' => $this->userA->id,
            'assigned_to' => $this->agent->id,
            'reason' => 'user_request',
            'status' => 'in_progress',
            'priority' => 'medium',
            'metadata' => ['source' => 'user_chat', 'dm_conversation_id' => $conv->id],
            'queued_at' => now(),
            'accepted_at' => now(),
        ]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->agent->id, 'role' => 'member']);

        $this->postJson("/api/handoff/{$handoff->id}/messages", ['content' => '您好，有什么可以帮您？'], $this->agentHeaders())
            ->assertStatus(410)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('conversation_messages', [
            'conversation_id' => $conv->id,
            'sender_id' => $this->agent->id,
            'content' => '您好，有什么可以帮您？',
        ]);
    }

    /** @test */
    public function handoff_queue_is_gone(): void
    {
        HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->userA->id,
            'reason' => 'user_request',
            'status' => 'queued',
            'priority' => 'medium',
            'queue_position' => 1,
            'metadata' => ['source' => 'user_chat'],
            'queued_at' => now(),
        ]);

        $this->getJson('/api/handoffs/queue', $this->agentHeaders())
            ->assertStatus(410)
            ->assertJsonPath('success', false);
    }
}
