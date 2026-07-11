<?php

namespace Tests\Feature\Api;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserPrivacySetting;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ImChatDmPhase2Test extends TestCase
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

        UserPrivacySetting::defaultFor($this->userA->id)->update(['dm_policy' => 'everyone']);
        UserPrivacySetting::defaultFor($this->userB->id)->update(['dm_policy' => 'everyone']);
    }

    private function makePrivateConv(): UserConversation
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id, 'role' => 'member']);

        return $conv;
    }

    /** @test */
    public function text_message_over_2000_chars_is_rejected(): void
    {
        $conv = $this->makePrivateConv();
        $token = $this->userA->createToken('token-a', ['*'])->plainTextToken;

        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/messages", [
            'content' => str_repeat('a', 2001),
            'message_type' => 'text',
        ], ['Authorization' => 'Bearer ' . $token]);

        $r->assertStatus(422);
    }

    /** @test */
    public function user_can_mark_conversation_unread(): void
    {
        $conv = $this->makePrivateConv();
        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'hello',
        ]);

        Sanctum::actingAs($this->userB);
        $this->postJson("/api/user-chat/conversations/{$conv->id}/read")->assertStatus(200);

        $r = $this->postJson("/api/user-chat/conversations/{$conv->id}/unread");
        $r->assertStatus(200)->assertJsonPath('success', true);

        $participant = ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', $this->userB->id)
            ->first();
        $this->assertGreaterThanOrEqual(1, $participant->unread_count);
    }

    /** @test */
    public function read_receipt_disabled_does_not_mark_messages_read(): void
    {
        $conv = $this->makePrivateConv();
        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'hello',
            'deliver_status' => 'delivered',
        ]);

        UserPrivacySetting::defaultFor($this->userB->id)->update(['show_read_receipt' => false]);

        Sanctum::actingAs($this->userB);
        $this->postJson("/api/user-chat/conversations/{$conv->id}/read")->assertStatus(200);

        $msg->refresh();
        $this->assertEquals('delivered', $msg->deliver_status);
    }

    /** @test */
    public function old_messages_beyond_retention_are_hidden(): void
    {
        $conv = $this->makePrivateConv();
        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'recent',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);
        $ancient = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'ancient',
        ]);
        $ancient->forceFill([
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ])->saveQuietly();

        Sanctum::actingAs($this->userB);
        $r = $this->getJson("/api/user-chat/conversations/{$conv->id}/messages");
        $r->assertStatus(200);

        $contents = collect($r->json('data'))->pluck('content')->all();
        $this->assertContains('recent', $contents);
        $this->assertNotContains('ancient', $contents);
    }

    /** @test */
    public function mark_delivered_updates_message_status(): void
    {
        $conv = $this->makePrivateConv();
        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'ping',
            'deliver_status' => 'sent',
        ]);

        Sanctum::actingAs($this->userB);
        $this->postJson('/api/user-chat/messages/delivered', [
            'message_ids' => [$msg->id],
        ])->assertStatus(200);

        $this->assertEquals('delivered', $msg->fresh()->deliver_status);
    }
}
