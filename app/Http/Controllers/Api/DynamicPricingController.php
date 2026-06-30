<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DynamicPricingRule;
use App\Models\PricingExperiment;
use App\Models\PricingPlan;
use App\Models\PricingTier;
use App\Services\DynamicPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DynamicPricingController extends Controller
{
    public function __construct(
        protected DynamicPricingService $pricingEngine,
    ) {}

    // ─── 阶梯定价管理 ──────────────────────────────────────────

    public function tiers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pricing_plan_id' => 'required|exists:pricing_plans,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tiers = PricingTier::where('pricing_plan_id', $request->input('pricing_plan_id'))
            ->orderBy('from_quantity')
            ->get();

        return ApiResponse::success($tiers);
    }

    public function storeTier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pricing_plan_id' => 'required|exists:pricing_plans,id',
            'name' => 'required|string|max:100',
            'from_quantity' => 'required|integer|min:1',
            'to_quantity' => 'nullable|integer|gt:from_quantity',
            'unit_price' => 'required|numeric|min:0',
            'flat_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['flat_fee'] ??= 0;

        $tier = PricingTier::create($data);

        return ApiResponse::created($tier, '阶梯定价已创建');
    }

    public function updateTier(Request $request, int $id): JsonResponse
    {
        $tier = PricingTier::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'from_quantity' => 'sometimes|integer|min:1',
            'to_quantity' => 'nullable|integer|gt:from_quantity',
            'unit_price' => 'sometimes|numeric|min:0',
            'flat_fee' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tier->update($validator->validated());

        return ApiResponse::success($tier->fresh(), '阶梯定价已更新');
    }

    public function destroyTier(int $id): JsonResponse
    {
        $tier = PricingTier::findOrFail($id);
        $tier->delete();

        return ApiResponse::success(null, '阶梯定价已删除');
    }

    // ─── 动态定价规则管理 ──────────────────────────────────────

    public function rules(Request $request): JsonResponse
    {
        $query = DynamicPricingRule::query();

        if ($request->filled('rule_type')) {
            $query->where('rule_type', $request->input('rule_type'));
        }
        if ($request->filled('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min((int) ($request->input('per_page') ?? 50), 100);
        return ApiResponse::success($query->orderBy('priority')->orderBy('id')->paginate($perPage));
    }

    public function showRule(int $id): JsonResponse
    {
        $rule = DynamicPricingRule::findOrFail($id);
        return ApiResponse::success($rule);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:100|unique:dynamic_pricing_rules,slug',
            'description' => 'nullable|string',
            'rule_type' => 'required|string|in:volume,segment,time_seasonal,time_hourly,promotion,llm_optimized',
            'target_type' => 'required|string|in:plan,customer,segment,product',
            'target_id' => 'nullable|integer',
            'target_ids' => 'nullable|array',
            'target_ids.*' => 'integer',
            'adjustment_type' => 'required|string|in:percentage,fixed,override,formula',
            'adjustment_value' => 'required|numeric',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'conditions' => 'nullable|array',
            'schedule' => 'nullable|array',
            'timezone' => 'nullable|string|max:50',
            'priority' => 'nullable|integer|min:0|max:9999',
            'stack_mode' => 'nullable|string|in:replace,add,multiply,compound',
            'allowed_stack_with' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['slug'] ??= Str::slug($data['name']) . '-' . Str::random(6);
        $data['tenant_id'] ??= $request->user()?->tenant_id;

        $rule = DynamicPricingRule::create($data);

        return ApiResponse::created($rule, '定价规则已创建');
    }

    public function updateRule(Request $request, int $id): JsonResponse
    {
        $rule = DynamicPricingRule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'rule_type' => 'sometimes|string|in:volume,segment,time_seasonal,time_hourly,promotion,llm_optimized',
            'target_type' => 'sometimes|string|in:plan,customer,segment,product',
            'target_id' => 'nullable|integer',
            'target_ids' => 'nullable|array',
            'target_ids.*' => 'integer',
            'adjustment_type' => 'sometimes|string|in:percentage,fixed,override,formula',
            'adjustment_value' => 'sometimes|numeric',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'conditions' => 'nullable|array',
            'schedule' => 'nullable|array',
            'timezone' => 'nullable|string|max:50',
            'priority' => 'nullable|integer|min:0|max:9999',
            'stack_mode' => 'nullable|string|in:replace,add,multiply,compound',
            'allowed_stack_with' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $rule->update($validator->validated());

        return ApiResponse::success($rule->fresh(), '定价规则已更新');
    }

    public function deleteRule(int $id): JsonResponse
    {
        $rule = DynamicPricingRule::findOrFail($id);
        $rule->delete();

        return ApiResponse::success(null, '定价规则已删除');
    }

    public function toggleRule(int $id): JsonResponse
    {
        $rule = DynamicPricingRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);

        return ApiResponse::success([
            'id' => $rule->id,
            'is_active' => $rule->fresh()->is_active,
        ], $rule->is_active ? '规则已启用' : '规则已停用');
    }

    // ─── 定价计算 ──────────────────────────────────────────────

    public function calculatePrice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pricing_plan_id' => 'required|exists:pricing_plans,id',
            'billing_period' => 'required|string|in:monthly,quarterly,semi_annually,yearly',
            'quantity' => 'nullable|integer|min:1',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $plan = PricingPlan::findOrFail($request->input('pricing_plan_id'));

        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = \App\Models\Customer::find($request->input('customer_id'));
        }

        $result = $this->pricingEngine->calculateSubscriptionPrice(
            $plan,
            $request->input('billing_period'),
            $request->input('quantity', 1),
            $customer,
            ['use_tiered' => true]
        );

        return ApiResponse::success($result);
    }

    // ─── 定价模拟 ──────────────────────────────────────────────

    public function simulate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pricing_plan_id' => 'required|exists:pricing_plans,id',
            'scenarios' => 'nullable|array',
            'scenarios.*.quantity' => 'nullable|integer|min:1',
            'scenarios.*.billing_period' => 'nullable|string|in:monthly,quarterly,semi_annually,yearly',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $plan = PricingPlan::findOrFail($request->input('pricing_plan_id'));
        $scenarios = $request->input('scenarios', []);

        $results = $this->pricingEngine->simulatePricing($plan, $scenarios);

        return ApiResponse::success($results);
    }

    // ─── LLM 定价优化 ──────────────────────────────────────────

    public function optimize(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pricing_plan_id' => 'required|exists:pricing_plans,id',
            'market_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $plan = PricingPlan::findOrFail($request->input('pricing_plan_id'));
        $result = $this->pricingEngine->generateOptimizationSuggestions(
            $plan,
            $request->input('market_data', [])
        );

        return ApiResponse::success($result);
    }

    // ─── 应用日志 ──────────────────────────────────────────────

    public function applicationLogs(Request $request): JsonResponse
    {
        $query = \App\Models\PricingRuleApplicationLog::query()
            ->with('appliable')
            ->orderByDesc('created_at');

        if ($request->filled('rule_id')) {
            $query->where('rule_id', $request->input('rule_id'));
        }
        if ($request->filled('context_type')) {
            $query->where('context_type', $request->input('context_type'));
        }

        $perPage = min((int) ($request->input('per_page') ?? 50), 100);
        return ApiResponse::success($query->paginate($perPage));
    }

    // ─── 规则类型元数据 ────────────────────────────────────────

    public function metadata(): JsonResponse
    {
        return ApiResponse::success([
            'rule_types' => [
                ['value' => 'volume', 'label' => '批量折扣'],
                ['value' => 'segment', 'label' => '客户细分定价'],
                ['value' => 'time_seasonal', 'label' => '季节性定价'],
                ['value' => 'time_hourly', 'label' => '时段定价'],
                ['value' => 'promotion', 'label' => '促销定价'],
                ['value' => 'llm_optimized', 'label' => 'LLM 优化定价'],
            ],
            'adjustment_types' => [
                ['value' => 'percentage', 'label' => '百分比折扣'],
                ['value' => 'fixed', 'label' => '固定金额'],
                ['value' => 'override', 'label' => '覆盖价格'],
                ['value' => 'formula', 'label' => '公式计算'],
            ],
            'stack_modes' => [
                ['value' => 'replace', 'label' => '替换'],
                ['value' => 'add', 'label' => '累加'],
                ['value' => 'multiply', 'label' => '乘法'],
                ['value' => 'compound', 'label' => '复利'],
            ],
            'target_types' => [
                ['value' => 'plan', 'label' => '定价方案'],
                ['value' => 'customer', 'label' => '客户'],
                ['value' => 'segment', 'label' => '客户分群'],
                ['value' => 'product', 'label' => '产品'],
            ],
            'pricing_models' => [
                ['value' => 'fixed', 'label' => '固定定价'],
                ['value' => 'tiered', 'label' => '阶梯定价'],
                ['value' => 'usage', 'label' => '用量定价'],
                ['value' => 'hybrid', 'label' => '混合定价'],
            ],
        ]);
    }

    // ═══════════════ 定价实验管理 (M3-26) ═══════════════

    public function experiments(Request $request): JsonResponse
    {
        return ApiResponse::success($this->pricingEngine->listExperiments(
            $request->user()->tenant_id,
            $request->only(['status', 'experiment_type', 'search']),
            $request->input('per_page', 20)
        ));
    }

    public function storeExperiment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'experiment_type' => 'nullable|in:pricing,discount,bundle,tier,promotion',
            'target_metric' => 'nullable|in:conversion,revenue,retention,profit',
            'confidence_level' => 'nullable|integer|min:90|max:99',
            'minimum_sample_size' => 'nullable|integer|min:10',
            'traffic_split' => 'nullable|numeric|min:1|max:99',
            'control_config' => 'nullable|array',
            'treatment_config' => 'nullable|array',
            'segment_filters' => 'nullable|array',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']) . '-' . strtolower(\Illuminate\Support\Str::random(6));
        $validated['created_by'] = $request->user()->id;
        $validated['tenant_id'] = $request->user()->tenant_id;
        $validated['status'] = 'draft';

        $experiment = PricingExperiment::create($validated);

        return ApiResponse::success($experiment, '实验已创建', 201);
    }

    public function showExperiment(PricingExperiment $experiment): JsonResponse
    {
        $experiment->load(['creator:id,name', 'participants', 'events']);
        return ApiResponse::success($experiment);
    }

    public function updateExperiment(Request $request, PricingExperiment $experiment): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:200',
            'description' => 'nullable|string',
            'control_config' => 'nullable|array',
            'treatment_config' => 'nullable|array',
            'segment_filters' => 'nullable|array',
            'traffic_split' => 'nullable|numeric|min:1|max:99',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]);

        $experiment->update($validated);
        return ApiResponse::success($experiment, '实验已更新');
    }

    public function startExperiment(PricingExperiment $experiment): JsonResponse
    {
        if (!in_array($experiment->status, ['draft', 'paused'])) {
            return ApiResponse::error('INVALID_STATUS', '只有草稿或暂停状态可以启动', 422);
        }

        $experiment->update([
            'status' => 'running',
            'starts_at' => $experiment->starts_at ?? now(),
        ]);

        return ApiResponse::success($experiment, '实验已启动');
    }

    public function pauseExperiment(PricingExperiment $experiment): JsonResponse
    {
        if ($experiment->status !== 'running') {
            return ApiResponse::error('INVALID_STATUS', '只有运行中的实验可以暂停', 422);
        }

        $experiment->update(['status' => 'paused']);
        return ApiResponse::success($experiment, '实验已暂停');
    }

    public function completeExperiment(PricingExperiment $experiment): JsonResponse
    {
        if (!in_array($experiment->status, ['running', 'paused'])) {
            return ApiResponse::error('INVALID_STATUS', '无效状态', 422);
        }

        // 计算结果
        $this->pricingEngine->calculateExperimentResults($experiment);

        $experiment->update([
            'status' => 'completed',
            'ends_at' => $experiment->ends_at ?? now(),
        ]);

        return ApiResponse::success($experiment->fresh(), '实验已完成');
    }

    public function calculateResults(PricingExperiment $experiment): JsonResponse
    {
        $result = $this->pricingEngine->calculateExperimentResults($experiment);
        return ApiResponse::success($result);
    }

    public function assignToExperiment(Request $request, PricingExperiment $experiment): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'subscription_id' => 'nullable|integer|exists:subscriptions,id',
            'original_price' => 'nullable|numeric|min:0',
        ]);

        $participant = $this->pricingEngine->assignToExperiment(
            $experiment,
            $validated['customer_id'],
            $validated['subscription_id'] ?? null,
            $validated['original_price'] ?? null,
        );

        return ApiResponse::success($participant, '已分配到实验组', 201);
    }

    public function recordEvent(Request $request, PricingExperiment $experiment): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|in:viewed,converted,churned,upgraded,downgraded,cancelled',
            'participant_id' => 'nullable|integer|exists:pricing_experiment_participants,id',
            'event_data' => 'nullable|array',
        ]);

        $event = $this->pricingEngine->recordExperimentEvent(
            $experiment,
            $validated['event_type'],
            $validated['participant_id'] ?? null,
            $validated['event_data'] ?? null,
        );

        return ApiResponse::success($event, '事件已记录', 201);
    }

    public function experimentStats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $total = PricingExperiment::where('tenant_id', $tenantId)->count();
        $running = PricingExperiment::where('tenant_id', $tenantId)->where('status', 'running')->count();
        $completed = PricingExperiment::where('tenant_id', $tenantId)->where('status', 'completed')->count();
        $totalParticipants = \App\Models\PricingExperimentParticipant::whereHas('experiment', fn($q) => $q->where('tenant_id', $tenantId))->count();

        return ApiResponse::success([
            'total' => $total,
            'running' => $running,
            'completed' => $completed,
            'draft' => PricingExperiment::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'paused' => PricingExperiment::where('tenant_id', $tenantId)->where('status', 'paused')->count(),
            'total_participants' => $totalParticipants,
        ]);
    }

    public function deleteExperiment(PricingExperiment $experiment): JsonResponse
    {
        if (!in_array($experiment->status, ['draft', 'cancelled'])) {
            return ApiResponse::error('DELETE_FAILED', '只能删除草稿或已取消的实验', 400);
        }
        $experiment->delete();
        return ApiResponse::success(null, '实验已删除');
    }

    /**
     * 应用优胜方案 — 将优胜实验组的配置推荐为新的定价方案
     */
    public function applyWinning(PricingExperiment $experiment): JsonResponse
    {
        try {
            $recommendation = $this->pricingEngine->applyWinningTreatment($experiment);
            return ApiResponse::success($recommendation, '优胜方案已生成');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('APPLY_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 获取实验优化建议 — 基于完成的实验生成数据驱动定价优化建议
     */
    public function recommendations(Request $request): JsonResponse
    {
        $result = $this->pricingEngine->generateExperimentRecommendations(
            $request->user()->tenant_id
        );
        return ApiResponse::success($result);
    }

    /**
     * 批量自动分配客户到匹配的实验
     */
    public function batchAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'current_price' => 'nullable|numeric|min:0',
        ]);

        $customer = \App\Models\Customer::findOrFail($validated['customer_id']);

        $assigned = $this->pricingEngine->autoAssignCustomerToExperiments(
            $customer,
            $validated['current_price'] ?? null,
        );

        return ApiResponse::success([
            'assigned_count' => count($assigned),
            'assigned' => $assigned,
        ], count($assigned) > 0 ? '已分配至匹配实验' : '无匹配实验');
    }
}
