<?php

namespace Tests\Feature\Api;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\MessageReaction;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserPrivacySetting;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ImChatDmPhase5Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->userA = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'UserA']);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'UserB']);

        foreach ([$this->userA, $this->userB] as $user) {
            UserPrivacySetting::defaultFor($user->id)->update(['dm_policy' => 'everyone']);
        }
    }

    /** @test */
    public function get_messages_includes_reactions_without_extra_requests(): void
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id, 'role' => 'member']);

        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'react me',
        ]);

        MessageReaction::create([
            'message_id' => $msg->id,
            'user_id' => $this->userB->id,
            'reaction' => '👍',
        ]);

        Sanctum::actingAs($this->userB);
        $r = $this->getJson("/api/user-chat/conversations/{$conv->id}/messages");
        $r->assertStatus(200);

        $first = collect($r->json('data'))->firstWhere('id', $msg->id);
        $this->assertNotNull($first);
        $this->assertIsArray($first['reactions']);
        $this->assertEquals('👍', $first['reactions'][0]['emoji']);
        $this->assertEquals(1, $first['reactions'][0]['count']);
    }

    /** @test */
    public function group_member_without_send_file_permission_is_blocked(): void
    {
        $conv = UserConversation::create([
            'type' => 'group',
            'name' => 'Test Group',
            'created_by' => $this->userA->id,
            'permissions' => ['send_file' => 'admin'],
        ]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'creator']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id, 'role' => 'member']);

        Sanctum::actingAs($this->userB);
        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/messages", [
            'content' => 'file attempt',
            'message_type' => 'file',
            'attachments' => [['name' => 'a.pdf', 'url' => '/f.pdf', 'mime' => 'application/pdf']],
        ]);

        $r->assertStatus(400);
    }

    /** @test */
    public function conversation_list_includes_permission_flags(): void
    {
        $conv = UserConversation::create([
            'type' => 'group',
            'name' => 'Perm Group',
            'created_by' => $this->userA->id,
            'permissions' => ['send_card' => 'admin'],
        ]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'creator']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id, 'role' => 'member']);

        Sanctum::actingAs($this->userB);
        $r = $this->getJson('/api/user-chat/conversations');
        $r->assertStatus(200);

        $item = collect($r->json('data'))->firstWhere('id', $conv->id);
        $this->assertFalse($item['can_send_card']);
        $this->assertTrue($item['can_send_file']);
    }
}
