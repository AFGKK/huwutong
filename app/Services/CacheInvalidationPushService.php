<?php

namespace App\Services;

use App\Events\CacheInvalidationBroadcast;
use App\Models\CacheInvalidation;
use App\Models\CacheInvalidationWebhook;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SDK 缓存失效主动推送服务 (M2-134)
 *
 * 核心流程：
 * 1. 服务端变更（License/FeatureFlag/ProductConfig）→ 调用 invalidate()
 * 2. 创建失效记录 → 通过 Reverb WebSocket 推送 → 失败则走 Webhook 降级
 * 3. SDK 下次心跳时拉取合并失效列表
 *
 * 推送通道优先级: Reverb (WebSocket) > Webhook > SSE (下次心跳拉取)
 */
class CacheInvalidationPushService
{
    /**
     * 批量推送的最大合并条目数
     */
    const BATCH_MERGE_LIMIT = 50;

    /**
     * 推送超时（秒）
     */
    const PUSH_TIMEOUT = 10;

    /**
     * 推送到单个租户的 WebSocket 最大尝试次数
     */
    const MAX_ATTEMPTS = 3;

    /**
     * 创建缓存失效记录并尝试推送
     *
     * @param int $tenantId 租户 ID
     * @param string $invalidationKey 失效键（如 license.status.123）
     * @param string $type 类型（license_status|feature_flag|product_config）
     * @param array|null $context 上下文（旧值/新值/变更描述）
     * @param bool $immediate 是否立即推送（否则走合并推送）
     * @return CacheInvalidation
     */
    public function invalidate(
        int $tenantId,
        string $invalidationKey,
        string $type,
        ?array $context = null,
        bool $immediate = true,
    ): CacheInvalidation {
        $invalidation = CacheInvalidation::create([
            'tenant_id' => $tenantId,
            'invalidation_key' => $invalidationKey,
            'type' => $type,
            'context' => $context,
            'status' => CacheInvalidation::STATUS_PENDING,
            'channel' => 'reverb',
        ]);

        if ($immediate) {
            $this->dispatchPush($invalidation);
        }

        return $invalidation;
    }

    /**
     * 批量创建缓存失效记录（合并推送）
     *
     * @param int $tenantId
     * @param array<array{key: string, type: string, context?: array}> $items
     * @return array<CacheInvalidation>
     */
    public function invalidateBatch(int $tenantId, array $items): array
    {
        $now = now();
        $groupHash = Str::random(16);
        $invalidations = [];

        foreach ($items as $item) {
            $invalidations[] = CacheInvalidation::create([
                'tenant_id' => $tenantId,
                'invalidation_key' => $item['key'],
                'type' => $item['type'],
                'context' => $item['context'] ?? null,
                'status' => CacheInvalidation::STATUS_PENDING,
                'channel' => 'reverb',
                'group_hash' => $groupHash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 合并推送
        $this->dispatchBatchPush($tenantId, $invalidations, $groupHash);

        return $invalidations;
    }

    /**
     * 触发单个失效记录的推送
     */
    public function dispatchPush(CacheInvalidation $invalidation): void
    {
        try {
            // 1. WebSocket 广播优先
            $broadcasted = $this->broadcastViaReverb($invalidation);

            if ($broadcasted) {
                $this->markPublished($invalidation, 'reverb');
                return;
            }

            // 2. WebSocket 失败 → Webhook 降级
            $webhookSent = $this->sendViaWebhook($invalidation);

            if ($webhookSent) {
                $this->markPublished($invalidation, 'webhook');
                return;
            }

            // 3. 两者都失败 → 标记失败，等待下次心跳拉取
            $this->markFailed($invalidation, 'Reverb 和 Webhook 均推送失败');
        } catch (\Throwable $e) {
            $this->markFailed($invalidation, $e->getMessage());
        }
    }

    /**
     * 批量触发合并推送
     */
    public function dispatchBatchPush(int $tenantId, array $invalidations, string $groupHash): void
    {
        if (empty($invalidations)) {
            return;
        }

        try {
            $keys = array_map(fn (CacheInvalidation $i) => $i->invalidation_key, $invalidations);
            $types = array_unique(array_map(fn (CacheInvalidation $i) => $i->type, $invalidations));

            // 为批量事件使用第一条记录作为载体
            $first = $invalidations[0];

            // 1. WebSocket 广播（合并后仅广播一条）
            $event = new CacheInvalidationBroadcast($first, $keys);
            broadcast($event);

            // 标记所有为已发布
            foreach ($invalidations as $inv) {
                $this->markPublished($inv, 'reverb');
            }
        } catch (\Throwable $e) {
            // 批量失败时，全部标记失败
            foreach ($invalidations as $inv) {
                $this->markFailed($inv, '批量推送失败: ' . $e->getMessage());
            }

            // 尝试 Webhook 降级
            $this->sendBatchViaWebhook($tenantId, $invalidations);
        }
    }

    /**
     * 通过 Reverb WebSocket 广播失效事件
     */
    protected function broadcastViaReverb(CacheInvalidation $invalidation): bool
    {
        try {
            $event = new CacheInvalidationBroadcast($invalidation);
            broadcast($event);

            return true;
        } catch (\Throwable $e) {
            Log::warning('CacheInvalidation Reverb 推送失败', [
                'id' => $invalidation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 通过 Webhook 推送失效通知
     */
    protected function sendViaWebhook(CacheInvalidation $invalidation): bool
    {
        $webhooks = CacheInvalidationWebhook::active()
            ->ofTenant($invalidation->tenant_id)
            ->get()
            ->filter(fn (CacheInvalidationWebhook $w) => $w->isSubscribed($invalidation->type));

        if ($webhooks->isEmpty()) {
            return false;
        }

        $payload = $this->buildWebhookPayload($invalidation);
        $successCount = 0;

        foreach ($webhooks as $webhook) {
            try {
                $response = $this->sendHttpWebhook($webhook, $payload);

                if ($response->successful()) {
                    $successCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('CacheInvalidation Webhook 发送失败', [
                    'webhook_id' => $webhook->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $successCount > 0;
    }

    /**
     * 批量通过 Webhook 推送
     */
    protected function sendBatchViaWebhook(int $tenantId, array $invalidations): void
    {
        $webhooks = CacheInvalidationWebhook::active()
            ->ofTenant($tenantId)
            ->get()
            ->filter(function (CacheInvalidationWebhook $w) use ($invalidations) {
                foreach ($invalidations as $inv) {
                    if ($w->isSubscribed($inv->type)) {
                        return true;
                    }
                }
                return false;
            });

        if ($webhooks->isEmpty()) {
            return;
        }

        $keys = array_map(fn (CacheInvalidation $i) => $i->invalidation_key, $invalidations);
        $payload = [
            'event' => 'cache.invalidation.batch',
            'tenant_id' => $tenantId,
            'keys' => $keys,
            'count' => count($invalidations),
            'timestamp' => now()->toIso8601String(),
        ];

        foreach ($webhooks as $webhook) {
            try {
                $this->sendHttpWebhook($webhook, $payload);
            } catch (\Throwable $e) {
                Log::warning('CacheInvalidation 批量 Webhook 发送失败', [
                    'webhook_id' => $webhook->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 发送 HTTP Webhook 请求
     */
    protected function sendHttpWebhook(CacheInvalidationWebhook $webhook, array $payload): Response
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Cache-Invalidation-Version' => '1',
        ];

        // HMAC 签名
        if (! empty($webhook->secret)) {
            $signature = hash_hmac('sha256', json_encode($payload), $webhook->secret);
            $headers['X-Cache-Invalidation-Signature'] = $signature;
        }

        return Http::timeout(self::PUSH_TIMEOUT)
            ->withHeaders($headers)
            ->post($webhook->url, $payload);
    }

    /**
     * 构建 Webhook 负载
     */
    protected function buildWebhookPayload(CacheInvalidation $invalidation): array
    {
        return [
            'event' => 'cache.invalidation',
            'tenant_id' => $invalidation->tenant_id,
            'type' => $invalidation->type,
            'invalidation_key' => $invalidation->invalidation_key,
            'context' => $invalidation->context,
            'timestamp' => $invalidation->created_at?->toIso8601String(),
        ];
    }

    /**
     * 标记为已发布
     */
    public function markPublished(CacheInvalidation $invalidation, string $channel): void
    {
        $invalidation->update([
            'status' => CacheInvalidation::STATUS_PUBLISHED,
            'channel' => $channel,
            'published_at' => now(),
            'last_attempt_at' => now(),
        ]);
    }

    /**
     * 标记为失败
     */
    public function markFailed(CacheInvalidation $invalidation, string $error): void
    {
        $invalidation->update([
            'status' => CacheInvalidation::STATUS_FAILED,
            'attempts' => $invalidation->attempts + 1,
            'last_attempt_at' => now(),
            'last_error' => $error,
        ]);
    }

    /**
     * 获取租户的待处理失效列表（SDK 心跳时拉取）
     *
     * @param int $tenantId
     * @param string|null $since 上次拉取时间
     * @return array{invalidations: array, pending_count: int}
     */
    public function getPendingInvalidations(int $tenantId, ?string $since = null): array
    {
        $query = CacheInvalidation::ofTenant($tenantId)
            ->whereIn('status', [CacheInvalidation::STATUS_PENDING, CacheInvalidation::STATUS_FAILED])
            ->where('channel', 'reverb');

        if ($since) {
            $query->where('created_at', '>', $since);
        }

        $invalidations = $query->orderBy('created_at', 'desc')
            ->limit(self::BATCH_MERGE_LIMIT)
            ->get()
            ->map(fn (CacheInvalidation $inv) => [
                'id' => $inv->id,
                'key' => $inv->invalidation_key,
                'type' => $inv->type,
                'context' => $inv->context,
                'created_at' => $inv->created_at?->toIso8601String(),
            ]);

        // 拉取后标记为已发布（SDK 拉取成功）
        $ids = $invalidations->pluck('id')->toArray();
        CacheInvalidation::whereIn('id', $ids)
            ->where('status', CacheInvalidation::STATUS_PENDING)
            ->update([
                'status' => CacheInvalidation::STATUS_PUBLISHED,
                'published_at' => now(),
                'last_attempt_at' => now(),
            ]);

        return [
            'invalidations' => $invalidations->toArray(),
            'pending_count' => $invalidations->count(),
        ];
    }

    /**
     * 主动清理已发布的失效记录（超过保留期）
     */
    public function prune(int $days = 7): int
    {
        return CacheInvalidation::where('status', CacheInvalidation::STATUS_PUBLISHED)
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
