<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * PWA 管理服务 (M3-51)
 *
 * 渐进式 Web 应用支持：
 * - Service Worker 注册管理
 * - 推送通知订阅
 * - 缓存策略配置
 * - 离线支持
 * - 版本更新管理
 */
class PwaService
{
    /**
     * 推送订阅缓存键
     */
    const SUBSCRIPTIONS_CACHE_KEY = 'pwa:push_subscriptions';

    /**
     * 获取 PWA 仪表盘状态
     */
    public function getDashboard(): array
    {
        $subscriptions = $this->getSubscriptions();
        $stats = $this->getStats();

        return [
            'enabled' => config('pwa.enabled', true),
            'service_worker' => [
                'registered' => file_exists(public_path('sw.js')),
                'path' => config('pwa.serviceworker.path'),
                'scope' => config('pwa.serviceworker.scope'),
                'cache_version' => config('pwa.serviceworker.cache_version', 'v1'),
            ],
            'manifest' => [
                'exists' => file_exists(public_path('manifest.json')),
                'name' => config('pwa.manifest.name'),
                'short_name' => config('pwa.manifest.short_name'),
                'theme_color' => config('pwa.manifest.theme_color'),
                'display' => config('pwa.manifest.display'),
            ],
            'push_notifications' => [
                'enabled' => config('pwa.push_notifications.enabled', false),
                'configured' => !empty(config('pwa.push_notifications.vapid_public_key')),
                'subscribers' => count($subscriptions),
                'active_subscribers' => count(array_filter($subscriptions, fn($s) => ($s['expires_at'] ?? 9999999999) > time())),
            ],
            'caching' => [
                'strategy' => config('pwa.caching.strategy', 'staleWhileRevalidate'),
                'max_age' => config('pwa.caching.max_age_seconds', 86400),
                'api_cache_ttl' => config('pwa.caching.api_cache_ttl', 300),
            ],
            'offline' => [
                'enabled' => config('pwa.offline.enabled', true),
                'fallback_page' => config('pwa.offline.fallback_page'),
            ],
            'stats' => $stats,
        ];
    }

    /**
     * 注册推送订阅
     */
    public function subscribe(array $subscriptionData): array
    {
        $subscriptions = $this->getSubscriptions();

        // 去重
        $endpoint = $subscriptionData['endpoint'] ?? '';
        $subscriptions = array_filter($subscriptions, fn($s) => ($s['endpoint'] ?? '') !== $endpoint);

        $subscriptions[] = [
            'endpoint' => $endpoint,
            'keys' => $subscriptionData['keys'] ?? [],
            'user_agent' => request()->userAgent(),
            'subscribed_at' => now()->toIso8601String(),
            'expires_at' => $subscriptionData['expirationTime'] ?? null,
            'ip' => request()->ip(),
        ];

        $this->saveSubscriptions($subscriptions);

        Log::info('PWA: Push subscription registered', [
            'endpoint_prefix' => substr($endpoint, 0, 50) . '...',
        ]);

        return ['success' => true, 'total' => count($subscriptions)];
    }

    /**
     * 取消推送订阅
     */
    public function unsubscribe(string $endpoint): array
    {
        $subscriptions = $this->getSubscriptions();
        $subscriptions = array_filter($subscriptions, fn($s) => ($s['endpoint'] ?? '') !== $endpoint);
        $this->saveSubscriptions(array_values($subscriptions));

        return ['success' => true, 'total' => count($subscriptions)];
    }

    /**
     * 发送推送通知
     */
    public function sendPushNotification(string $title, string $body, ?string $url = null, ?string $tag = null): array
    {
        if (!config('pwa.push_notifications.enabled')) {
            return ['success' => false, 'message' => __('app.common.push_notifications_not_enabled')];
        }

        $subscriptions = $this->getSubscriptions();
        if (empty($subscriptions)) {
            return ['success' => false, 'message' => __('app.common.no_subscribed_users')];
        }

        $auth = [
            'VAPID' => [
                'subject' => config('pwa.push_notifications.vapid_subject'),
                'publicKey' => config('pwa.push_notifications.vapid_public_key'),
                'privateKey' => config('pwa.push_notifications.vapid_private_key'),
            ],
        ];

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?? '/build/',
            'tag' => $tag ?? 'notification',
            'icon' => '/build/assets/pwa-icon-192.png',
            'badge' => '/build/assets/pwa-badge.png',
            'timestamp' => now()->toIso8601String(),
        ]);

        $sent = 0;
        $failed = 0;

        try {
            $webPush = new WebPush($auth);

            foreach ($subscriptions as $sub) {
                if (empty($sub['endpoint']) || empty($sub['keys'])) {
                    continue;
                }

                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'publicKey' => $sub['keys']['p256dh'] ?? '',
                    'authToken' => $sub['keys']['auth'] ?? '',
                ]);

                $webPush->queueNotification($subscription, $payload);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                } else {
                    $failed++;
                    Log::warning('PWA: Push send failed', [
                        'endpoint' => substr($report->getEndpoint(), 0, 50),
                        'reason' => $report->getReason(),
                    ]);

                    // 订阅过期，移除
                    if ($report->isSubscriptionExpired()) {
                        $this->unsubscribe($report->getEndpoint());
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('PWA: Push notification error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return [
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($subscriptions),
            'message' => "发送 {$sent} 条成功，{$failed} 条失败",
        ];
    }

    /**
     * 清除所有缓存 (通知 SW)
     */
    public function clearCache(): array
    {
        // 清除服务端缓存
        Cache::flush();

        // 记录清除事件
        Log::info('PWA: Cache cleared');

        return ['success' => true, 'message' => __('app.common.cache_cleared')];
    }

    /**
     * 更新 Service Worker 版本
     */
    public function updateServiceWorker(): array
    {
        $currentVersion = config('pwa.serviceworker.cache_version', 'v1');
        $parts = explode('v', $currentVersion);
        $num = (int) ($parts[1] ?? 1);
        $newVersion = 'v' . ($num + 1);

        // 更新配置缓存
        Cache::forever('pwa:sw_version', $newVersion);

        Log::info('PWA: Service Worker version updated', [
            'from' => $currentVersion,
            'to' => $newVersion,
        ]);

        return [
            'success' => true,
            'old_version' => $currentVersion,
            'new_version' => $newVersion,
            'message' => "Service Worker 版本已更新: {$currentVersion} → {$newVersion}",
        ];
    }

    /**
     * 获取推送订阅列表
     */
    public function getSubscriptions(): array
    {
        return Cache::get(self::SUBSCRIPTIONS_CACHE_KEY, []);
    }

    /**
     * 保存推送订阅
     */
    protected function saveSubscriptions(array $subscriptions): void
    {
        Cache::forever(self::SUBSCRIPTIONS_CACHE_KEY, array_values($subscriptions));
    }

    /**
     * 获取统计信息
     */
    protected function getStats(): array
    {
        $cacheSize = $this->estimateCacheSize();

        return [
            'push_subscribers' => count($this->getSubscriptions()),
            'manifest_exists' => file_exists(public_path('manifest.json')),
            'sw_exists' => file_exists(public_path('sw.js')),
            'estimated_cache_size' => $cacheSize,
            'last_sw_update' => Cache::get('pwa:sw_version', 'v1'),
        ];
    }

    /**
     * 估算缓存大小
     */
    protected function estimateCacheSize(): int
    {
        $size = 0;
        $manifestPath = public_path('manifest.json');
        $swPath = public_path('sw.js');

        if (file_exists($manifestPath)) {
            $size += filesize($manifestPath);
        }
        if (file_exists($swPath)) {
            $size += filesize($swPath);
        }

        return $size;
    }
}
