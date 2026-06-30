<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AlertEvent;
use App\Models\AlertIntegration;
use App\Models\AlertRule;
use App\Services\AlertEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AlertController extends Controller
{
    public function __construct(
        protected AlertEngineService $alertEngine,
    ) {}

    /**
     * 告警看板数据
     *
     * GET /api/alerts/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->alertEngine->getDashboardData());
    }

    /**
     * 告警规则列表
     *
     * GET /api/alerts/rules?is_active=&metric_type=
     */
    public function rules(Request $request): JsonResponse
    {
        $query = AlertRule::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->filled('metric_type')) {
            $query->where('metric_type', $request->input('metric_type'));
        }

        $rules = $query->orderBy('created_at', 'desc')->get();

        return ApiResponse::success($rules);
    }

    /**
     * 创建告警规则
     *
     * POST /api/alerts/rules
     */
    public function storeRule(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:alert_rules,slug',
            'description' => 'nullable|string',
            'metric_type' => 'required|string|in:license_expiry,activation_burst,heartbeat_missed,payment_failed,apm_slow,sdk_deprecated,certificate_expiry,custom',
            'condition_operator' => 'required|string|in:gt,gte,lt,lte,eq,neq',
            'threshold' => 'required|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:0',
            'severity' => 'sometimes|in:critical,warning,info',
            'channels' => 'sometimes|array',
            'channels.*' => 'in:database,slack,dingtalk,webhook',
            'webhook_urls' => 'nullable|array',
            'webhook_urls.*' => 'string|url',
            'slack_webhook' => 'nullable|string|url',
            'dingtalk_webhook' => 'nullable|string|url',
            'cooldown_minutes' => 'sometimes|integer|min:1',
            'max_alert_per_day' => 'sometimes|integer|min:1',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $rule = AlertRule::create($validator->validated());

        return ApiResponse::created($rule, '告警规则创建成功');
    }

    /**
     * 更新告警规则
     *
     * PUT /api/alerts/rules/{alertRule}
     */
    public function updateRule(Request $request, AlertRule $alertRule): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'metric_type' => 'sometimes|string|in:license_expiry,activation_burst,heartbeat_missed,payment_failed,apm_slow,sdk_deprecated,certificate_expiry,custom',
            'condition_operator' => 'sometimes|string|in:gt,gte,lt,lte,eq,neq',
            'threshold' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:0',
            'severity' => 'sometimes|in:critical,warning,info',
            'channels' => 'sometimes|array',
            'channels.*' => 'in:database,slack,dingtalk,webhook',
            'webhook_urls' => 'nullable|array',
            'webhook_urls.*' => 'string|url',
            'slack_webhook' => 'nullable|string|url',
            'dingtalk_webhook' => 'nullable|string|url',
            'cooldown_minutes' => 'sometimes|integer|min:0',
            'max_alert_per_day' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $alertRule->update($validator->validated());

        return ApiResponse::success($alertRule->fresh(), '告警规则已更新');
    }

    /**
     * 删除告警规则
     *
     * DELETE /api/alerts/rules/{alertRule}
     */
    public function destroyRule(Request $request, AlertRule $alertRule): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $alertRule->events()->delete();
        $alertRule->delete();

        return ApiResponse::success(null, '告警规则已删除');
    }

    /**
     * 告警事件列表
     *
     * GET /api/alerts/events?status=&severity=&alert_rule_id=&page=
     */
    public function events(Request $request): JsonResponse
    {
        $query = AlertEvent::with(['rule:id,name,slug,severity', 'acknowledgedBy:id,name', 'resolvedBy:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('alert_rule_id')) {
            $query->where('alert_rule_id', $request->input('alert_rule_id'));
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }
        if ($request->filled('date_from')) {
            $query->where('fired_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('fired_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        $items = $query->orderByDesc('fired_at')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($items);
    }

    /**
     * 告警事件详情
     *
     * GET /api/alerts/events/{alertEvent}
     */
    public function showEvent(AlertEvent $alertEvent): JsonResponse
    {
        $alertEvent->load(['rule', 'acknowledgedBy:id,name', 'resolvedBy:id,name']);

        return ApiResponse::success($alertEvent);
    }

    /**
     * 确认告警
     *
     * POST /api/alerts/events/{alertEvent}/acknowledge
     */
    public function acknowledgeEvent(Request $request, AlertEvent $alertEvent): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $this->alertEngine->acknowledge($alertEvent, $request->user()->id);

        return ApiResponse::success($alertEvent->fresh(), '告警已确认');
    }

    /**
     * 解决告警
     *
     * POST /api/alerts/events/{alertEvent}/resolve
     */
    public function resolveEvent(Request $request, AlertEvent $alertEvent): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $this->alertEngine->resolve($alertEvent, $request->user()->id);

        return ApiResponse::success($alertEvent->fresh(), '告警已解决');
    }

    /**
     * 手动触发告警
     *
     * POST /api/alerts/fire
     */
    public function fire(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'severity' => 'sometimes|in:critical,warning,info',
            'event_type' => 'sometimes|string|max:100',
            'context' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $event = $this->alertEngine->fireManual(
            $data['title'],
            $data['message'],
            $data['severity'] ?? 'warning',
            $data['event_type'] ?? 'manual',
            $data['context'] ?? null,
        );

        return ApiResponse::created($event, '告警已触发');
    }

    /**
     * 执行全部规则检测
     *
     * POST /api/alerts/evaluate
     */
    public function evaluate(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $stats = $this->alertEngine->evaluateAllRules();

        return ApiResponse::success($stats, '规则检测完成');
    }

    // ── 告警集成 ──

    /**
     * 集成列表
     *
     * GET /api/alerts/integrations
     */
    public function integrations(): JsonResponse
    {
        return ApiResponse::success(AlertIntegration::orderBy('created_at', 'desc')->get());
    }

    /**
     * 创建集成
     *
     * POST /api/alerts/integrations
     */
    public function storeIntegration(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:slack,dingtalk,webhook,email_group',
            'webhook_url' => 'required|string',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'severity_filter' => 'sometimes|in:all,critical,warning,info',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $integration = AlertIntegration::create($validator->validated());

        return ApiResponse::created($integration, '集成创建成功');
    }

    /**
     * 更新集成
     *
     * PUT /api/alerts/integrations/{alertIntegration}
     */
    public function updateIntegration(Request $request, AlertIntegration $alertIntegration): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'webhook_url' => 'sometimes|string',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'severity_filter' => 'sometimes|in:all,critical,warning,info',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $alertIntegration->update($validator->validated());

        return ApiResponse::success($alertIntegration->fresh(), '集成已更新');
    }

    /**
     * 删除集成
     *
     * DELETE /api/alerts/integrations/{alertIntegration}
     */
    public function destroyIntegration(Request $request, AlertIntegration $alertIntegration): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $alertIntegration->delete();

        return ApiResponse::success(null, '集成已删除');
    }

    /**
     * 测试集成连通性
     *
     * POST /api/alerts/integrations/{alertIntegration}/test
     */
    public function testIntegration(Request $request, AlertIntegration $alertIntegration): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', '无权执行此操作', 403);
        }

        $result = $this->alertEngine->testIntegration($alertIntegration);

        return $result
            ? ApiResponse::success(null, '集成测试成功')
            : ApiResponse::error('TEST_FAILED', '集成测试失败，请检查 Webhook URL', 422);
    }

    /**
     * 获取可用的指标类型和模板
     *
     * GET /api/alerts/meta
     */
    public function meta(): JsonResponse
    {
        return ApiResponse::success([
            'metric_types' => $this->alertEngine->getMetricTypeOptions(),
            'severity_options' => $this->alertEngine->getSeverityOptions(),
            'templates' => $this->alertEngine->getAlertTemplates(),
            'operator_options' => [
                'gt' => '大于',
                'gte' => '大于等于',
                'lt' => '小于',
                'lte' => '小于等于',
                'eq' => '等于',
                'neq' => '不等于',
            ],
        ]);
    }
}
