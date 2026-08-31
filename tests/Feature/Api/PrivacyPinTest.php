<?php

namespace Tests\Feature\Api;

use App\Models\ConversationParticipant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConversation;
use App\Models\UserPrivacySetting;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PrivacyPinTest extends TestCase
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
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    /** @test */
    public function user_can_set_verify_and_remove_privacy_pin(): void
    {
        $this->postJson('/api/user-chat/privacy-pin/set', [
            'pin' => '1234',
        ], $this->headers())->assertStatus(200);

        $status = $this->getJson('/api/user-chat/privacy-pin/status', $this->headers());
        $status->assertOk()->assertJsonPath('data.has_pin', true);

        $this->postJson('/api/user-chat/privacy-pin/verify', [
            'pin' => '1234',
        ], $this->headers())->assertOk()->assertJsonPath('data.verified', true);

        $this->postJson('/api/user-chat/privacy-pin/remove', [
            'current_pin' => '1234',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.has_pin', false)
            ->assertJsonPath('data.verified', true);

        $this->assertDatabaseHas('user_privacy_settings', [
            'user_id' => $this->user->id,
            'privacy_pin' => null,
        ]);

        $this->getJson('/api/user-chat/privacy-pin/status', $this->headers())
            ->assertJsonPath('data.has_pin', false);
    }

    /** @test */
    public function remove_privacy_pin_requires_correct_current_pin(): void
    {
        UserPrivacySetting::defaultFor($this->user->id)->update([
            'privacy_pin' => bcrypt('5678'),
        ]);

        $this->postJson('/api/user-chat/privacy-pin/remove', [
            'current_pin' => '0000',
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PIN_MISMATCH');
    }

    /** @test */
    public function remove_privacy_pin_fails_when_not_set(): void
    {
        $this->postJson('/api/user-chat/privacy-pin/remove', [
            'current_pin' => '1234',
        ], $this->headers())
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'PIN_NOT_SET');
    }

    /** @test */
    public function hidden_conversations_require_pin_verification_when_pin_is_set(): void
    {
        $conv = UserConversation::create([
            'type' => 'private',
            'created_by' => $this->user->id,
        ]);
        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'is_hidden' => true,
            'hidden_at' => now(),
        ]);

        UserPrivacySetting::defaultFor($this->user->id)->update([
            'privacy_pin' => bcrypt('9999'),
        ]);

        $this->getJson('/api/user-chat/conversations/hidden', $this->headers())
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PIN_REQUIRED');

        $this->postJson('/api/user-chat/privacy-pin/verify', [
            'pin' => '9999',
        ], $this->headers())->assertOk();

        $this->getJson('/api/user-chat/conversations/hidden', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function hidden_conversations_accessible_without_pin_after_removal(): void
    {
        $conv = UserConversation::create([
            'type' => 'private',
            'created_by' => $this->user->id,
        ]);
        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'is_hidden' => true,
            'hidden_at' => now(),
        ]);

        UserPrivacySetting::defaultFor($this->user->id)->update([
            'privacy_pin' => bcrypt('4321'),
        ]);

        $this->postJson('/api/user-chat/privacy-pin/remove', [
            'current_pin' => '4321',
        ], $this->headers())->assertOk();

        $this->getJson('/api/user-chat/conversations/hidden', $this->headers())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
