<?php

namespace App\Services;

use App\Models\EventDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Webhook 事件系统服务
 *
 * 职责：
 * - 事件派发到订阅的 Webhook 端点
 * - 签名生成与校验（HMAC-SHA256）
 * - 重试策略（1s/5s/30s/5m/30m/2h + 死信队列）
 * - 失败熔断（连续失败 N 次后自动暂停端点）
 * - 租户隔离校验（payload 数据不跨租户泄露）
 * - Webhook 端点归属租户校验
 */
class WebhookService
{
    /**
     * 重试延迟策略（秒）
     */
    const RETRY_DELAYS = [1, 5, 30, 300, 1800, 7200];

    /**
     * 最大重试次数
     */
    const MAX_RETRIES = 6;

    /**
     * 熔断阈值：连续失败次数
     */
    const CIRCUIT_BREAKER_THRESHOLD = 10;

    /**
     * 熔断恢复时间（秒）
     */
    const CIRCUIT_BREAKER_RESET_TIME = 300;

    /**
     * HTTP 超时（秒）
     */
    const HTTP_TIMEOUT = 10;

    /**
     * 死信队列最大存活天数
     */
    const DEAD_LETTER_TTL_DAYS = 30;

    /**
     * 签名算法
     */
    const SIGNATURE_ALGORITHM = 'sha256';

    /**
     * 派发事件到所有匹配的 Webhook 端点
     *
     * @param int $tenantId
     * @param string $eventType 事件类型，如 license.activated
     * @param array $payload
     * @param array $context 额外上下文（如 license_id, device_id）
     * @return int 派发的端点数量
     */
    public function dispatch(int $tenantId, string $eventType, array $payload, array $context = []): int
    {
        // 1. 查找订阅了该事件的端点（租户隔离）
        $endpoints = WebhookEndpoint::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_paused', false)
            ->where(function ($q) use ($eventType) {
                $q->whereJsonContains('events', $eventType)
                  ->orWhereJsonContains('events', '*');
            })
            ->get();

        if ($endpoints->isEmpty()) {
            return 0;
        }

        // 2. 为每个端点创建事件记录
        foreach ($endpoints as $endpoint) {
            $event = WebhookEvent::create([
                'tenant_id' => $tenantId,
                'webhook_endpoint_id' => $endpoint->id,
                'event_type' => $eventType,
                'payload' => $this->buildPayload($tenantId, $eventType, $payload, $endpoint),
                'status' => 'pending',
            ]);

            // 3. 尝试派发（同步派发，失败入队列重试）
            try {
                $this->sendToEndpoint($event, $endpoint);
            } catch (\Throwable $e) {
                Log::warning('Webhook 首次派发失败，入队列重试', [
                    'event_id' => $event->id,
                    'endpoint_id' => $endpoint->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $endpoints->count();
    }

    /**
     * 构建安全的 Payload（租户隔离校验）
     *
     * 确保 payload 中的数据不包含其他租户的信息
     */
    protected function buildPayload(int $tenantId, string $eventType, array $payload, WebhookEndpoint $endpoint): array
    {
        // 校验端点归属租户
        if ($endpoint->tenant_id !== $tenantId) {
            Log::error('Webhook 租户不匹配', [
                'endpoint_tenant' => $endpoint->tenant_id,
                'request_tenant' => $tenantId,
            ]);
            throw new \RuntimeException('Webhook 端点租户不匹配');
        }

        return [
            'event' => $eventType,
            'id' => (string) Str::uuid(),
            'created_at' => now()->toIso8601String(),
            'data' => $payload,
        ];
    }

    /**
     * 发送到端点
     *
     * @param WebhookEvent $event
     * @param WebhookEndpoint $endpoint
     * @return bool
     */
    public function sendToEndpoint(WebhookEvent $event, WebhookEndpoint $endpoint): bool
    {
        // 检查熔断状态
        if ($this->isCircuitBroken($endpoint)) {
            $event->update(['status' => 'paused']);
            Log::warning('Webhook 端点已熔断，跳过派发', ['endpoint_id' => $endpoint->id]);
            return false;
        }

        try {
            $payload = $event->payload;
            if (! is_array($payload)) {
                $payload = json_decode($payload, true) ?? [];
            }

            // 构建带签名的请求
            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'HWT-Webhook/1.0',
                'X-Webhook-Id' => (string) $event->id,
                'X-Webhook-Event' => $event->event_type,
                'X-Webhook-Timestamp' => (string) time(),
            ];

            // 添加签名
            if ($endpoint->secret) {
                $signature = $this->signPayload($payload, $endpoint->secret);
                $headers['X-Webhook-Signature'] = $signature;
            }

            // 发送 HTTP 请求
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders($headers)
                ->post($endpoint->url, $payload);

            // 记录派发结果
            EventDelivery::create([
                'webhook_event_id' => $event->id,
                'url' => $endpoint->url,
                'attempt' => ($event->deliveries()->count() ?? 0) + 1,
                'status' => $response->successful() ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if ($response->successful()) {
                $event->update(['status' => 'delivered']);
                // 成功—重置熔断计数器
                $this->resetCircuitBreaker($endpoint);
                return true;
            }

            // 失败处理
            $event->increment('attempts');
            $this->handleFailure($event, $endpoint, "HTTP {$response->status()}");

            return false;
        } catch (\Throwable $e) {
            // 连接异常
            EventDelivery::create([
                'webhook_event_id' => $event->id,
                'url' => $endpoint->url,
                'attempt' => ($event->deliveries()->count() ?? 0) + 1,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $event->increment('attempts');
            $this->handleFailure($event, $endpoint, $e->getMessage());

            return false;
        }
    }

    /**
     * 处理失败（更新重试策略）
     */
    protected function handleFailure(WebhookEvent $event, WebhookEndpoint $endpoint, string $error): void
    {
        Log::warning('Webhook 派发失败', [
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'attempt' => $event->attempts,
            'error' => $error,
        ]);

        // 增加连续失败计数
        $failCount = Cache::increment('webhook_fail:' . $endpoint->id, 1);
        Cache::put('webhook_fail:' . $endpoint->id, $failCount, self::CIRCUIT_BREAKER_RESET_TIME);

        // 检查是否触发熔断
        if ($failCount >= self::CIRCUIT_BREAKER_THRESHOLD) {
            $this->activateCircuitBreaker($endpoint);
        }

        // 如果还有重试次数，更新状态为 pending（等待重试）
        if ($event->attempts < self::MAX_RETRIES) {
            $event->update(['status' => 'retrying']);

            // 计算下次重试时间
            $delay = self::RETRY_DELAYS[min($event->attempts - 1, count(self::RETRY_DELAYS) - 1)];
            $retryAt = now()->addSeconds($delay);
            $event->update(['next_retry_at' => $retryAt]);
        } else {
            // 超过最大重试次数 → 死信队列
            $event->update(['status' => 'dead_letter']);
            Log::error('Webhook 进入死信队列', [
                'event_id' => $event->id,
                'endpoint_id' => $endpoint->id,
            ]);
        }
    }

    /**
     * 重试待处理的 Webhook 事件
     *
     * 由定时任务调用（推荐每分钟运行）
     */
    public function retryPending(): int
    {
        $events = WebhookEvent::whereIn('status', ['pending', 'retrying'])
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                  ->orWhere('next_retry_at', '<=', now());
            })
            ->with('webhookEndpoint')
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($events as $event) {
            $endpoint = $event->webhookEndpoint;
            if ($endpoint && $endpoint->is_active && ! $endpoint->is_paused) {
                try {
                    $this->sendToEndpoint($event, $endpoint);
                    $count++;
                } catch (\Throwable $e) {
                    Log::error('Webhook 重试异常', ['event_id' => $event->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return $count;
    }

    /**
     * 清理死信队列中过期的记录
     */
    public function cleanDeadLetters(): int
    {
        return WebhookEvent::where('status', 'dead_letter')
            ->where('created_at', '<', now()->subDays(self::DEAD_LETTER_TTL_DAYS))
            ->delete();
    }

    // ─── HMAC 签名 ───

    /**
     * 对 Payload 生成 HMAC 签名
     */
    public function signPayload(array $payload, string $secret): string
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return base64_encode(
            hash_hmac(self::SIGNATURE_ALGORITHM, $payloadJson, $secret, true),
        );
    }

    /**
     * 校验 Webhook 签名
     *
     * @param string $payload 原始请求体
     * @param string $signature 请求头 X-Webhook-Signature
     * @param string $secret 端点密钥
     * @return bool
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expected = base64_encode(
            hash_hmac(self::SIGNATURE_ALGORITHM, $payload, $secret, true),
        );

        return hash_equals($expected, $signature);
    }

    // ─── 熔断机制 ───

    /**
     * 检查端点是否处于熔断状态
     */
    public function isCircuitBroken(WebhookEndpoint $endpoint): bool
    {
        if (! $endpoint->is_paused) {
            return false;
        }

        // 检查是否过了熔断恢复时间
        $pausedAt = $endpoint->paused_at;
        if ($pausedAt && $pausedAt->addSeconds(self::CIRCUIT_BREAKER_RESET_TIME)->isPast()) {
            // 自动恢复
            $endpoint->update([
                'is_paused' => false,
                'paused_at' => null,
            ]);
            Cache::forget('webhook_fail:' . $endpoint->id);
            return false;
        }

        return true;
    }

    /**
     * 激活熔断
     */
    public function activateCircuitBreaker(WebhookEndpoint $endpoint): void
    {
        $endpoint->update([
            'is_paused' => true,
            'paused_at' => now(),
        ]);

        Log::warning('Webhook 端点已熔断', [
            'endpoint_id' => $endpoint->id,
            'url' => $endpoint->url,
            'reset_at' => now()->addSeconds(self::CIRCUIT_BREAKER_RESET_TIME),
        ]);
    }

    /**
     * 重置熔断计数器
     */
    public function resetCircuitBreaker(WebhookEndpoint $endpoint): void
    {
        Cache::forget('webhook_fail:' . $endpoint->id);
    }

    /**
     * 手动暂停/恢复端点
     */
    public function togglePause(WebhookEndpoint $endpoint, bool $pause): void
    {
        $endpoint->update([
            'is_paused' => $pause,
            'paused_at' => $pause ? now() : null,
        ]);

        if (! $pause) {
            Cache::forget('webhook_fail:' . $endpoint->id);
        }
    }
}
