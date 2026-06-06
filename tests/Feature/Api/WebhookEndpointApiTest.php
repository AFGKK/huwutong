<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookEndpointApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = \App\Models\Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_index_returns_endpoints(): void
    {
        WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => '测试端点',
            'url' => 'https://example.com/webhook',
            'events' => ['license.activated', 'license.revoked'],
            'secret' => 'whsec_' . bin2hex(random_bytes(24)),
            'is_active' => true,
            'is_paused' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/webhook-endpoints');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'meta']);
    }

    public function test_store_creates_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/webhook-endpoints', [
                'name' => '生产环境',
                'url' => 'https://api.example.com/hooks/hwt',
                'events' => ['*'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'name', 'url', 'events', 'secret']]);

        $this->assertDatabaseHas('webhook_endpoints', [
            'tenant_id' => $this->user->tenant_id,
            'name' => '生产环境',
            'url' => 'https://api.example.com/hooks/hwt',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/webhook-endpoints', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_url_format(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/webhook-endpoints', [
                'name' => '测试',
                'url' => 'not-a-url',
                'events' => ['license.activated'],
            ]);

        $response->assertStatus(422);
    }

    public function test_store_auto_generates_secret(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/webhook-endpoints', [
                'name' => '自动密钥',
                'url' => 'https://example.com/hook',
                'events' => ['license.activated'],
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotNull($data['secret']);
        $this->assertStringStartsWith('whsec_', $data['secret']);
    }

    public function test_show_returns_endpoint(): void
    {
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => '查看测试',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'secret' => 'whsec_' . bin2hex(random_bytes(24)),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/webhook-endpoints/{$endpoint->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $endpoint->id);
    }

    public function test_show_respects_tenant_isolation(): void
    {
        $otherTenant = \App\Models\Tenant::factory()->create();
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $otherTenant->id,
            'name' => '其他租户端点',
            'url' => 'https://other.com/hook',
            'events' => ['license.activated'],
            'secret' => 'whsec_' . bin2hex(random_bytes(24)),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/webhook-endpoints/{$endpoint->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_endpoint(): void
    {
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => '原始名称',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'secret' => 'whsec_' . bin2hex(random_bytes(24)),
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/webhook-endpoints/{$endpoint->id}", [
                'name' => '更新后名称',
                'events' => ['license.activated', 'license.expired'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('webhook_endpoints', [
            'id' => $endpoint->id,
            'name' => '更新后名称',
        ]);
    }

    public function test_update_with_new_secret(): void
    {
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => '密钥更新',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'secret' => 'whsec_old_secret',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/webhook-endpoints/{$endpoint->id}", [
                'secret' => 'whsec_new_secret_value_12345',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('webhook_endpoints', [
            'id' => $endpoint->id,
            'secret' => 'whsec_old_secret',
        ]);
    }

    public function test_destroy_disables_endpoint(): void
    {
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => '待删除端点',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'secret' => 'whsec_' . bin2hex(random_bytes(24)),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/webhook-endpoints/{$endpoint->id}");

        $response->assertStatus(200);

        // 软删除：标记为 inactive
        $this->assertDatabaseHas('webhook_endpoints', [
            'id' => $endpoint->id,
            'is_active' => false,
            'is_paused' => true,
        ]);
    }

    public function test_toggle_pause_suspends_and_resumes(): void
    {
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => '暂停测试',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'secret' => 'whsec_' . bin2hex(random_bytes(24)),
            'is_paused' => false,
        ]);

        // 暂停
        $response = $this->actingAs($this->user)
            ->postJson("/api/webhook-endpoints/{$endpoint->id}/toggle-pause");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_paused', true);
        $this->assertDatabaseHas('webhook_endpoints', [
            'id' => $endpoint->id,
            'is_paused' => true,
        ]);

        // 恢复
        $response = $this->actingAs($this->user)
            ->postJson("/api/webhook-endpoints/{$endpoint->id}/toggle-pause");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_paused', false);
        $this->assertDatabaseHas('webhook_endpoints', [
            'id' => $endpoint->id,
            'is_paused' => false,
        ]);
    }

    public function test_test_sends_ping(): void
    {
        Http::fake([
            'https://example.com/webhook-test' => Http::response('ok', 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => '测试连接',
            'url' => 'https://example.com/webhook-test',
            'events' => ['license.activated'],
            'secret' => 'whsec_' . bin2hex(random_bytes(24)),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/webhook-endpoints/{$endpoint->id}/test");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['success', 'status_code', 'latency_ms', 'error']]);

        // 验证发送了 test.ping 事件
        Http::assertSent(function (Request $request) {
            $body = $request->data();
            return $body['event'] === 'test.ping'
                && $request->hasHeader('X-Webhook-Event', 'test.ping');
        });
    }

    public function test_test_handles_connection_failure(): void
    {
        Http::fake([
            'https://example.com/failing-url' => Http::response('Server Error', 500),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => '失败测试',
            'url' => 'https://example.com/failing-url',
            'events' => ['license.activated'],
            'secret' => 'whsec_' . bin2hex(random_bytes(24)),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/webhook-endpoints/{$endpoint->id}/test");

        $response->assertStatus(200)
            ->assertJsonPath('data.success', false);
    }

    public function test_event_types_returns_list(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/webhook-endpoints/event-types');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_unauthenticated_access(): void
    {
        $response = $this->getJson('/api/webhook-endpoints');
        $response->assertStatus(401);
    }
}
