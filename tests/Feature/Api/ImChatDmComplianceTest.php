<?php

namespace Tests\Feature\Api;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserPrivacySetting;
use App\Services\UserChatPolicyService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ImChatDmComplianceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $userA;
    private User $userB;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->userA = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'UserA']);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'UserB']);
        $this->tokenA = $this->userA->createToken('token-a', ['*'])->plainTextToken;
        $this->tokenB = $this->userB->createToken('token-b', ['*'])->plainTextToken;
    }

    protected function headers(string $token): array
    {
        $this->app->make('auth')->forgetGuards();

        return ['Authorization' => 'Bearer ' . $token];
    }

    protected function asUser(User $user): static
    {
        $this->app->make('auth')->forgetGuards();
        Sanctum::actingAs($user);

        return $this;
    }

    /** @test */
    public function closed_dm_policy_blocks_stranger_conversation(): void
    {
        UserPrivacySetting::defaultFor($this->userB->id)->update(['dm_policy' => 'closed']);

        $r = $this->postJson('/api/user-chat/conversations', [
            'participant_ids' => [$this->userB->id],
        ], $this->headers($this->tokenA));

        $r->assertStatus(400)->assertJsonPath('success', false);
    }

    /** @test */
    public function followers_only_requires_follow_relationship(): void
    {
        UserPrivacySetting::defaultFor($this->userB->id)->update(['dm_policy' => 'followers_only']);

        $r = $this->postJson('/api/user-chat/conversations', [
            'participant_ids' => [$this->userB->id],
        ], $this->headers($this->tokenA));

        $r->assertStatus(400);

        DB::table('forum_follows')->insert([
            'user_id' => $this->userA->id,
            'target_user_id' => $this->userB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = $this->postJson('/api/user-chat/conversations', [
            'participant_ids' => [$this->userB->id],
        ], $this->headers($this->tokenA));

        $this->assertContains($r->status(), [200, 201]);
    }

    /** @test */
    public function stranger_message_creates_pending_request_for_recipient(): void
    {
        UserPrivacySetting::defaultFor($this->userB->id)->update(['dm_policy' => 'everyone']);

        $create = $this->postJson('/api/user-chat/conversations', [
            'participant_ids' => [$this->userB->id],
        ], $this->headers($this->tokenA));
        $create->assertStatus(201);
        $convId = $create->json('data.id');

        $send = $this->postJson("/api/user-chat/conversations/{$convId}/messages", [
            'content' => '你好，认识一下',
            'message_type' => 'text',
        ], $this->headers($this->tokenA));
        $this->assertContains($send->status(), [200, 201]);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $convId,
            'user_id' => $this->userB->id,
            'request_status' => 'pending',
        ]);

        $requests = $this->asUser($this->userB)->getJson('/api/user-chat/message-requests');
        $requests->assertStatus(200)->assertJsonPath('success', true);
        $this->assertCount(1, $requests->json('data'));
    }

    /** @test */
    public function recipient_can_accept_message_request(): void
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);
        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->userB->id,
            'role' => 'member',
            'request_status' => 'pending',
        ]);

        $r = $this->postJson("/api/user-chat/message-requests/{$conv->id}/accept", [], $this->headers($this->tokenB));
        $r->assertStatus(200);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conv->id,
            'user_id' => $this->userB->id,
            'request_status' => 'accepted',
        ]);
    }

    /** @test */
    public function harassment_mute_blocks_after_five_unreplied_stranger_messages(): void
    {
        UserPrivacySetting::defaultFor($this->userB->id)->update(['dm_policy' => 'everyone']);

        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);
        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->userB->id,
            'role' => 'member',
            'request_status' => 'pending',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            ConversationMessage::create([
                'conversation_id' => $conv->id,
                'sender_id' => $this->userA->id,
                'message_type' => 'text',
                'content' => "msg {$i}",
            ]);
        }

        app(UserChatPolicyService::class)->applyHarassmentCheck($this->userA->id, $this->userB->id, $conv->id);

        $sixth = $this->postJson("/api/user-chat/conversations/{$conv->id}/messages", [
            'content' => '第六条',
            'message_type' => 'text',
        ], $this->headers($this->tokenA));

        $sixth->assertStatus(400);
    }

    /** @test */
    public function dm_policy_can_be_saved_via_privacy_settings(): void
    {
        $r = $this->putJson('/api/user-chat/privacy-settings', [
            'dm_policy' => 'closed',
        ], $this->headers($this->tokenA));

        $r->assertStatus(200);
        $this->assertDatabaseHas('user_privacy_settings', [
            'user_id' => $this->userA->id,
            'dm_policy' => 'closed',
        ]);
    }

    /** @test */
    public function mutual_follow_requires_both_directions(): void
    {
        UserPrivacySetting::defaultFor($this->userB->id)->update(['dm_policy' => 'mutual_follow']);

        $r = $this->postJson('/api/user-chat/conversations', [
            'participant_ids' => [$this->userB->id],
        ], $this->headers($this->tokenA));
        $r->assertStatus(400);

        DB::table('forum_follows')->insert([
            'user_id' => $this->userA->id,
            'target_user_id' => $this->userB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = $this->postJson('/api/user-chat/conversations', [
            'participant_ids' => [$this->userB->id],
        ], $this->headers($this->tokenA));
        $r->assertStatus(400);

        DB::table('forum_follows')->insert([
            'user_id' => $this->userB->id,
            'target_user_id' => $this->userA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = $this->postJson('/api/user-chat/conversations', [
            'participant_ids' => [$this->userB->id],
        ], $this->headers($this->tokenA));
        $this->assertContains($r->status(), [200, 201]);
    }

    /** @test */
    public function dm_policy_accepts_mutual_follow(): void
    {
        $r = $this->putJson('/api/user-chat/privacy-settings', [
            'dm_policy' => 'mutual_follow',
        ], $this->headers($this->tokenA));

        $r->assertStatus(200)->assertJsonPath('data.dm_policy', 'mutual_follow');
        $this->assertDatabaseHas('user_privacy_settings', [
            'user_id' => $this->userA->id,
            'dm_policy' => 'mutual_follow',
        ]);
    }

    /** @test */
    public function seller_inquiry_bypasses_friend_requirement(): void
    {
        $product = Product::factory()->create([
            'user_id' => $this->userB->id,
            'name' => 'Test Product',
            'is_active' => true,
        ]);

        $r = $this->postJson('/api/user-chat/seller-inquiry', [
            'seller_id' => $this->userB->id,
            'product_id' => $product->id,
        ], $this->headers($this->tokenA));

        $r->assertStatus(201)->assertJsonPath('success', true);
    }

    /** @test */
    public function seller_inquiry_bypasses_closed_dm_policy(): void
    {
        UserPrivacySetting::defaultFor($this->userB->id)->update(['dm_policy' => 'closed']);

        $product = Product::factory()->create([
            'user_id' => $this->userB->id,
            'name' => 'Closed Policy Product',
            'is_active' => true,
        ]);

        $r = $this->postJson('/api/user-chat/seller-inquiry', [
            'seller_id' => $this->userB->id,
            'product_id' => $product->id,
        ], $this->headers($this->tokenA));

        $r->assertStatus(201)->assertJsonPath('success', true);
    }
}
