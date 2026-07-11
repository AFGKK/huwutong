<?php

namespace Tests\Feature\Api;

use App\Models\HandoffRequest;
use App\Models\LiveChatConversation;
use App\Models\LiveChatMessage;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ImChatLiveChatHandoffTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $customer;
    private User $agent;
    private string $customerToken;
    private string $agentToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->customer = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Visitor']);
        $this->agent = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Agent']);

        $role = Role::findOrCreate('super-admin', 'web');
        \DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => $role->id, 'model_type' => User::class, 'model_id' => $this->agent->id],
            ['tenant_id' => $this->tenant->id]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->agent->load('roles');

        $this->customerToken = $this->customer->createToken('customer', ['*'])->plainTextToken;
        $this->agentToken = $this->agent->createToken('agent', ['*'])->plainTextToken;
    }

    private function customerHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->customerToken];
    }

    private function agentHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->agentToken];
    }

    /** @test */
    public function live_chat_handoff_appears_in_unified_queue(): void
    {
        $conv = LiveChatConversation::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'session_id' => 'chat_test_001',
            'status' => 'handoff',
            'source' => 'widget',
        ]);

        HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'live_chat_conversation_id' => $conv->id,
            'user_id' => $this->customer->id,
            'reason' => 'AI无法解决',
            'status' => 'queued',
            'priority' => 'medium',
            'queue_position' => 1,
            'conversation_context' => ['source' => 'live_chat', 'session_id' => $conv->session_id],
            'metadata' => ['source' => 'live_chat'],
            'queued_at' => now(),
        ]);

        $response = $this->getJson('/api/handoffs/queue', $this->agentHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $queue = $response->json('data');
        $this->assertNotEmpty($queue);
        $this->assertEquals($conv->id, $queue[0]['live_chat_conversation_id']);
    }

    /** @test */
    public function agent_can_accept_live_chat_handoff_via_unified_api(): void
    {
        $conv = LiveChatConversation::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'session_id' => 'chat_test_002',
            'status' => 'handoff',
            'source' => 'widget',
        ]);

        $handoff = HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'live_chat_conversation_id' => $conv->id,
            'user_id' => $this->customer->id,
            'reason' => 'AI无法解决',
            'status' => 'queued',
            'priority' => 'medium',
            'queue_position' => 1,
            'conversation_context' => ['source' => 'live_chat'],
            'metadata' => ['source' => 'live_chat'],
            'queued_at' => now(),
        ]);

        $response = $this->postJson("/api/handoff/{$handoff->id}/accept", [], $this->agentHeaders());

        $response->assertStatus(200)->assertJsonPath('success', true);

        $handoff->refresh();
        $this->assertEquals('in_progress', $handoff->status);
        $this->assertEquals($this->agent->id, $handoff->assigned_to);

        $this->assertDatabaseHas('live_chat_messages', [
            'conversation_id' => $conv->id,
            'sender_type' => 'agent',
            'sender_id' => $this->agent->id,
        ]);
    }

    /** @test */
    public function user_message_after_handoff_routes_to_handoff_not_ai(): void
    {
        config(['live-chat.handoff.auto_handoff_after_messages' => 1]);

        $create = $this->postJson('/api/live-chat/conversations', ['source' => 'widget'], $this->customerHeaders());
        $convId = $create->json('data.id');

        $this->postJson("/api/live-chat/conversations/{$convId}/messages", [
            'content' => '我需要人工客服',
        ], $this->customerHeaders())->assertStatus(200);

        $handoff = HandoffRequest::where('live_chat_conversation_id', $convId)->first();
        $this->assertNotNull($handoff);

        $beforeAiCount = LiveChatMessage::where('conversation_id', $convId)->where('sender_type', 'ai')->count();

        $followUp = $this->postJson("/api/live-chat/conversations/{$convId}/messages", [
            'content' => '还在吗？',
        ], $this->customerHeaders());

        $followUp->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNull($followUp->json('data.reply'));

        $afterAiCount = LiveChatMessage::where('conversation_id', $convId)->where('sender_type', 'ai')->count();
        $this->assertEquals($beforeAiCount, $afterAiCount);

        $this->assertDatabaseHas('agent_messages', [
            'handoff_request_id' => $handoff->id,
            'sender_type' => 'customer',
            'content' => '还在吗？',
        ]);
    }

    /** @test */
    public function agent_reply_is_mirrored_to_live_chat_messages(): void
    {
        $conv = LiveChatConversation::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'session_id' => 'chat_test_003',
            'status' => 'active',
            'source' => 'widget',
            'assigned_to' => $this->agent->id,
        ]);

        $handoff = HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'live_chat_conversation_id' => $conv->id,
            'user_id' => $this->customer->id,
            'assigned_to' => $this->agent->id,
            'reason' => 'AI无法解决',
            'status' => 'in_progress',
            'priority' => 'medium',
            'queue_position' => 1,
            'conversation_context' => ['source' => 'live_chat'],
            'metadata' => ['source' => 'live_chat'],
            'queued_at' => now(),
            'accepted_at' => now(),
        ]);

        $response = $this->postJson("/api/handoff/{$handoff->id}/messages", [
            'content' => '您好，我是人工客服',
        ], $this->agentHeaders());

        $response->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('live_chat_messages', [
            'conversation_id' => $conv->id,
            'sender_type' => 'agent',
            'content' => '您好，我是人工客服',
        ]);
    }
}
