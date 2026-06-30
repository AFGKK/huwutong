<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\AutomationWebhook;
use App\Services\AutomationRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AutomationRuleController extends Controller
{
    public function __construct(
        protected AutomationRuleService $automationService
    ) {}

    // ─── 仪表盘 ───

    public function dashboard(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        return ApiResponse::success($this->automationService->getDashboard($tenantId));
    }

    // ─── 规则 CRUD ───

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        return ApiResponse::success(
            $this->automationService->getRules($request->only(['status', 'category', 'trigger_type', 'search', 'per_page']), $tenantId)
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'category' => 'nullable|string|in:license,billing,customer,security,system,custom',
            'trigger_type' => 'required|string|in:event,schedule,webhook,condition',
            'trigger_config' => 'nullable|array',
            'conditions' => 'nullable|array',
            'conditions.*.field' => 'required_with:conditions|string',
            'conditions.*.operator' => 'required_with:conditions|string',
            'condition_logic' => 'nullable|string|in:all,any',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'action_execution' => 'nullable|string|in:sequential,parallel,first_success',
            'status' => 'nullable|string|in:draft,active,paused',
            'priority' => 'nullable|integer|min:0|max:9999',
            'cooldown_minutes' => 'nullable|integer|min:0',
            'max_executions_per_hour' => 'nullable|integer|min:0',
            'max_executions_per_day' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'webhook_ids' => 'nullable|array',
            'webhook_ids.*' => 'integer|exists:automation_webhooks,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->except('webhook_ids');
        $data['tenant_id'] = $request->user()->tenant_id ?? 1;

        $rule = $this->automationService->createRule($data);

        if ($request->has('webhook_ids')) {
            $rule->webhooks()->sync($request->input('webhook_ids'));
        }

        return ApiResponse::success($rule->load('webhooks'), 201);
    }

    public function show(int $id)
    {
        return ApiResponse::success($this->automationService->getRule($id));
    }

    public function update(Request $request, AutomationRule $rule)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'category' => 'nullable|string|in:license,billing,customer,security,system,custom',
            'trigger_type' => 'nullable|string|in:event,schedule,webhook,condition',
            'trigger_config' => 'nullable|array',
            'conditions' => 'nullable|array',
            'condition_logic' => 'nullable|string|in:all,any',
            'actions' => 'nullable|array|min:1',
            'action_execution' => 'nullable|string|in:sequential,parallel,first_success',
            'status' => 'nullable|string|in:draft,active,paused,archived',
            'priority' => 'nullable|integer|min:0|max:9999',
            'cooldown_minutes' => 'nullable|integer|min:0',
            'max_executions_per_hour' => 'nullable|integer|min:0',
            'max_executions_per_day' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'webhook_ids' => 'nullable|array',
            'webhook_ids.*' => 'integer|exists:automation_webhooks,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $rule = $this->automationService->updateRule($rule, $request->except('webhook_ids'));

        if ($request->has('webhook_ids')) {
            $rule->webhooks()->sync($request->input('webhook_ids'));
        }

        return ApiResponse::success($rule->load('webhooks'));
    }

    public function destroy(AutomationRule $rule)
    {
        $this->automationService->deleteRule($rule);
        return ApiResponse::success(['deleted' => true]);
    }

    public function toggle(AutomationRule $rule)
    {
        return ApiResponse::success($this->automationService->toggleStatus($rule));
    }

    // ─── 执行 ───

    public function execute(Request $request, AutomationRule $rule)
    {
        $context = $request->input('context', []);
        $result = $this->automationService->evaluateAndExecute($rule, $context, 'manual');
        return ApiResponse::success($result);
    }

    // ─── 执行历史 ───

    public function executions(Request $request, int $ruleId)
    {
        $perPage = min((int) ($request->input('per_page', 20)), 100);

        $query = \App\Models\AutomationExecutionLog::with('actionLogs')
            ->where('rule_id', $ruleId);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return ApiResponse::success(
            $query->orderByDesc('created_at')->paginate($perPage)->toArray()
        );
    }

    public function allExecutions(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        $perPage = min((int) ($request->input('per_page', 20)), 100);

        $query = \App\Models\AutomationExecutionLog::with('rule')
            ->whereIn('rule_id', function ($q) use ($tenantId) {
                $q->select('id')->from('automation_rules')->where('tenant_id', $tenantId);
            });

        if ($request->filled('rule_id')) {
            $query->where('rule_id', $request->input('rule_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return ApiResponse::success(
            $query->orderByDesc('created_at')->paginate($perPage)->toArray()
        );
    }

    // ─── 可用选项 ───

    public function triggers()
    {
        return ApiResponse::success($this->automationService->getAvailableTriggers());
    }

    public function actions()
    {
        return ApiResponse::success($this->automationService->getAvailableActions());
    }

    // ─── Webhook 管理 ───

    public function webhooks(Request $request)
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        return ApiResponse::success($this->automationService->getWebhooks($tenantId));
    }

    public function storeWebhook(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'url' => 'required|url|max:1000',
            'method' => 'nullable|string|in:GET,POST,PUT,PATCH,DELETE',
            'headers' => 'nullable|array',
            'body_template' => 'nullable|array',
            'auth_type' => 'nullable|string|in:none,basic,bearer,custom',
            'auth_config' => 'nullable|array',
            'retry_config' => 'nullable|array',
            'timeout_config' => 'nullable|array',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id ?? 1;

        return ApiResponse::success($this->automationService->createWebhook($data), 201);
    }

    public function updateWebhook(Request $request, AutomationWebhook $webhook)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'url' => 'nullable|url|max:1000',
            'method' => 'nullable|string|in:GET,POST,PUT,PATCH,DELETE',
            'headers' => 'nullable|array',
            'body_template' => 'nullable|array',
            'auth_type' => 'nullable|string|in:none,basic,bearer,custom',
            'auth_config' => 'nullable|array',
            'retry_config' => 'nullable|array',
            'timeout_config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->automationService->updateWebhook($webhook, $request->all()));
    }

    public function destroyWebhook(AutomationWebhook $webhook)
    {
        $this->automationService->deleteWebhook($webhook);
        return ApiResponse::success(['deleted' => true]);
    }

    public function testWebhook(AutomationWebhook $webhook)
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders($webhook->headers ?? [])
                ->{$webhook->method ?? 'post'}($webhook->url, $webhook->body_template ?? ['test' => true]);

            return ApiResponse::success([
                'success' => $response->successful(),
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 2000),
            ]);
        } catch (\Exception $e) {
            return ApiResponse::success(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
