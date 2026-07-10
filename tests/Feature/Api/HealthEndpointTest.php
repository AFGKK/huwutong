<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function health_live_endpoint_returns_ok()
    {
        $response = $this->getJson('/api/health/live');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    /** @test */
    public function health_ready_endpoint_returns_ok()
    {
        $response = $this->getJson('/api/health/ready');

        $response->assertStatus(200);
    }

    /** @test */
    public function user_cannot_access_protected_endpoint_without_auth()
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_can_access_user_endpoint()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $user->id]);
    }
}

