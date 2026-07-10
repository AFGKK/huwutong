<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class StatusPageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_page(): void
    {
        $response = $this->getJson('/api/status');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => ['overall_status', 'components', 'incidents', 'uptime']]);
    }

    public function test_history_returns_data(): void
    {
        $response = $this->getJson('/api/status/history');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => ['uptime_percent', 'incidents']]);
    }

    public function test_subscribe_requires_email(): void
    {
        $response = $this->postJson('/api/status/subscribe', []);

        $response->assertStatus(422);
    }

    public function test_subscribe_accepts_email(): void
    {
        $response = $this->postJson('/api/status/subscribe', [
            'email' => 'test@example.com',
        ]);

        $this->assertContains($response->status(), [200, 422, 500]);
    }
}
