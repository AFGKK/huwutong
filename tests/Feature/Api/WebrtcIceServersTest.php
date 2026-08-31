<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class WebrtcIceServersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_user_cannot_fetch_ice_servers(): void
    {
        $this->getJson('/api/calls/ice-servers')->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_gets_default_stun_when_turn_not_configured(): void
    {
        config([
            'webrtc.ice_servers_json' => null,
            'webrtc.turn_url' => null,
            'webrtc.turns_url' => null,
        ]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/calls/ice-servers', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_turn', false);

        $servers = $response->json('data.ice_servers');
        $this->assertIsArray($servers);
        $this->assertNotEmpty($servers);
        $this->assertArrayHasKey('urls', $servers[0]);
        $this->assertStringContainsString('stun:', (string) $servers[0]['urls']);
    }

    /** @test */
    public function ice_servers_from_json_env_includes_turn(): void
    {
        config([
            'webrtc.ice_servers_json' => json_encode([
                ['urls' => 'stun:stun.l.google.com:19302'],
                ['urls' => 'turn:turn.example.com:3478', 'username' => 'u', 'credential' => 'p'],
            ]),
        ]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/calls/ice-servers', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.has_turn', true);

        $servers = $response->json('data.ice_servers');
        $this->assertCount(2, $servers);
        $this->assertEquals('turn:turn.example.com:3478', $servers[1]['urls']);
        $this->assertEquals('u', $servers[1]['username']);
        $this->assertEquals('p', $servers[1]['credential']);
    }

    /** @test */
    public function ice_servers_built_from_individual_turn_vars(): void
    {
        config([
            'webrtc.ice_servers_json' => null,
            'webrtc.stun_urls' => ['stun:stun.l.google.com:19302'],
            'webrtc.turn_url' => 'turn:turn.huwutong.com:3478',
            'webrtc.turns_url' => 'turns:turn.huwutong.com:5349',
            'webrtc.turn_username' => 'hwt',
            'webrtc.turn_credential' => 'secret',
        ]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/calls/ice-servers', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk()->assertJsonPath('data.has_turn', true);

        $servers = $response->json('data.ice_servers');
        $this->assertCount(3, $servers);
        $this->assertEquals('turn:turn.huwutong.com:3478', $servers[1]['urls']);
        $this->assertEquals('hwt', $servers[1]['username']);
        $this->assertEquals('secret', $servers[1]['credential']);
        $this->assertEquals('turns:turn.huwutong.com:5349', $servers[2]['urls']);
    }
}
