<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CacheInvalidation;
use App\Models\CacheInvalidationWebhook;
use App\Services\CacheInvalidationPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SDK 缓存失效推送控制器 (M2-134)
 *
 * 提供：
 * - SSE 推送端点（SDK 可通过 SSE 实时接收失效通知）
 * - 心跳检查端点
 * - Webhook 配置管理
 * - 手动触发失效
 */
class CacheInvalidationController extends Controller
{
    protected CacheInvalidationPushService $pushService;

    public function __construct(CacheInvalidationPushService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * SSE 推送端点 — SDK 建立 SSE 长连接实时接收失效通知
     *
     * GET /api/sdk/cache/events?tenant_id={id}&last_event_id={optional}
     */
    public function stream(Request $request): StreamedResponse
    {
        $tenantId = $request->query('tenant_id');
        if (! $tenantId) {
            abort(400, __('app.api.cache_invalidation.missing_tenant_id'));
        }

        $lastEventId = $request->query('last_event_id');
        $response = new StreamedResponse(function () use ($tenantId, $lastEventId) {
            // 设置无限执行时间
            set_time_limit(0);

            // 发送初始心跳
            echo "event: heartbeat\n";
            echo "data: " . json_encode(['time' => now()->toIso8601String()]) . "\n\n";
            ob_flush();
            flush();

            $lastCheck = $lastEventId ?: now()->subMinute()->toIso8601String();
            $checkCount = 0;

            // 长轮询模式：每 30 秒检查一次新事件，最多 10 次（5分钟）
            while ($checkCount < 10) {
                sleep(30);
                $checkCount++;

                // 心跳保活
                echo "event: heartbeat\n";
                echo "data: " . json_encode(['time' => now()->toIso8601String(), 'count' => $checkCount]) . "\n\n";
                ob_flush();
                flush();

                // 检查是否有新事件
                $invalidations = CacheInvalidation::ofTenant($tenantId)
                    ->whereIn('status', [CacheInvalidation::STATUS_PENDING, CacheInvalidation::STATUS_FAILED])
                    ->where('channel', 'reverb')
                    ->where('created_at', '>', $lastCheck)
                    ->limit(20)
                    ->get();

                if ($invalidations->isNotEmpty()) {
                    foreach ($invalidations as $inv) {
                        echo "id: {$inv->id}\n";
                        echo "event: cache.invalidation\n";
                        echo "data: " . json_encode([
                            'type' => $inv->type,
                            'key' => $inv->invalidation_key,
                            'context' => $inv->context,
                            'timestamp' => $inv->created_at?->toIso8601String(),
                        ]) . "\n\n";
                        ob_flush();
                        flush();

                        // 标记为已推送
                        $inv->update([
                            'status' => CacheInvalidation::STATUS_PUBLISHED,
                            'channel' => 'sse',
                            'published_at' => now(),
                            'last_attempt_at' => now(),
                        ]);
                    }

                    $lastCheck = now()->toIso8601String();
                }
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * SDK 心跳检查端点 — 拉取待处理的失效列表
     *
     * GET /api/sdk/cache/pending?tenant_id={id}&since={optional}
     */
    public function pending(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        if (! $tenantId) {
            return ApiResponse::error('VALIDATION_ERROR', __('app.api.cache_invalidation.missing_tenant_id'), 422);
        }

        $since = $request->query('since');
        $result = $this->pushService->getPendingInvalidations($tenantId, $since);

        return ApiResponse::success($result);
    }

    /**
     * 手动触发缓存失效（供管理后台使用）
     *
     * POST /api/sdk/cache/invalidate
     */
    public function invalidate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'key' => 'required|string|max:100',
            'type' => 'required|in:' . implode(',', array_keys(CacheInvalidation::TYPES)),
            'context' => 'sometimes|nullable|array',
            'immediate' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cache_invalidation.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $invalidation = $this->pushService->invalidate(
            tenantId: $data['tenant_id'],
            invalidationKey: $data['key'],
            type: $data['type'],
            context: $data['context'] ?? null,
            immediate: $data['immediate'] ?? true,
        );

        return ApiResponse::success([
            'id' => $invalidation->id,
            'key' => $invalidation->invalidation_key,
            'type' => $invalidation->type,
            'status' => $invalidation->status,
            'channel' => $invalidation->channel,
            'created_at' => $invalidation->created_at,
        ], __('app.api.cache_invalidation.cache_invalidated'), 201);
    }

    /**
     * 批量触发缓存失效
     *
     * POST /api/sdk/cache/invalidate-batch
     */
    public function invalidateBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'items' => 'required|array|min:1|max:50',
            'items.*.key' => 'required|string|max:100',
            'items.*.type' => 'required|in:' . implode(',', array_keys(CacheInvalidation::TYPES)),
            'items.*.context' => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cache_invalidation.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $invalidations = $this->pushService->invalidateBatch($data['tenant_id'], $data['items']);

        return ApiResponse::success([
            'count' => count($invalidations),
            'invalidations' => array_map(fn (CacheInvalidation $inv) => [
                'id' => $inv->id,
                'key' => $inv->invalidation_key,
                'type' => $inv->type,
                'status' => $inv->status,
            ], $invalidations),
        ], __('app.api.cache_invalidation.batch_invalidated'), 201);
    }

    /**
     * 获取 Webhook 配置列表
     */
    public function webhooks(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $webhooks = CacheInvalidationWebhook::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'url' => $w->url,
                'has_secret' => ! empty($w->secret),
                'subscribed_types' => $w->subscribed_types,
                'is_active' => $w->is_active,
                'created_at' => $w->created_at,
            ]);

        return ApiResponse::success([
            'webhooks' => $webhooks,
            'available_types' => CacheInvalidation::TYPES,
        ]);
    }

    /**
     * 创建 Webhook 配置
     */
    public function storeWebhook(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:500',
            'secret' => 'sometimes|nullable|string|min:8|max:128',
            'subscribed_types' => 'sometimes|nullable|array',
            'subscribed_types.*' => 'in:' . implode(',', array_keys(CacheInvalidation::TYPES)),
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cache_invalidation.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['is_active'] = true;

        $webhook = CacheInvalidationWebhook::create($data);

        return ApiResponse::success([
            'id' => $webhook->id,
            'url' => $webhook->url,
            'has_secret' => ! empty($webhook->secret),
            'subscribed_types' => $webhook->subscribed_types,
            'is_active' => $webhook->is_active,
        ], __('app.api.cache_invalidation.webhook_created'), 201);
    }

    /**
     * 更新 Webhook 配置
     */
    public function updateWebhook(Request $request, CacheInvalidationWebhook $webhook): JsonResponse
    {
        $this->authorizeWebhook($request, $webhook);

        $validator = Validator::make($request->all(), [
            'url' => 'sometimes|url|max:500',
            'secret' => 'sometimes|nullable|string|min:8|max:128',
            'subscribed_types' => 'sometimes|nullable|array',
            'subscribed_types.*' => 'in:' . implode(',', array_keys(CacheInvalidation::TYPES)),
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.cache_invalidation.validation_failed'), $validator->errors()->toArray());
        }

        $webhook->update($validator->validated());

        return ApiResponse::success([
            'id' => $webhook->id,
            'url' => $webhook->url,
            'has_secret' => ! empty($webhook->secret),
            'subscribed_types' => $webhook->subscribed_types,
            'is_active' => $webhook->is_active,
        ], __('app.api.cache_invalidation.webhook_updated'));
    }

    /**
     * 删除 Webhook 配置
     */
    public function destroyWebhook(Request $request, CacheInvalidationWebhook $webhook): JsonResponse
    {
        $this->authorizeWebhook($request, $webhook);
        $webhook->delete();

        return ApiResponse::success(null, __('app.api.cache_invalidation.webhook_deleted'));
    }

    /**
     * 推送统计概览
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $stats = [
            'total_pending' => CacheInvalidation::ofTenant($tenantId)
                ->where('status', CacheInvalidation::STATUS_PENDING)->count(),
            'total_failed' => CacheInvalidation::ofTenant($tenantId)
                ->where('status', CacheInvalidation::STATUS_FAILED)->count(),
            'total_published' => CacheInvalidation::ofTenant($tenantId)
                ->where('status', CacheInvalidation::STATUS_PUBLISHED)->count(),
            'by_type' => CacheInvalidation::ofTenant($tenantId)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'by_channel' => CacheInvalidation::ofTenant($tenantId)
                ->selectRaw('channel, count(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
            'webhook_count' => CacheInvalidationWebhook::where('tenant_id', $tenantId)->count(),
        ];

        return ApiResponse::success($stats);
    }

    /**
     * 验证当前用户是否拥有此 Webhook
     */
    protected function authorizeWebhook(Request $request, CacheInvalidationWebhook $webhook): void
    {
        $tenantId = $request->user()->tenant_id;
        if ($webhook->tenant_id !== $tenantId) {
            abort(403, __('app.api.cache_invalidation.webhook_forbidden'));
        }
    }
}
