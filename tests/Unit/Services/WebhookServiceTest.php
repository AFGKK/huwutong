<?php

namespace Tests\Unit\Services;

use App\Models\EventDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WebhookService::class);
    }

    // ─── 事件派发 M1.3-05 ───

    public function test_dispatch_sends_to_subscribed_endpoint(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        $count = $this->service->dispatch(1, 'license.activated', [
            'license_key' => 'TEST-123',
        ]);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('webhook_events', [
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'license.activated',
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('event_deliveries', [
            'webhook_event_id' => WebhookEvent::first()->id,
            'status' => 'success',
        ]);
    }

    public function test_dispatch_skips_unsubscribed_endpoint(): void
    {
        WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['license.expired'], // 只订阅了 expired
            'is_active' => true,
        ]);

        $count = $this->service->dispatch(1, 'license.activated', []);

        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_dispatch_respects_wildcard_event(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Wildcard',
            'url' => 'https://example.com/hook',
            'secret' => null,
            'events' => ['*'],
            'is_active' => true,
        ]);

        $count = $this->service->dispatch(1, 'license.activated', []);
        $this->assertEquals(1, $count);
    }

    public function test_dispatch_isolates_tenant_data(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        // 租户2 的端点
        WebhookEndpoint::create([
            'tenant_id' => 2,
            'name' => 'Tenant2',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        // 从租户1 派发，不应匹配
        $count = $this->service->dispatch(1, 'license.activated', []);
        $this->assertEquals(0, $count);
    }

    public function test_dispatch_handles_connection_failure(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(null, 500),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        $this->service->dispatch(1, 'license.activated', []);

        $event = WebhookEvent::first();
        $this->assertNotNull($event);
        $this->assertEquals('retrying', $event->status);
        $this->assertEquals(1, $event->attempts);
        $this->assertNotNull($event->next_retry_at);
    }

    // ─── 重试策略 M1.3-06 ───

    public function test_retry_sends_pending_events(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        $event = WebhookEvent::create([
            'tenant_id' => 1,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'license.activated',
            'payload' => ['test' => true],
            'status' => 'retrying',
            'attempts' => 1,
            'next_retry_at' => now()->subMinute(),
        ]);

        $count = $this->service->retryPending();

        $this->assertEquals(1, $count);
        $event->refresh();
        $this->assertEquals('delivered', $event->status);
    }

    public function test_retry_skips_future_retry_time(): void
    {
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        WebhookEvent::create([
            'tenant_id' => 1,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'license.activated',
            'payload' => [],
            'status' => 'retrying',
            'attempts' => 1,
            'next_retry_at' => now()->addHour(), // 还没到重试时间
        ]);

        $count = $this->service->retryPending();
        $this->assertEquals(0, $count);
    }

    public function test_max_retries_moves_to_dead_letter(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(null, 500),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        $event = WebhookEvent::create([
            'tenant_id' => 1,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'license.activated',
            'payload' => [],
            'status' => 'retrying',
            'attempts' => WebhookService::MAX_RETRIES, // 已达最大重试次数
            'next_retry_at' => now()->subMinute(),
        ]);

        $this->service->retryPending();

        $event->refresh();
        $this->assertEquals('dead_letter', $event->status);
    }

    // ─── 签名校验 M1.3-07 ───

    public function test_signature_is_sent_in_header(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'secret' => 'my-secret-key',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        $this->service->dispatch(1, 'license.activated', ['key' => 'val']);

        Http::assertSent(function (Request $request) {
            $signature = $request->header('X-Webhook-Signature')[0] ?? null;
            $this->assertNotNull($signature, 'Signature header must be present');

            // 验证签名
            $body = $request->body();
            $this->assertTrue(
                $this->service->verifySignature($body, $signature, 'my-secret-key'),
                'Signature verification must pass',
            );

            return true;
        });
    }

    public function test_verify_signature_validates_correctly(): void
    {
        $payload = ['event' => 'test', 'data' => ['key' => 'value']];
        $secret = 'my-secret-key';

        $signature = $this->service->signPayload($payload, $secret);
        $payloadJson = json_encode($payload);

        $this->assertTrue($this->service->verifySignature($payloadJson, $signature, $secret));
        $this->assertFalse($this->service->verifySignature($payloadJson, $signature, 'wrong-secret'));
        $this->assertFalse($this->service->verifySignature(
            json_encode(['different' => 'payload']),
            $signature,
            $secret,
        ));
    }

    // ─── 熔断 M1.3-07 ───

    public function test_circuit_breaker_pauses_after_consecutive_failures(): void
    {
        Http::fake([
            'https://example.com/hook' => Http::response(null, 500),
        ]);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'is_active' => true,
        ]);

        // 模拟 N 次连续失败
        $failCount = WebhookService::CIRCUIT_BREAKER_THRESHOLD;
        Cache::put('webhook_fail:' . $endpoint->id, $failCount, 300);

        // 派发一次应该触发熔断
        $this->service->dispatch(1, 'license.activated', []);

        $endpoint->refresh();
        $this->assertTrue($endpoint->is_paused);
        $this->assertNotNull($endpoint->paused_at);
    }

    public function test_circuit_breaker_skips_paused_endpoint(): void
    {
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'events' => ['license.activated'],
            'is_active' => true,
            'is_paused' => true,
            'paused_at' => now(),
        ]);

        Http::fake([
            'https://example.com/hook' => Http::response(['ok' => true], 200),
        ]);

        $count = $this->service->dispatch(1, 'license.activated', []);

        // 熔断中的端点不计数
        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('webhook_events', 0);
        Http::assertNothingSent();
    }

    public function test_toggle_pause_resumes_endpoint(): void
    {
        $endpoint = WebhookEndpoint::create([
            'tenant_id' => 1,
            'name' => 'Test',
            'url' => 'https://example.com/hook',
            'events' => ['*'],
            'is_active' => true,
            'is_paused' => true,
            'paused_at' => now(),
        ]);

        $this->service->togglePause($endpoint, false);

        $endpoint->refresh();
        $this->assertFalse($endpoint->is_paused);
        $this->assertNull($endpoint->paused_at);
    }
}
