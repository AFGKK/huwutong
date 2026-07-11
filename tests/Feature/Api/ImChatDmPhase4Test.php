<?php

namespace Tests\Feature\Api;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserPrivacySetting;
use App\Services\UserChatNotificationService;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ImChatDmPhase4Test extends TestCase
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

    private function makePrivateConv(): UserConversation
    {
        $conv = UserConversation::create(['type' => 'private', 'created_by' => $this->userA->id]);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userA->id, 'role' => 'member']);
        ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $this->userB->id, 'role' => 'member']);

        return $conv;
    }

    /** @test */
    public function new_message_creates_im_notification_for_recipient(): void
    {
        $conv = $this->makePrivateConv();
        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'hello there',
        ]);
        $msg->load('sender:id,name');

        app(UserChatNotificationService::class)->notifyNewMessage($msg, [$this->userA->id, $this->userB->id]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->userB->id,
            'type' => 'im_message',
        ]);

        $notification = Notification::where('user_id', $this->userB->id)->first();
        $this->assertStringContainsString('UserA', $notification->title);
        $this->assertEquals($conv->id, $notification->payload['conversation_id']);
        $this->assertEquals($msg->id, $notification->payload['message_id']);
    }

    /** @test */
    public function im_notification_skipped_during_quiet_hours(): void
    {
        NotificationPreference::create([
            'user_id' => $this->userB->id,
            'channels' => ['mail' => true, 'sms' => false, 'database' => true],
            'types' => [
                'im_message' => ['database' => true, 'mail' => true, 'sms' => false],
            ],
            'quiet_hours_start' => '00:00',
            'quiet_hours_end' => '23:59',
            'timezone' => 'Asia/Shanghai',
        ]);

        $conv = $this->makePrivateConv();
        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'quiet test',
        ]);
        $msg->load('sender:id,name');

        app(UserChatNotificationService::class)->notifyNewMessage($msg, [$this->userA->id, $this->userB->id]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->userB->id,
            'type' => 'im_message',
        ]);
    }

    /** @test */
    public function im_notification_skipped_when_conversation_muted(): void
    {
        $conv = $this->makePrivateConv();
        ConversationParticipant::where('conversation_id', $conv->id)
            ->where('user_id', $this->userB->id)
            ->update(['is_muted' => true]);

        $msg = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'muted test',
        ]);
        $msg->load('sender:id,name');

        app(UserChatNotificationService::class)->notifyNewMessage($msg, [$this->userA->id, $this->userB->id]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->userB->id,
            'type' => 'im_message',
        ]);
    }

    /** @test */
    public function send_message_via_api_triggers_im_notification(): void
    {
        $conv = $this->makePrivateConv();

        Sanctum::actingAs($this->userA);
        $this->postJson("/api/user-chat/conversations/{$conv->id}/messages", [
            'content' => 'api notify test',
            'message_type' => 'text',
        ])->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->userB->id,
            'type' => 'im_message',
        ]);
    }

    /** @test */
    public function prune_command_soft_deletes_messages_beyond_retention(): void
    {
        $conv = $this->makePrivateConv();
        $recent = ConversationMessage::create([
            'conversation_id' => $conv->id,
            'sender_id' => $this->userA->id,
            'message_type' => 'text',
            'content' => 'recent',
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

        Artisan::call('dm:prune-retention', ['--days' => 180]);

        $this->assertNull($recent->fresh()->deleted_at);
        $this->assertNotNull($ancient->fresh()->deleted_at);
    }
}
