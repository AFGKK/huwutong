<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookReplayApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = \App\Models\Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $this->endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['*'],
            'is_active' => true,
        ]);
    }

    private function authHeaders(): array
    {
        $token = $this->user->createToken('test-token')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    // ─── 事件列表 ───

    public function test_index_returns_replayable_events(): void
    {
        WebhookEvent::create([
            'tenant_id' => $this->user->tenant_id,
            'webhook_endpoint_id' => $this->endpoint->id,
            'event_type' => 'license.activated',
            'payload' => ['test' => true],
            'status' => 'dead_letter',
        ]);

        $response = $this->getJson('/api/webhook-replay/events', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_filters_by_status(): void
    {
        WebhookEvent::create([
            'tenant_id' => $this->user->tenant_id,
            'webhook_endpoint_id' => $this->endpoint->id,
            'event_type' => 'license.expired',
            'payload' => [],
            'status' => 'delivered',
        ]);

        $response = $this->getJson('/api/webhook-replay/events?status=delivered', $this->authHeaders());
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_requires_auth(): void
    {
        $response = $this->getJson('/api/webhook-replay/events');
        $response->assertStatus(401);
    }

    // ─── 事件详情 ───

    public function test_show_returns_event_with_deliveries(): void
    {
        $event = WebhookEvent::create([
            'tenant_id' => $this->user->tenant_id,
            'webhook_endpoint_id' => $this->endpoint->id,
            'event_type' => 'license.activated',
            'payload' => ['key' => 'val'],
            'status' => 'retrying',
            'attempts' => 2,
        ]);

        $response = $this->getJson("/api/webhook-replay/events/{$event->id}", $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('data.event.id', $event->id);
        $response->assertJsonPath('data.event.status', 'retrying');
    }

    public function test_show_rejects_other_tenant(): void
    {
        $otherTenant = \App\Models\Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $event = WebhookEvent::create([
            'tenant_id' => $otherUser->tenant_id,
            'webhook_endpoint_id' => $this->endpoint->id,
            'event_type' => 'license.activated',
            'payload' => [],
            'status' => 'retrying',
        ]);

        $response = $this->getJson("/api/webhook-replay/events/{$event->id}", $this->authHeaders());
        $response->assertStatus(404);
    }

    // ─── 单事件重放 ───

    public function test_replay_sends_event_to_endpoint(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        $event = WebhookEvent::create([
            'tenant_id' => $this->user->tenant_id,
            'webhook_endpoint_id' => $this->endpoint->id,
            'event_type' => 'license.activated',
            'payload' => ['test' => 'replay'],
            'status' => 'dead_letter',
        ]);

        $response = $this->postJson("/api/webhook-replay/events/{$event->id}/replay", [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('data.delivered', true);

        Http::assertSent(function (Request $req) {
            return str_contains($req->url(), 'example.com/hook');
        });
    }

    public function test_replay_handles_endpoint_failure(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(null, 500),
        ]);

        $event = WebhookEvent::create([
            'tenant_id' => $this->user->tenant_id,
            'webhook_endpoint_id' => $this->endpoint->id,
            'event_type' => 'license.expired',
            'payload' => [],
            'status' => 'retrying',
        ]);

        $response = $this->postJson("/api/webhook-replay/events/{$event->id}/replay", [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('data.delivered', false);
    }

    // ─── 批量重放 ───

    public function test_batch_replay_replays_multiple_events(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        $events = [];
        for ($i = 0; $i < 3; $i++) {
            $events[] = WebhookEvent::create([
                'tenant_id' => $this->user->tenant_id,
                'webhook_endpoint_id' => $this->endpoint->id,
                'event_type' => "license.event_{$i}",
                'payload' => [],
                'status' => 'dead_letter',
            ])->id;
        }

        $response = $this->postJson('/api/webhook-replay/batch-replay', [
            'event_ids' => $events,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.total', 3);
        $response->assertJsonPath('data.success_count', 3);
    }

    // ─── 端点全量重放 ───

    public function test_replay_endpoint_replays_all_failed(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        for ($i = 0; $i < 2; $i++) {
            WebhookEvent::create([
                'tenant_id' => $this->user->tenant_id,
                'webhook_endpoint_id' => $this->endpoint->id,
                'event_type' => 'license.event',
                'payload' => [],
                'status' => 'retrying',
            ]);
        }

        $response = $this->postJson(
            "/api/webhook-replay/endpoints/{$this->endpoint->id}/replay-all",
            [],
            $this->authHeaders(),
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.total', 2);
        $response->assertJsonPath('data.success_count', 2);
    }

    // ─── 统计 ───

    public function test_stats_returns_counts(): void
    {
        WebhookEvent::create([
            'tenant_id' => $this->user->tenant_id,
            'webhook_endpoint_id' => $this->endpoint->id,
            'event_type' => 'license.activated',
            'payload' => [],
            'status' => 'dead_letter',
        ]);

        $response = $this->getJson('/api/webhook-replay/stats', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'pending_replay',
                'dead_letter',
                'delivered_today',
                'failed_today',
                'total_endpoints',
                'paused_endpoints',
            ],
        ]);
        $response->assertJsonPath('data.dead_letter', 1);
    }
}
