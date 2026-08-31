<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Grpc\GrpcManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrpcController extends Controller
{
    public function __construct(
        protected GrpcManagerService $grpcManager
    ) {}

    /**
     * 仪表盘
     * GET /api/v1/admin/grpc/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->grpcManager->getDashboard();

        return ApiResponse::success($data, __('app.api.grpc.dashboard_fetched'));
    }

    /**
     * 健康检查
     * GET /api/v1/admin/grpc/health
     */
    public function health(): JsonResponse
    {
        $data = $this->grpcManager->healthCheck();

        return ApiResponse::success($data, __('app.api.grpc.health_check_done'));
    }

    /**
     * 配置状态
     * GET /api/v1/admin/grpc/config
     */
    public function config(): JsonResponse
    {
        $data = $this->grpcManager->getConfig();

        return ApiResponse::success($data, __('app.api.grpc.config_fetched'));
    }

    /**
     * 端点信息
     * GET /api/v1/admin/grpc/endpoints
     */
    public function endpoints(): JsonResponse
    {
        $data = $this->grpcManager->getEndpoints();

        return ApiResponse::success($data, __('app.api.grpc.endpoints_fetched'));
    }

    /**
     * 熔断器状态
     * GET /api/v1/admin/grpc/circuit-breaker
     */
    public function circuitBreaker(): JsonResponse
    {
        $data = $this->grpcManager->getAllCircuitBreakerStatus();

        return ApiResponse::success($data, __('app.api.grpc.circuit_breaker_status'));
    }

    /**
     * 重置熔断器
     * POST /api/v1/admin/grpc/reset-circuit-breaker
     */
    public function resetCircuitBreaker(): JsonResponse
    {
        $this->grpcManager->resetAllCircuitBreakers();

        return ApiResponse::success(null, __('app.api.grpc.circuit_breaker_reset'));
    }

    // ─── gRPC REST 回退端点（内部使用） ────────────

    /**
     * License 服务 REST 回退
     * POST /api/v1/grpc/license
     */
    public function restLicense(Request $request): JsonResponse
    {
        $method = $request->header('X-Grpc-Method', 'health');
        $payload = $request->all();

        try {
            $service = $this->grpcManager->license();
            $result = $service->$method(...$this->extractArgs($method, $payload));

            return ApiResponse::success($result, __('app.api.grpc.license_call_success'));
        } catch (\Throwable $e) {
            return ApiResponse::success(['error' => $e->getMessage()], __('app.api.grpc.license_call_failed'), false, 500);
        }
    }

    /**
     * Device 服务 REST 回退
     * POST /api/v1/grpc/device
     */
    public function restDevice(Request $request): JsonResponse
    {
        $method = $request->header('X-Grpc-Method', 'health');
        $payload = $request->all();

        try {
            $service = $this->grpcManager->device();
            $result = $service->$method(...$this->extractArgs($method, $payload));

            return ApiResponse::success($result, __('app.api.grpc.device_call_success'));
        } catch (\Throwable $e) {
            return ApiResponse::success(['error' => $e->getMessage()], __('app.api.grpc.device_call_failed'), false, 500);
        }
    }

    /**
     * Billing 服务 REST 回退
     * POST /api/v1/grpc/billing
     */
    public function restBilling(Request $request): JsonResponse
    {
        $method = $request->header('X-Grpc-Method', 'health');
        $payload = $request->all();

        try {
            $service = $this->grpcManager->billing();
            $result = $service->$method(...$this->extractArgs($method, $payload));

            return ApiResponse::success($result, __('app.api.grpc.billing_call_success'));
        } catch (\Throwable $e) {
            return ApiResponse::success(['error' => $e->getMessage()], __('app.api.grpc.billing_call_failed'), false, 500);
        }
    }

    /**
     * Notification 服务 REST 回退
     * POST /api/v1/grpc/notification
     */
    public function restNotification(Request $request): JsonResponse
    {
        $method = $request->header('X-Grpc-Method', 'health');
        $payload = $request->all();

        try {
            $service = $this->grpcManager->notification();
            $result = $service->$method(...$this->extractArgs($method, $payload));

            return ApiResponse::success($result, __('app.api.grpc.notification_call_success'));
        } catch (\Throwable $e) {
            return ApiResponse::success(['error' => $e->getMessage()], __('app.api.grpc.notification_call_failed'), false, 500);
        }
    }

    /**
     * 提取方法参数
     */
    protected function extractArgs(string $method, array $payload): array
    {
        // 对于有明确参数映射的方法，按顺序提取
        $argMap = [
            'activate' => ['licenseKey' => 'license_key', 'deviceFingerprint' => 'device_fingerprint', 'metadata' => 'metadata'],
            'validate' => ['licenseKey' => 'license_key', 'deviceFingerprint' => 'device_fingerprint'],
            'revoke' => ['licenseKey' => 'license_key', 'reason' => 'reason'],
            'suspend' => ['licenseKey' => 'license_key', 'reason' => 'reason'],
            'unsuspend' => ['licenseKey' => 'license_key'],
            'getLicense' => ['licenseKey' => 'license_key'],
            'checkFeature' => ['licenseKey' => 'license_key', 'featureKey' => 'feature_key'],
            'registerDevice' => ['licenseKey' => 'license_key', 'fingerprint' => 'fingerprint', 'info' => 'info'],
            'getDevice' => ['deviceId' => 'device_id'],
            'listDevices' => ['licenseId' => 'license_id'],
            'updateTrustScore' => ['deviceId' => 'device_id', 'score' => 'trust_score', 'reason' => 'reason'],
            'removeDevice' => ['deviceId' => 'device_id', 'reason' => 'reason'],
            'blacklistDevice' => ['deviceId' => 'device_id', 'reason' => 'reason'],
            'matchFingerprint' => ['licenseId' => 'license_id', 'fingerprint' => 'fingerprint'],
            'createSubscription' => ['customerId' => 'customer_id', 'productId' => 'product_id', 'planType' => 'plan_type', 'billingCycle' => 'billing_cycle', 'amount' => 'amount'],
            'getSubscription' => ['subscriptionId' => 'subscription_id'],
            'cancelSubscription' => ['subscriptionId' => 'subscription_id', 'reason' => 'reason'],
            'getInvoice' => ['invoiceId' => 'invoice_id'],
            'listInvoices' => ['customerId' => 'customer_id'],
            'recordUsage' => ['subscriptionId' => 'subscription_id', 'metricKey' => 'metric_key', 'quantity' => 'quantity'],
            'getUsage' => ['subscriptionId' => 'subscription_id', 'metricKey' => 'metric_key'],
            'checkQuota' => ['subscriptionId' => 'subscription_id', 'metricKey' => 'metric_key', 'requestedAmount' => 'requested_amount'],
            'send' => ['userIds' => 'user_ids', 'type' => 'type', 'title' => 'title', 'body' => 'body', 'channel' => 'channel'],
            'sendBatch' => ['notifications' => 'notifications'],
            'getHistory' => ['userId' => 'user_id'],
            'markAsRead' => ['userId' => 'user_id', 'notificationIds' => 'notification_ids'],
            'getUnreadCount' => ['userId' => 'user_id'],
            'pushWebhook' => ['eventType' => 'event_type', 'payload' => 'payload_json', 'targetUrl' => 'target_url'],
            'sendEmail' => ['to' => 'to', 'subject' => 'subject', 'body' => 'body_html'],
        ];

        $args = [];
        if (isset($argMap[$method])) {
            foreach ($argMap[$method] as $phpArg => $payloadKey) {
                if (isset($payload[$payloadKey])) {
                    $args[] = $payload[$payloadKey];
                }
            }
        }

        return $args;
    }
}
