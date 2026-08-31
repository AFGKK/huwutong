<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use App\Models\AlertEscalation;
use App\Models\AlertEvent;
use App\Models\AlertIntegration;
use App\Models\AlertRule;
use App\Services\AlertEngineService;
use App\Services\AlertManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AlertController extends Controller
{
    public function __construct(
        protected AlertEngineService $alertEngine,
        protected AlertManagerService $manager,
    ) {}

    // ═══════════════════════════════════════════════════════
    //  来自原 AlertController — 告警看板、规则、事件、集成、元数据
    // ═══════════════════════════════════════════════════════

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
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
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

        return ApiResponse::created($rule, __('app.api.alert.rule_created'));
    }

    /**
     * 更新告警规则
     *
     * PUT /api/alerts/rules/{alertRule}
     */
    public function updateRule(Request $request, AlertRule $alertRule): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
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

        return ApiResponse::success($alertRule->fresh(), __('app.api.alert.rule_updated'));
    }

    /**
     * 删除告警规则
     *
     * DELETE /api/alerts/rules/{alertRule}
     */
    public function destroyRule(Request $request, AlertRule $alertRule): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
        }

        $alertRule->events()->delete();
        $alertRule->delete();

        return ApiResponse::success(null, __('app.api.alert.rule_deleted'));
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
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
        }

        $this->alertEngine->acknowledge($alertEvent, $request->user()->id);

        return ApiResponse::success($alertEvent->fresh(), __('app.api.alert.acknowledged'));
    }

    /**
     * 解决告警
     *
     * POST /api/alerts/events/{alertEvent}/resolve
     */
    public function resolveEvent(Request $request, AlertEvent $alertEvent): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
        }

        $this->alertEngine->resolve($alertEvent, $request->user()->id);

        return ApiResponse::success($alertEvent->fresh(), __('app.api.alert.resolved'));
    }

    /**
     * 手动触发告警
     *
     * POST /api/alerts/fire
     */
    public function fire(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
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

        return ApiResponse::created($event, __('app.api.alert.triggered'));
    }

    /**
     * 执行全部规则检测
     *
     * POST /api/alerts/evaluate
     */
    public function evaluate(Request $request): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
        }

        $stats = $this->alertEngine->evaluateAllRules();

        return ApiResponse::success($stats, __('app.api.alert.detect_done'));
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
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
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

        return ApiResponse::created($integration, __('app.api.alert.integration_created'));
    }

    /**
     * 更新集成
     *
     * PUT /api/alerts/integrations/{alertIntegration}
     */
    public function updateIntegration(Request $request, AlertIntegration $alertIntegration): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
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

        return ApiResponse::success($alertIntegration->fresh(), __('app.api.alert.integration_updated'));
    }

    /**
     * 删除集成
     *
     * DELETE /api/alerts/integrations/{alertIntegration}
     */
    public function destroyIntegration(Request $request, AlertIntegration $alertIntegration): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
        }

        $alertIntegration->delete();

        return ApiResponse::success(null, __('app.api.alert.integration_deleted'));
    }

    /**
     * 测试集成连通性
     *
     * POST /api/alerts/integrations/{alertIntegration}/test
     */
    public function testIntegration(Request $request, AlertIntegration $alertIntegration): JsonResponse
    {
        if ($request->user()->cannot('admin')) {
            return ApiResponse::error('FORBIDDEN', __('app.api.alert.forbidden'), 403);
        }

        $result = $this->alertEngine->testIntegration($alertIntegration);

        return $result
            ? ApiResponse::success(null, __('app.api.alert.test_ok'))
            : ApiResponse::error('TEST_FAILED', __('app.api.alert.test_failed'), 422);
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
                'gt' => __('app.api.alert.op_gt'),
                'gte' => __('app.api.alert.op_gte'),
                'lt' => __('app.api.alert.op_lt'),
                'lte' => __('app.api.alert.op_lte'),
                'eq' => __('app.api.alert.op_eq'),
                'neq' => __('app.api.alert.op_neq'),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  来自原 AlertingController — 规则详情、渠道、升级策略、事件统计、元数据
    // ═══════════════════════════════════════════════════════

    /**
     * 告警规则详情
     */
    public function showRule(int $id)
    {
        return ApiResponse::success($this->alertEngine->getRule($id));
    }

    /**
     * 通知渠道列表
     */
    public function channels(Request $request)
    {
        return ApiResponse::success($this->alertEngine->getChannels($request->user()->tenant_id));
    }

    /**
     * 创建通知渠道
     */
    public function channelStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'type' => 'required|string|in:email,slack,webhook,sms,dingtalk,feishu,wechat,custom',
            'config' => 'required|array',
            'is_enabled' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->alertEngine->createChannel($data), __('app.common.operation_success'), 201);
    }

    /**
     * 更新通知渠道
     */
    public function channelUpdate(Request $request, AlertChannel $alertChannel)
    {
        return ApiResponse::success($this->alertEngine->updateChannel(
            $alertChannel, $request->only(['name', 'config', 'is_enabled', 'is_default', 'description'])
        ));
    }

    /**
     * 删除通知渠道
     */
    public function channelDestroy(AlertChannel $alertChannel)
    {
        $this->alertEngine->deleteChannel($alertChannel);
        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * 测试通知渠道
     */
    public function testChannel(AlertChannel $alertChannel)
    {
        return ApiResponse::success($this->alertEngine->testChannel($alertChannel));
    }

    /**
     * 升级策略列表
     */
    public function escalations(Request $request)
    {
        return ApiResponse::success($this->alertEngine->getEscalations(
            $request->user()->tenant_id,
            $request->input('alert_rule_id')
        ));
    }

    /**
     * 创建升级策略
     */
    public function escalationStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'alert_rule_id' => 'nullable|integer|exists:alert_rules,id',
            'escalation_level' => 'required|integer|in:1,2,3',
            'after_minutes' => 'required|integer|min:1',
            'notify_type' => 'required|string',
            'notify_target' => 'required|array',
            'is_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->alertEngine->createEscalation($data), __('app.common.operation_success'), 201);
    }

    /**
     * 更新升级策略
     */
    public function escalationUpdate(Request $request, AlertEscalation $alertEscalation)
    {
        return ApiResponse::success($this->alertEngine->updateEscalation(
            $alertEscalation, $request->all()
        ));
    }

    /**
     * 删除升级策略
     */
    public function escalationDestroy(int $id)
    {
        $this->alertEngine->deleteEscalation($id);
        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * 告警事件详情（带通知日志和升级日志）
     */
    public function showAlertingEvent(int $id)
    {
        return ApiResponse::success($this->alertEngine->getEvent($id));
    }

    /**
     * 事件统计
     */
    public function eventStats(Request $request)
    {
        return ApiResponse::success($this->alertEngine->getEventStats(
            $request->user()->tenant_id,
            $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            $request->input('end_date', now()->format('Y-m-d'))
        ));
    }

    /**
     * 指标类型列表
     */
    public function metricTypes()
    {
        return ApiResponse::success($this->alertEngine->getMetricTypes());
    }

    /**
     * 严重程度列表
     */
    public function severities()
    {
        return ApiResponse::success($this->alertEngine->getSeverities());
    }

    // ═══════════════════════════════════════════════════════
    //  来自原 AlertManagerController — 聚合、静默、疲劳、摘要、分析
    // ═══════════════════════════════════════════════════════

    /**
     * 告警管理看板总览
     */
    public function managementDashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->dashboard()]);
    }

    /**
     * 执行告警聚合
     */
    public function aggregate(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->aggregateEvents()]);
    }

    /**
     * 聚合组列表
     */
    public function aggregationGroups(Request $request): JsonResponse
    {
        $hours = min((int) $request->input('hours', 24), 168);
        return response()->json(['success' => true, 'data' => $this->manager->aggregationGroups($hours)]);
    }

    /**
     * 聚合组详情
     */
    public function aggregationDetail(string $groupKey): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->aggregationDetail($groupKey)]);
    }

    /**
     * 静默规则列表
     */
    public function listSilenceRules(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->listSilenceRules($request)]);
    }

    /**
     * 创建静默规则
     */
    public function storeSilenceRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'match_type' => 'required|in:exact,pattern,wildcard',
            'match_rules' => 'nullable|array',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'timezone' => 'nullable|string|max:50',
            'is_recurring' => 'nullable|boolean',
            'recurrence_rule' => 'nullable|string|max:100',
            'reason' => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'data' => $this->manager->storeSilenceRule($validated)], 201);
    }

    /**
     * 更新静默规则
     */
    public function updateSilenceRule(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'match_type' => 'sometimes|in:exact,pattern,wildcard',
            'match_rules' => 'nullable|array',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
            'is_active' => 'sometimes|boolean',
            'reason' => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'data' => $this->manager->updateSilenceRule($id, $validated)]);
    }

    /**
     * 删除静默规则
     */
    public function deleteSilenceRule(int $id): JsonResponse
    {
        $this->manager->deleteSilenceRule($id);
        return response()->json(['success' => true, 'message' => __('app.common.deleted')]);
    }

    /**
     * 切换静默规则状态
     */
    public function toggleSilenceRule(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->toggleSilenceRule($id)]);
    }

    /**
     * 检查告警疲劳状态
     */
    public function checkFatigue(int $ruleId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->checkFatigue($ruleId)]);
    }

    /**
     * 自动告警降级
     */
    public function autoDowngrade(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->autoDowngrade()]);
    }

    /**
     * 疲劳设置列表
     */
    public function listFatigueSettings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->listFatigueSettings()]);
    }

    /**
     * 创建疲劳设置
     */
    public function storeFatigueSetting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'nullable|string|max:50',
            'repetition_threshold' => 'required|integer|min:1|max:100',
            'decay_factor' => 'required|numeric|min:0|max:1',
            'auto_downgrade' => 'nullable|boolean',
            'target_severity' => 'nullable|in:info,warning,critical',
        ]);
        return response()->json(['success' => true, 'data' => $this->manager->storeFatigueSetting($validated)], 201);
    }

    /**
     * 更新疲劳设置
     */
    public function updateFatigueSetting(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'repetition_threshold' => 'sometimes|integer|min:1|max:100',
            'decay_factor' => 'sometimes|numeric|min:0|max:1',
            'auto_downgrade' => 'nullable|boolean',
            'target_severity' => 'nullable|in:info,warning,critical',
        ]);
        return response()->json(['success' => true, 'data' => $this->manager->updateFatigueSetting($id, $validated)]);
    }

    /**
     * 删除疲劳设置
     */
    public function deleteFatigueSetting(int $id): JsonResponse
    {
        $this->manager->deleteFatigueSetting($id);
        return response()->json(['success' => true, 'message' => __('app.common.deleted')]);
    }

    /**
     * 生成告警摘要
     */
    public function generateDigest(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->manager->generateDigest()]);
    }

    /**
     * 噪音分析
     */
    public function noiseAnalysis(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 7), 90);
        return response()->json(['success' => true, 'data' => $this->manager->noiseAnalysis($days)]);
    }

    /**
     * 通知统计
     */
    public function notificationStats(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 7), 90);
        return response()->json(['success' => true, 'data' => $this->manager->notificationStats($days)]);
    }
}
