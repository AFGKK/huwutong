<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\ApiResponse;
use App\Models\EventDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook 事件回放控制器
 *
 * 提供 Webhook 失败事件的手动/批量重放能力。
 * 所有操作受租户隔离保护。
 */
class WebhookReplayController extends Controller
{
    public function __construct(
        protected WebhookService $webhookService,
    ) {}

    /**
     * 获取可重放的事件列表
     *
     * GET /api/webhook-replay/events
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,retrying,dead_letter,delivered,paused',
            'webhook_endpoint_id' => 'nullable|integer',
            'event_type' => 'nullable|string|max:100',
        ]);

        $query = WebhookEvent::with(['webhookEndpoint', 'deliveries'])
            ->where('tenant_id', $request->user()->tenant_id);

        // 筛选可重放的事件
        if (empty($data['status'])) {
            $query->whereIn('status', ['retrying', 'dead_letter']);
        } else {
            $query->where('status', $data['status']);
        }

        if (! empty($data['webhook_endpoint_id'])) {
            $query->where('webhook_endpoint_id', $data['webhook_endpoint_id']);
        }

        if (! empty($data['event_type'])) {
            $query->where('event_type', $data['event_type']);
        }

        $query->latest();

        $events = $query->paginate(min($data['per_page'] ?? 20, 100));

        return ApiResponse::paginated($events);
    }

    /**
     * 获取事件详情（含完整交付历史）
     *
     * GET /api/webhook-replay/events/{event}
     */
    public function show(int $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (! $event) {
            return ApiResponse::notFound(__('app.api.webhook_replay.event_not_found'));
        }

        $event->load(['deliveries' => fn($q) => $q->latest('attempt')]);

        return ApiResponse::success([
            'event' => $event,
            'deliveries' => $event->deliveries,
        ]);
    }

    /**
     * 手动重放单个事件
     *
     * POST /api/webhook-replay/events/{event}/replay
     */
    public function replay(int $id): JsonResponse
    {
        $event = $this->findEvent($id);

        if (! $event) {
            return ApiResponse::notFound(__('app.api.webhook_replay.event_not_found'));
        }

        $endpoint = $event->webhookEndpoint;

        if (! $endpoint) {
            return ApiResponse::error('ENDPOINT_NOT_FOUND', __('app.api.webhook_replay.endpoint_not_found'), 404);
        }

        if (! $endpoint->is_active) {
            return ApiResponse::error('ENDPOINT_INACTIVE', __('app.api.webhook_replay.endpoint_inactive'), 400);
        }

        $success = $this->webhookService->sendToEndpoint($event, $endpoint);

        return ApiResponse::success([
            'event_id' => $event->id,
            'delivered' => $success,
            'status' => $event->fresh()->status,
        ], $success ? __('app.api.webhook_replay.replay_success') : __('app.api.webhook_replay.replay_failed'));
    }

    /**
     * 批量重放事件
     *
     * POST /api/webhook-replay/batch-replay
     */
    public function batchReplay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_ids' => 'required|array|min:1|max:50',
            'event_ids.*' => 'required|integer|distinct',
        ]);

        $tenantId = $request->user()->tenant_id;

        $events = WebhookEvent::whereIn('id', $data['event_ids'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['retrying', 'dead_letter'])
            ->get();

        if ($events->isEmpty()) {
            return ApiResponse::error('NO_REPLAYABLE_EVENTS', __('app.api.webhook_replay.no_replayable_events'), 404);
        }

        $results = [];
        foreach ($events as $event) {
            $endpoint = $event->webhookEndpoint;
            if ($endpoint && $endpoint->is_active) {
                $success = $this->webhookService->sendToEndpoint($event, $endpoint);
                $results[] = [
                    'event_id' => $event->id,
                    'delivered' => $success,
                    'status' => $event->fresh()->status,
                ];
            } else {
                $results[] = [
                    'event_id' => $event->id,
                    'delivered' => false,
                    'status' => $event->status,
                    'error' => __('app.api.webhook_replay.endpoint_unavailable'),
                ];
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['delivered']));

        return ApiResponse::success([
            'total' => count($events),
            'success_count' => $successCount,
            'results' => $results,
        ], __('app.api.webhook_replay.replayed_count', ['success' => $successCount, 'total' => $events->count()]));
    }

    /**
     * 重放指定端点所有失败事件
     *
     * POST /api/webhook-replay/endpoints/{endpoint}/replay-all
     */
    public function replayEndpoint(int $endpointId): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('id', $endpointId)
            ->where('tenant_id', auth()->user()?->tenant_id)
            ->first();

        if (! $endpoint) {
            return ApiResponse::notFound(__('app.api.webhook_replay.endpoint_not_found_full'));
        }

        $events = WebhookEvent::where('webhook_endpoint_id', $endpoint->id)
            ->whereIn('status', ['retrying', 'dead_letter'])
            ->limit(50)
            ->get();

        if ($events->isEmpty()) {
            return ApiResponse::success(['total' => 0, 'success_count' => 0], __('app.api.webhook_replay.no_pending_events'));
        }

        $successCount = 0;
        foreach ($events as $event) {
            $result = $this->webhookService->sendToEndpoint($event, $endpoint);
            if ($result) {
                $successCount++;
            }
        }

        return ApiResponse::success([
            'total' => $events->count(),
            'success_count' => $successCount,
        ], __('app.api.webhook_replay.replayed_count', ['success' => $successCount, 'total' => $events->count()]));
    }

    /**
     * 获取回放统计
     *
     * GET /api/webhook-replay/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return ApiResponse::success([
            'pending_replay' => WebhookEvent::where('tenant_id', $tenantId)
                ->whereIn('status', ['retrying', 'pending'])
                ->count(),
            'dead_letter' => WebhookEvent::where('tenant_id', $tenantId)
                ->where('status', 'dead_letter')
                ->count(),
            'delivered_today' => WebhookEvent::where('tenant_id', $tenantId)
                ->where('status', 'delivered')
                ->whereDate('created_at', today())
                ->count(),
            'failed_today' => WebhookEvent::where('tenant_id', $tenantId)
                ->whereIn('status', ['retrying', 'dead_letter'])
                ->whereDate('created_at', today())
                ->count(),
            'total_endpoints' => WebhookEndpoint::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count(),
            'paused_endpoints' => WebhookEndpoint::where('tenant_id', $tenantId)
                ->where('is_paused', true)
                ->count(),
        ]);
    }

    /**
     * 查找事件并验证租户归属
     */
    protected function findEvent(int $id): ?WebhookEvent
    {
        $event = WebhookEvent::with('webhookEndpoint')->find($id);

        if (! $event) {
            return null;
        }

        // 租户隔离
        if ($event->tenant_id !== auth()->user()?->tenant_id) {
            return null;
        }

        return $event;
    }
}
