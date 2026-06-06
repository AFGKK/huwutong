<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 异步分发 Webhook 事件到端点
 *
 * 将 WebhookService::dispatch() 中的同步 HTTP 调用异步化，
 * 避免阻塞主业务流程。
 */
class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $maxExceptions = 3;

    public array $backoff = [5, 15, 60];

    /**
     * @param int $tenantId
     * @param string $eventType
     * @param array $payload
     * @param array $context
     */
    public function __construct(
        protected int $tenantId,
        protected string $eventType,
        protected array $payload,
        protected array $context = [],
    ) {}

    public function handle(WebhookService $webhookService): void
    {
        $endpoints = WebhookEndpoint::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->where('is_paused', false)
            ->where(function ($q) {
                $q->whereJsonContains('events', $this->eventType)
                  ->orWhereJsonContains('events', '*');
            })
            ->get();

        if ($endpoints->isEmpty()) {
            return;
        }

        foreach ($endpoints as $endpoint) {
            $event = WebhookEvent::create([
                'tenant_id' => $this->tenantId,
                'webhook_endpoint_id' => $endpoint->id,
                'event_type' => $this->eventType,
                'payload' => $webhookService->buildPayload(
                    $this->tenantId, $this->eventType, $this->payload, $endpoint,
                ),
                'status' => 'pending',
            ]);

            try {
                $webhookService->sendToEndpoint($event, $endpoint);
            } catch (\Throwable $e) {
                Log::warning('Webhook 队列派发失败', [
                    'event_id' => $event->id,
                    'endpoint_id' => $endpoint->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DispatchWebhookJob 执行失败', [
            'tenant_id' => $this->tenantId,
            'event_type' => $this->eventType,
            'error' => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['webhook', 'dispatch', 'tenant:' . $this->tenantId, 'event:' . $this->eventType];
    }
}
