<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DispatchWebhookJob;
use App\Models\Tenant;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookJobTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_dispatch_webhook_job_creates_event_and_sends(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 200),
        ]);

        $endpoint = WebhookEndpoint::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook',
            'events' => ['license.activated'],
            'is_active' => true,
            'is_paused' => false,
        ]);

        $job = new DispatchWebhookJob(
            $this->tenant->id,
            'license.activated',
            ['license_key' => 'TEST-123'],
        );

        $job->handle(app(WebhookService::class));

        $this->assertDatabaseHas('webhook_events', [
            'tenant_id' => $this->tenant->id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'license.activated',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook'
                && $request->method() === 'POST';
        });
    }

    public function test_dispatch_webhook_job_skips_unmatched_endpoints(): void
    {
        Http::fake();

        WebhookEndpoint::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook',
            'events' => ['product.updated'],
            'is_active' => true,
            'is_paused' => false,
        ]);

        $job = new DispatchWebhookJob(
            $this->tenant->id,
            'license.activated',
            ['license_key' => 'TEST-123'],
        );

        $job->handle(app(WebhookService::class));

        Http::assertNothingSent();
    }
}
