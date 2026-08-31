<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AutoDeliveryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 自动发货引擎管理 (M2-142 🛒)
 */
class AutoDeliveryController extends Controller
{
    public function __construct(protected AutoDeliveryEngine $deliveryEngine) {}

    /**
     * 执行订单自动发货
     */
    public function execute(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with(['items.sku', 'deliveries', 'customer.user'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($orderId);

        if ($order->status !== 'paid') {
            return ApiResponse::error('ORDER_NOT_PAID', __("app.auto_delivery.msg_cce3d4c5"), 400);
        }

        $result = $this->deliveryEngine->execute($order);

        if ($result['success']) {
            return ApiResponse::success($result, __("app.auto_delivery.msg_5dc87a90"));
        }

        return ApiResponse::error('DELIVERY_FAILED', $result['error'] ?? __("app.auto_delivery.msg_05d62326"), 500);
    }

    /**
     * 重试失败的发货
     */
    public function retry(Request $request, int $deliveryId): JsonResponse
    {
        $result = $this->deliveryEngine->retryDelivery($deliveryId);

        if (!empty($result['error'])) {
            return ApiResponse::error('RETRY_FAILED', $result['error'], 500);
        }

        return ApiResponse::success($result, __("app.auto_delivery.msg_5c86d115"));
    }

    /**
     * 手动补发通知
     */
    public function resend(Request $request, int $deliveryId): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'nullable|string|in:email,webhook,api_callback',
        ]);

        $result = $this->deliveryEngine->resendDelivery($deliveryId, $validated['channel'] ?? 'email');

        if (!empty($result['error'])) {
            return ApiResponse::error('RESEND_FAILED', $result['error'], 500);
        }

        return ApiResponse::success($result, $result['message'] ?? __("app.auto_delivery.msg_59948ce8"));
    }

    /**
     * 发货统计
     */
    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->deliveryEngine->getStats($request->user()->tenant_id)
        );
    }

    /**
     * 多渠道看板统计
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $stats = \App\Models\Delivery::whereHas('order', fn($q) => $q->where('tenant_id', $tenantId));

        $total = (clone $stats)->count();
        $delivered = (clone $stats)->where('status', 'delivered')->count();
        $failed = (clone $stats)->where('status', 'failed')->count();
        $pending = (clone $stats)->where('status', 'pending')->count();
        $emailSent = (clone $stats)->where('email_sent', true)->count();
        $webhookPushed = (clone $stats)->where('webhook_pushed', true)->count();
        $apiCallbackSent = (clone $stats)->where('api_callback_sent', true)->count();

        $todayStats = (clone $stats)->whereDate('created_at', today());
        $todayDelivered = (clone $todayStats)->where('status', 'delivered')->count();

        // 各渠道统计
        $channelBreakdown = [
            'email' => [
                'sent' => $emailSent,
                'rate' => $delivered > 0 ? round(($emailSent / $delivered) * 100, 1) : 0,
            ],
            'webhook' => [
                'pushed' => $webhookPushed,
                'rate' => $delivered > 0 ? round(($webhookPushed / $delivered) * 100, 1) : 0,
            ],
            'api_callback' => [
                'sent' => $apiCallbackSent,
                'rate' => $delivered > 0 ? round(($apiCallbackSent / $delivered) * 100, 1) : 0,
            ],
        ];

        // 最近交付
        $recentDeliveries = \App\Models\Delivery::with([
            'order:id,order_no,status',
            'orderItem:id,name',
        ])->whereHas('order', fn($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'total' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'pending' => $pending,
            'today_delivered' => $todayDelivered,
            'email_sent' => $emailSent,
            'webhook_pushed' => $webhookPushed,
            'api_callback_sent' => $apiCallbackSent,
            'channel_breakdown' => $channelBreakdown,
            'recent_deliveries' => $recentDeliveries,
        ]);
    }

    /**
     * 发货列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->deliveryEngine->getDeliveries($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * 发货详情
     */
    public function show(Request $request, int $deliveryId): JsonResponse
    {
        $delivery = \App\Models\Delivery::with([
            'order:id,order_no,status,final_amount,paid_at,tenant_id',
            'orderItem:id,name,quantity,subtotal',
            'order.customer.user:id,name,email',
        ])->findOrFail($deliveryId);

        // 获取日志
        $logs = \App\Models\DeliveryLog::where('delivery_id', $deliveryId)
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success([
            'delivery' => $delivery,
            'logs' => $logs,
        ]);
    }

    /**
     * 批量重试失败发货
     */
    public function batchRetry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_ids' => 'required|array',
            'delivery_ids.*' => 'integer|exists:deliveries,id',
        ]);

        $results = [];
        foreach ($validated['delivery_ids'] as $id) {
            $results[] = [
                'delivery_id' => $id,
                'result' => $this->deliveryEngine->retryDelivery($id),
            ];
        }

        return ApiResponse::success($results, __("app.auto_delivery.msg_25d57db4"));
    }
}
