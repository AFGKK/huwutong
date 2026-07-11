<?php

namespace Tests\Feature\Api;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserFriend;
use App\Models\UserPrivacySetting;
use App\Services\GdprComplianceService;
use App\Services\UserChatCleanupService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ImChatDmPhase3Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $userA;
    private User $userB;
    private User $userC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->userA = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'UserA']);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'UserB']);
        $this->userC = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'UserC']);

        foreach ([$this->userA, $this->userB, $this->userC] as $user) {
            UserPrivacySetting::defaultFor($user->id)->update(['dm_policy' => 'everyone']);
        }
    }

    private function makePrivateConv(User $a, User $b): UserConversation
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $a->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $a->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $b->id, 'role' => 'member']);

        return $conv;
    }

    /** @test */
    public function search_can_filter_by_sender(): void
    {
        $conv = $this->makePrivateConv($this->userA, $this->userB);

        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'apple pie recipe',
        ]);
        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userB->id,
            'message_type' => 'text',
            'content' => 'apple juice order',
        ]);

        Sanctum::actingAs($this->userA);
        $r = $this->getJson('/api/user-chat/messages/search-fulltext?' . http_build_query([
            'q' => 'apple',
            'sender_id' => $this->userB->id,
        ]));

        $r->assertStatus(200);
        $contents = collect($r->json('data'))->pluck('content')->all();
        $this->assertContains('apple juice order', $contents);
        $this->assertNotContains('apple pie recipe', $contents);
    }

    /** @test */
    public function search_rejects_unknown_sender(): void
    {
        $conv = $this->makePrivateConv($this->userA, $this->userB);
        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'hello world',
        ]);

        Sanctum::actingAs($this->userA);
        $r = $this->getJson('/api/user-chat/messages/search-fulltext?' . http_build_query([
            'q' => 'hello',
            'sender_id' => $this->userC->id,
        ]));

        $r->assertStatus(400);
    }

    /** @test */
    public function search_excludes_messages_beyond_retention(): void
    {
        $conv = $this->makePrivateConv($this->userA, $this->userB);

        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userB->id,
            'message_type' => 'text',
            'content' => 'recent banana',
        ]);
        $ancient = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userB->id,
            'message_type' => 'text',
            'content' => 'ancient banana',
        ]);
        $ancient->forceFill([
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ])->saveQuietly();

        Sanctum::actingAs($this->userA);
        $r = $this->getJson('/api/user-chat/messages/search-fulltext?q=banana&sender_id=' . $this->userB->id);
        $r->assertStatus(200);

        $contents = collect($r->json('data'))->pluck('content')->all();
        $this->assertContains('recent banana', $contents);
        $this->assertNotContains('ancient banana', $contents);
    }

    /** @test */
    public function user_deletion_anonymizes_messages_and_removes_participation(): void
    {
        $conv = $this->makePrivateConv($this->userA, $this->userB);
        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'secret message',
        ]);

        UserFriend::create([
            'requester_id' => $this->userA->id,
            'addressee_id' => $this->userB->id,
            'status' => 'accepted',
        ]);

        $results = app(UserChatCleanupService::class)->cleanupForDeletedUser($this->userA);

        $this->assertArrayHasKey('conversation_messages', $results);
        $this->assertArrayHasKey('conversation_participants', $results);
        $this->assertArrayHasKey('user_friends', $results);

        $msg->refresh();
        $this->assertEquals('[此用户已注销]', $msg->content);
        $this->assertTrue((bool) $msg->is_recalled);

        $participant = ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', $this->userA->id)
            ->first();
        $this->assertNotNull($participant->deleted_at);

        $this->assertDatabaseMissing('user_friends', [
            'requester_id' => $this->userA->id,
            'addressee_id' => $this->userB->id,
        ]);

        // 对方仍可查看会话，但消息已匿名
        Sanctum::actingAs($this->userB);
        $r = $this->getJson("/api/user-chat/conversations/{$conv->id}/messages");
        $r->assertStatus(200);
        $contents = collect($r->json('data'))->pluck('content')->all();
        $this->assertContains('[此用户已注销]', $contents);
        $this->assertNotContains('secret message', $contents);
    }

    /** @test */
    public function gdpr_anonymization_includes_im_cleanup(): void
    {
        $conv = $this->makePrivateConv($this->userA, $this->userB);
        ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'private note',
        ]);

        $results = app(GdprComplianceService::class)->anonymizeUserData($this->userA);

        $this->assertArrayHasKey('conversation_messages', $results);
        $this->assertArrayHasKey('conversation_participants', $results);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'content' => '[此用户已注销]',
        ]);
    }
}
