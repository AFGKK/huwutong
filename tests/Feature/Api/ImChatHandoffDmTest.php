<?php

namespace Tests\Feature\Api;

use App\Models\ConversationMessage;
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

    private function userHeaders(): array
    {
        $token = $this->userA->createToken('user-test', ['*'])->plainTextToken;

        return ['Authorization' => 'Bearer ' . $token];
    }

    /** @test */
    public function accept_user_chat_handoff_posts_system_message_in_dm(): void
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

        $r = $this->postJson("/api/handoff/{$handoff->id}/accept", [], $this->agentHeaders());
        $r->assertStatus(200)->assertJsonPath('success', true);

        $handoff->refresh();
        $dmId = $handoff->dmConversationId();
        $this->assertNotNull($dmId);
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $dmId,
            'message_type' => 'system',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $dmId,
            'user_id' => $this->agent->id,
        ]);
    }

    /** @test */
    public function agent_reply_routes_to_dm_not_agent_messages(): void
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

        $r = $this->postJson("/api/handoff/{$handoff->id}/messages", ['content' => '您好，有什么可以帮您？'], $this->agentHeaders());
        $r->assertStatus(200);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conv->id,
            'sender_id' => $this->agent->id,
            'content' => '您好，有什么可以帮您？',
        ]);
        $this->assertDatabaseMissing('agent_messages', [
            'handoff_request_id' => $handoff->id,
            'content' => '您好，有什么可以帮您？',
        ]);
    }

    /** @test */
    public function peer_chat_handoff_creates_separate_agent_dm(): void
    {
        $seller = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Seller']);
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $seller->id, 'role' => 'member']);

        $handoff = HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_conversation_id' => $conv->id,
            'user_id' => $this->userA->id,
            'reason' => 'user_request',
            'status' => 'queued',
            'priority' => 'medium',
            'metadata' => ['source' => 'user_chat'],
            'queued_at' => now(),
        ]);

        $this->postJson("/api/handoff/{$handoff->id}/accept", [], $this->agentHeaders())->assertStatus(200);

        $handoff->refresh();
        $dmId = $handoff->dmConversationId();
        $this->assertNotNull($dmId);
        $this->assertNotEquals($conv->id, $dmId);

        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $dmId, 'user_id' => $this->userA->id]);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $dmId, 'user_id' => $this->agent->id]);
        $this->assertDatabaseMissing('conversation_participants', ['conversation_id' => $conv->id, 'user_id' => $this->agent->id]);
    }

    /** @test */
    public function user_can_see_dm_messages_after_agent_accepts(): void
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
            'metadata' => ['source' => 'user_chat'],
            'queued_at' => now(),
        ]);

        $this->postJson("/api/handoff/{$handoff->id}/accept", [], $this->agentHeaders())->assertStatus(200);
        $this->postJson("/api/handoff/{$handoff->id}/messages", ['content' => '人工回复内容'], $this->agentHeaders())->assertStatus(200);

        $handoff->refresh();
        $r = $this->getJson("/api/user-chat/conversations/{$handoff->dmConversationId()}/messages", $this->userHeaders());
        $r->assertStatus(200);
        $contents = collect($r->json('data'))->pluck('content')->all();
        $this->assertContains('人工回复内容', $contents);
    }
}
