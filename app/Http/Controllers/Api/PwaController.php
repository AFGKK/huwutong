<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PwaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PWA 管理控制器 (M3-51)
 */
class PwaController extends Controller
{
    public function __construct(
        protected PwaService $pwaService,
    ) {}

    /**
     * PWA 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->pwaService->getDashboard();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 注册推送订阅 (来自客户端 JS)
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'expirationTime' => ['nullable', 'integer'],
        ]);

        $result = $this->pwaService->subscribe($validated);

        return response()->json($result);
    }

    /**
     * 取消推送订阅
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $result = $this->pwaService->unsubscribe($validated['endpoint']);

        return response()->json($result);
    }

    /**
     * 发送推送通知
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $this->authorize('sendNotifications', \App\Models\User::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:500'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->pwaService->sendPushNotification(
            $validated['title'],
            $validated['body'],
            $validated['url'] ?? null,
            $validated['tag'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * 获取订阅列表
     */
    public function subscriptions(): JsonResponse
    {
        $subscriptions = $this->pwaService->getSubscriptions();

        $list = array_map(function ($sub) {
            return [
                'endpoint_prefix' => substr($sub['endpoint'] ?? '', 0, 50) . '...',
                'user_agent' => $sub['user_agent'] ?? 'unknown',
                'subscribed_at' => $sub['subscribed_at'] ?? null,
                'expires_at' => $sub['expires_at'] ?? null,
                'ip' => $sub['ip'] ?? null,
            ];
        }, $subscriptions);

        return response()->json([
            'success' => true,
            'data' => $list,
            'total' => count($list),
        ]);
    }

    /**
     * 清除缓存
     */
    public function clearCache(): JsonResponse
    {
        $this->authorize('updateSystem', \App\Models\User::class);

        $result = $this->pwaService->clearCache();

        return response()->json($result);
    }

    /**
     * 更新 Service Worker 版本
     */
    public function updateWorker(): JsonResponse
    {
        $this->authorize('updateSystem', \App\Models\User::class);

        $result = $this->pwaService->updateServiceWorker();

        return response()->json($result);
    }
}
