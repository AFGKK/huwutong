<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RenewalConfig;
use App\Models\RenewalEscalation;
use App\Models\Subscription;
use App\Services\RenewalPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RetentionController extends Controller
{
    public function __construct(
        protected RenewalPipelineService $pipelineService,
    ) {}

    /**
     * 获取续费失败统计
     */
    public function failureStats(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        return response()->json([
            'success' => true,
            'data' => $this->pipelineService->getFailureStats(),
        ]);
    }

    /**
     * 获取指定订阅的失败历史
     */
    public function subscriptionFailures(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        return response()->json([
            'success' => true,
            'data' => $this->pipelineService->getSubscriptionFailureHistory($subscription),
        ]);
    }

    /**
     * 手动触发重试
     */
    public function manualRetry(Subscription $subscription): JsonResponse
    {
        $this->authorize('update', $subscription);

        if ($subscription->status !== 'active' && $subscription->status !== 'grace') {
            return response()->json([
                'success' => false,
                'message' => __('app.controller_compat.retention_msg_56'),
            ], 422);
        }

        $billingService = app(\App\Services\BillingService::class);
        $result = $billingService->manualRenew($subscription);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => __('app.controller_compat.retention_msg_66'),
            ]);
        }

        // 记录到流水线
        if (isset($result['invoice'])) {
            $this->pipelineService->handleRenewalFailure(
                $subscription,
                $result['invoice'],
                $result['error'] ?? 'manual_retry_failed'
            );
        }

        return response()->json([
            'success' => false,
            'message' => __('app.controller_compat.retention_msg_81') . ($result['error'] ?? '未知错误'),
        ], 502);
    }

    /**
     * 获取待处理的人工介入列表
     */
    public function pendingEscalations(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $escalations = RenewalEscalation::with(['subscription.customer.user:id,name'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $escalations,
        ]);
    }

    /**
     * 解决人工介入
     */
    public function resolveEscalation(Request $request, int $escalationId): JsonResponse
    {
        $this->authorize('update', Subscription::class);

        $validator = Validator::make($request->all(), [
            'resolution_note' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = $this->pipelineService->resolveEscalation(
            $escalationId,
            $request->input('resolution_note'),
        );

        return response()->json([
            'success' => $result,
            'message' => $result ? '已处理' : __('app.common.operation_failed'),
        ]);
    }

    // ── 续费策略配置 ──

    /**
     * 获取配置列表
     */
    public function configs(): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        return response()->json([
            'success' => true,
            'data' => $this->pipelineService->getConfigs(),
        ]);
    }

    /**
     * 获取指定配置
     */
    public function getConfig(int $id): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $config = $this->pipelineService->getConfig($id);
        if (! $config) {
            return response()->json(['success' => false, 'message' => __('app.controller_compat.config_not_found')], 404);
        }

        return response()->json(['success' => true, 'data' => $config]);
    }

    /**
     * 创建/更新配置
     */
    public function saveConfig(Request $request, ?int $id = null): JsonResponse
    {
        $this->authorize('update', Subscription::class);

        $rules = [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'max_attempts' => 'integer|min:1|max:20',
            'retry_intervals_days' => 'nullable|array',
            'retry_intervals_days.*' => 'integer|min:1|max:90',
            'downgrade_after_attempt' => 'integer|min:1|max:20',
            'escalate_after_attempt' => 'integer|min:1|max:20',
            'notification_channels' => 'nullable|array',
            'notification_channels.*' => 'string|in:database,mail,sms',
            'reminder_days_before' => 'integer|min:0|max:90',
            'reminder_schedule' => 'nullable|array',
            'reminder_schedule.*' => 'integer|min:0|max:365',
            'retention_coupon_enabled' => 'boolean',
            'retention_coupon_discount_percent' => 'numeric|min:0|max:100',
            'retention_coupon_max_uses' => 'integer|min:1|max:100',
            'retention_coupon_valid_days' => 'integer|min:1|max:365',
            'retention_coupon_max_discount' => 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $config = $this->pipelineService->saveConfig($request->all(), $id);

        return response()->json([
            'success' => true,
            'message' => $id ? __('app.common.configured') : '配置已创建',
            'data' => $config,
        ]);
    }

    /**
     * 切换配置激活状态
     */
    public function toggleConfig(int $id): JsonResponse
    {
        $this->authorize('update', Subscription::class);

        $result = $this->pipelineService->toggleConfigActive($id);

        return response()->json([
            'success' => $result,
            'message' => $result ? '配置状态已切换' : __('app.common.operation_failed'),
        ]);
    }

    /**
     * 删除配置
     */
    public function deleteConfig(int $id): JsonResponse
    {
        $this->authorize('update', Subscription::class);

        $result = $this->pipelineService->deleteConfig($id);

        return response()->json([
            'success' => $result,
            'message' => $result ? '配置已删除' : __('app.common.operation_failed'),
        ]);
    }
}
