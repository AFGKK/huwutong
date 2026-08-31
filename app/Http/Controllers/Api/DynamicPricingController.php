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
            return ApiResponse::validationError(__('app.api.pricing.validation_failed'), $validator->errors()->toArray());
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
            return ApiResponse::validationError(__('app.api.pricing.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['flat_fee'] ??= 0;

        $tier = PricingTier::create($data);

        return ApiResponse::created($tier, __('app.api.pricing.tier_created'));
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
            return ApiResponse::validationError(__('app.api.pricing.validation_failed'), $validator->errors()->toArray());
        }

        $tier->update($validator->validated());

        return ApiResponse::success($tier->fresh(), __('app.api.pricing.tier_updated'));
    }

    public function destroyTier(int $id): JsonResponse
    {
        $tier = PricingTier::findOrFail($id);
        $tier->delete();

        return ApiResponse::success(null, __('app.api.pricing.tier_deleted'));
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
            return ApiResponse::validationError(__('app.api.pricing.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['slug'] ??= Str::slug($data['name']) . '-' . Str::random(6);
        $data['tenant_id'] ??= $request->user()?->tenant_id;

        $rule = DynamicPricingRule::create($data);

        return ApiResponse::created($rule, __('app.api.pricing.rule_created'));
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
            return ApiResponse::validationError(__('app.api.pricing.validation_failed'), $validator->errors()->toArray());
        }

        $rule->update($validator->validated());

        return ApiResponse::success($rule->fresh(), __('app.api.pricing.rule_updated'));
    }

    public function deleteRule(int $id): JsonResponse
    {
        $rule = DynamicPricingRule::findOrFail($id);
        $rule->delete();

        return ApiResponse::success(null, __('app.api.pricing.rule_deleted'));
    }

    public function toggleRule(int $id): JsonResponse
    {
        $rule = DynamicPricingRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);

        return ApiResponse::success([
            'id' => $rule->id,
            'is_active' => $rule->fresh()->is_active,
        ], $rule->is_active ? __('app.api.pricing.rule_enabled') : __('app.api.pricing.rule_disabled'));
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
            return ApiResponse::validationError(__('app.api.pricing.validation_failed'), $validator->errors()->toArray());
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
            return ApiResponse::validationError(__('app.api.pricing.validation_failed'), $validator->errors()->toArray());
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
            return ApiResponse::validationError(__('app.api.pricing.validation_failed'), $validator->errors()->toArray());
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
                ['value' => 'volume', 'label' => __('app.api.pricing.type_volume')],
                ['value' => 'segment', 'label' => __('app.api.pricing.type_segment')],
                ['value' => 'time_seasonal', 'label' => __('app.api.pricing.type_time_seasonal')],
                ['value' => 'time_hourly', 'label' => __('app.api.pricing.type_time_hourly')],
                ['value' => 'promotion', 'label' => __('app.api.pricing.type_promotion')],
                ['value' => 'llm_optimized', 'label' => __('app.api.pricing.type_llm')],
            ],
            'adjustment_types' => [
                ['value' => 'percentage', 'label' => __('app.api.pricing.adj_percentage')],
                ['value' => 'fixed', 'label' => __('app.api.pricing.adj_fixed')],
                ['value' => 'override', 'label' => __('app.api.pricing.adj_override')],
                ['value' => 'formula', 'label' => __('app.api.pricing.adj_formula')],
            ],
            'stack_modes' => [
                ['value' => 'replace', 'label' => __('app.api.pricing.stack_replace')],
                ['value' => 'add', 'label' => __('app.api.pricing.stack_add')],
                ['value' => 'multiply', 'label' => __('app.api.pricing.stack_multiply')],
                ['value' => 'compound', 'label' => __('app.api.pricing.stack_compound')],
            ],
            'target_types' => [
                ['value' => 'plan', 'label' => __('app.api.pricing.target_plan')],
                ['value' => 'customer', 'label' => __('app.api.pricing.target_customer')],
                ['value' => 'segment', 'label' => __('app.api.pricing.target_segment')],
                ['value' => 'product', 'label' => __('app.api.pricing.target_product')],
            ],
            'pricing_models' => [
                ['value' => 'fixed', 'label' => __('app.api.pricing.model_fixed')],
                ['value' => 'tiered', 'label' => __('app.api.pricing.model_tiered')],
                ['value' => 'usage', 'label' => __('app.api.pricing.model_usage')],
                ['value' => 'hybrid', 'label' => __('app.api.pricing.model_hybrid')],
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

        return ApiResponse::success($experiment, __('app.api.pricing.experiment_created'), 201);
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
        return ApiResponse::success($experiment, __('app.api.pricing.experiment_updated'));
    }

    public function startExperiment(PricingExperiment $experiment): JsonResponse
    {
        if (!in_array($experiment->status, ['draft', 'paused'])) {
            return ApiResponse::error('INVALID_STATUS', __('app.api.pricing.start_draft_only'), 422);
        }

        $experiment->update([
            'status' => 'running',
            'starts_at' => $experiment->starts_at ?? now(),
        ]);

        return ApiResponse::success($experiment, __('app.api.pricing.experiment_started'));
    }

    public function pauseExperiment(PricingExperiment $experiment): JsonResponse
    {
        if ($experiment->status !== 'running') {
            return ApiResponse::error('INVALID_STATUS', __('app.api.pricing.pause_running_only'), 422);
        }

        $experiment->update(['status' => 'paused']);
        return ApiResponse::success($experiment, __('app.api.pricing.experiment_paused'));
    }

    public function completeExperiment(PricingExperiment $experiment): JsonResponse
    {
        if (!in_array($experiment->status, ['running', 'paused'])) {
            return ApiResponse::error('INVALID_STATUS', __('app.api.pricing.invalid_status'), 422);
        }

        // 计算结果
        $this->pricingEngine->calculateExperimentResults($experiment);

        $experiment->update([
            'status' => 'completed',
            'ends_at' => $experiment->ends_at ?? now(),
        ]);

        return ApiResponse::success($experiment->fresh(), __('app.api.pricing.experiment_completed'));
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

        return ApiResponse::success($participant, __('app.api.pricing.assigned_group'), 201);
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

        return ApiResponse::success($event, __('app.api.pricing.event_recorded'), 201);
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
            return ApiResponse::error('DELETE_FAILED', __('app.api.pricing.delete_draft_only'), 400);
        }
        $experiment->delete();
        return ApiResponse::success(null, __('app.api.pricing.experiment_deleted'));
    }

    /**
     * 应用优胜方案 — 将优胜实验组的配置推荐为新的定价方案
     */
    public function applyWinning(PricingExperiment $experiment): JsonResponse
    {
        try {
            $recommendation = $this->pricingEngine->applyWinningTreatment($experiment);
            return ApiResponse::success($recommendation, __('app.api.pricing.winner_generated'));
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
        ], count($assigned) > 0 ? __('app.api.pricing.assigned_matched') : __('app.api.pricing.no_matched'));
    }
}
