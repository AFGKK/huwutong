<?php

namespace Tests\Feature\Api;

use App\Models\HandoffRequest;
use App\Models\LiveChatConversation;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Live Chat + 统一 handoff 队列已退役：路由 404 / 变更 410 / 队列空列表。
 */
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
    public function live_chat_routes_are_gone(): void
    {
        $this->postJson('/api/live-chat/conversations', ['source' => 'widget'], $this->customerHeaders())
            ->assertStatus(404);

        $this->getJson('/api/live-chat/admin/dashboard', $this->agentHeaders())
            ->assertStatus(404);

        $this->postJson('/api/live-chat/admin/handoffs/1/accept', [], $this->agentHeaders())
            ->assertStatus(404);
    }

    /** @test */
    public function unified_queue_is_gone_even_with_legacy_rows(): void
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

        $this->getJson('/api/handoffs/queue', $this->agentHeaders())
            ->assertStatus(410)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function agent_cannot_accept_live_chat_handoff_via_unified_api(): void
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
            'reason' => 'user_request',
            'status' => 'queued',
            'priority' => 'medium',
            'queue_position' => 1,
            'conversation_context' => ['source' => 'live_chat'],
            'metadata' => ['source' => 'live_chat'],
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
    public function handoff_messages_endpoint_is_disabled(): void
    {
        $conv = LiveChatConversation::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'session_id' => 'chat_test_003',
            'status' => 'active',
            'source' => 'widget',
        ]);

        $handoff = HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'live_chat_conversation_id' => $conv->id,
            'user_id' => $this->customer->id,
            'assigned_to' => $this->agent->id,
            'reason' => 'user_request',
            'status' => 'in_progress',
            'priority' => 'medium',
            'conversation_context' => ['source' => 'live_chat'],
            'metadata' => ['source' => 'live_chat'],
            'queued_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->postJson("/api/handoff/{$handoff->id}/messages", [
            'content' => 'agent reply',
        ], $this->agentHeaders())
            ->assertStatus(410);
    }

    /** @test */
    public function customer_status_poll_is_disabled(): void
    {
        $conv = LiveChatConversation::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->customer->id,
            'session_id' => 'chat_test_004',
            'status' => 'handoff',
            'source' => 'widget',
        ]);

        $handoff = HandoffRequest::create([
            'tenant_id' => $this->tenant->id,
            'live_chat_conversation_id' => $conv->id,
            'user_id' => $this->customer->id,
            'reason' => 'user_request',
            'status' => 'queued',
            'priority' => 'medium',
            'conversation_context' => ['source' => 'live_chat'],
            'metadata' => ['source' => 'live_chat'],
            'queued_at' => now(),
        ]);

        $this->getJson("/api/handoff/{$handoff->id}/status", $this->customerHeaders())
            ->assertStatus(410);
    }
}
