<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AutomatedDecisionRecord;
use App\Models\DataBreachNotification;
use App\Models\DpiaRecord;
use App\Models\ProcessingActivityRecord;
use App\Models\SubProcessorAssessment;
use App\Services\GdprEnhancementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GDPR 增强管理控制器 (M3-33 Enhancement)
 *
 * DPIA / 数据泄露通知 / ROPA / 子处理商 / 自动决策记录
 */
class GdprEnhancementController extends Controller
{
    public function __construct(
        protected GdprEnhancementService $service,
    ) {}

    // ═══════════════ DPIA ═══════════════

    public function dpiaIndex(Request $request): JsonResponse
    {
        $data = $this->service->listDpias($request->only(['status', 'processing_type']), $request->input('per_page', 20));
        return ApiResponse::success($data);
    }

    public function dpiaShow(DpiaRecord $dpia): JsonResponse
    {
        $dpia->load(['creator:id,name', 'reviewer:id,name']);
        return ApiResponse::success($dpia);
    }

    public function dpiaStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'processing_type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'data_categories' => 'nullable|array',
            'data_subjects' => 'nullable|array',
            'processing_purposes' => 'nullable|array',
            'necessity_assessment' => 'nullable|string',
            'proportionality_assessment' => 'nullable|string',
            'risks' => 'nullable|array',
            'mitigation_measures' => 'nullable|string',
            'controller_dpo' => 'nullable|string|max:100',
        ]);
        $dpia = $this->service->createDpia($validated);
        return ApiResponse::success($dpia, 'DPIA 已创建', 201);
    }

    public function dpiaUpdate(Request $request, DpiaRecord $dpia): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'data_categories' => 'nullable|array',
            'data_subjects' => 'nullable|array',
            'processing_purposes' => 'nullable|array',
            'necessity_assessment' => 'nullable|string',
            'proportionality_assessment' => 'nullable|string',
            'risks' => 'nullable|array',
            'mitigation_measures' => 'nullable|string',
            'status' => 'nullable|in:draft,in_review',
        ]);
        try {
            $dpia = $this->service->updateDpia($dpia, $validated);
            return ApiResponse::success($dpia, 'DPIA 已更新');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CANNOT_UPDATE', $e->getMessage(), 400);
        }
    }

    public function dpiaReview(Request $request, DpiaRecord $dpia): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'review_notes' => 'nullable|string|max:1000',
        ]);
        $dpia = $this->service->reviewDpia($dpia, $validated['status'], $validated['review_notes'] ?? null);
        return ApiResponse::success($dpia, 'DPIA 审核完成');
    }

    public function dpiaStats(): JsonResponse
    {
        return ApiResponse::success($this->service->getDpiaStats());
    }

    // ═══════════════ 数据泄露通知 ═══════════════

    public function breachIndex(Request $request): JsonResponse
    {
        $data = $this->service->listBreaches($request->only(['status', 'severity']), $request->input('per_page', 20));
        return ApiResponse::success($data);
    }

    public function breachShow(DataBreachNotification $breach): JsonResponse
    {
        $breach->load(['reporter:id,name', 'assignee:id,name']);
        return ApiResponse::success($breach);
    }

    public function breachStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'severity' => 'required|in:critical,high,medium,low',
            'detected_at' => 'required|date',
            'description' => 'required|string',
            'affected_data_categories' => 'nullable|array',
            'affected_users_count' => 'nullable|integer|min:0',
            'containment_actions' => 'nullable|string',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'evidence_refs' => 'nullable|array',
        ]);
        $breach = $this->service->createBreach($validated);
        return ApiResponse::success($breach, '泄露事件已记录', 201);
    }

    public function breachUpdate(Request $request, DataBreachNotification $breach): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'in:detected,assessing,reported,resolved,closed',
            'severity' => 'in:critical,high,medium,low',
            'root_cause' => 'nullable|string',
            'impact_assessment' => 'nullable|string',
            'containment_actions' => 'nullable|string',
            'contained_at' => 'nullable|date',
            'notified_supervisory_authority' => 'boolean',
            'authority_notified_at' => 'nullable|date',
            'authority_response' => 'nullable|string',
            'notified_affected_users' => 'boolean',
            'users_notified_at' => 'nullable|date',
            'remediation_plan' => 'nullable|string',
            'remediated_at' => 'nullable|date',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'evidence_refs' => 'nullable|array',
        ]);
        $breach = $this->service->updateBreach($breach, $validated);
        return ApiResponse::success($breach, '泄露事件已更新');
    }

    public function breachStats(): JsonResponse
    {
        return ApiResponse::success($this->service->getBreachStats());
    }

    // ═══════════════ ROPA ═══════════════

    public function ropaIndex(Request $request): JsonResponse
    {
        $data = $this->service->listRopas($request->only(['status', 'processing_type']), $request->input('per_page', 20));
        return ApiResponse::success($data);
    }

    public function ropaShow(ProcessingActivityRecord $ropa): JsonResponse
    {
        $ropa->load('dpia');
        return ApiResponse::success($ropa);
    }

    public function ropaStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'controller_name' => 'required|string|max:255',
            'controller_contact' => 'nullable|string|max:255',
            'controller_dpo' => 'nullable|string|max:100',
            'processing_type' => 'required|string|max:50',
            'processing_description' => 'required|string',
            'processing_purposes' => 'required|array',
            'data_categories' => 'required|array',
            'data_subjects' => 'required|array',
            'recipients' => 'nullable|array',
            'transfers' => 'nullable|array',
            'retention_period' => 'nullable|string',
            'technical_measures' => 'nullable|array',
            'organizational_measures' => 'nullable|array',
            'has_dpia' => 'boolean',
            'dpia_id' => 'nullable|integer|exists:dpia_records,id',
        ]);
        $ropa = $this->service->createRopa($validated);
        return ApiResponse::success($ropa, 'ROPA 已创建', 201);
    }

    public function ropaUpdate(Request $request, ProcessingActivityRecord $ropa): JsonResponse
    {
        $validated = $request->validate([
            'controller_name' => 'string|max:255',
            'processing_description' => 'string',
            'processing_purposes' => 'array',
            'data_categories' => 'array',
            'data_subjects' => 'array',
            'recipients' => 'nullable|array',
            'transfers' => 'nullable|array',
            'retention_period' => 'nullable|string',
            'technical_measures' => 'nullable|array',
            'organizational_measures' => 'nullable|array',
            'status' => 'in:active,archived',
        ]);
        $ropa = $this->service->updateRopa($ropa, $validated);
        return ApiResponse::success($ropa, 'ROPA 已更新');
    }

    public function ropaStats(): JsonResponse
    {
        return ApiResponse::success($this->service->getRopaStats());
    }

    // ═══════════════ 子处理商 ═══════════════

    public function subProcessorIndex(Request $request): JsonResponse
    {
        $data = $this->service->listSubProcessors($request->only(['status']), $request->input('per_page', 20));
        return ApiResponse::success($data);
    }

    public function subProcessorShow(SubProcessorAssessment $subProcessor): JsonResponse
    {
        return ApiResponse::success($subProcessor);
    }

    public function subProcessorStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'jurisdiction' => 'required|string|max:100',
            'processing_description' => 'required|string',
            'data_categories' => 'nullable|array',
            'security_assessment' => 'nullable|string',
            'certification' => 'nullable|string|max:100',
            'safeguards' => 'nullable|array',
        ]);
        $sp = $this->service->createSubProcessor($validated);
        return ApiResponse::success($sp, '子处理商已添加', 201);
    }

    public function subProcessorUpdate(Request $request, SubProcessorAssessment $subProcessor): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'processing_description' => 'string',
            'data_categories' => 'nullable|array',
            'status' => 'in:pending,approved,rejected,terminated',
            'security_assessment' => 'nullable|string',
            'certification' => 'nullable|string|max:100',
            'has_dpa_signed' => 'boolean',
            'dpa_signed_at' => 'nullable|date',
            'safeguards' => 'nullable|array',
        ]);
        $sp = $this->service->updateSubProcessor($subProcessor, $validated);
        return ApiResponse::success($sp, '子处理商已更新');
    }

    // ═══════════════ 自动决策 ═══════════════

    public function autoDecisionIndex(Request $request): JsonResponse
    {
        $data = $this->service->listAutoDecisions($request->only(['type', 'is_active']), $request->input('per_page', 20));
        return ApiResponse::success($data);
    }

    public function autoDecisionShow(AutomatedDecisionRecord $autoDecision): JsonResponse
    {
        return ApiResponse::success($autoDecision);
    }

    public function autoDecisionStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:automated_decision,profiling',
            'description' => 'required|string',
            'input_data_categories' => 'required|array',
            'output_decision' => 'required|array',
            'logic_explanation' => 'nullable|string',
            'significance' => 'nullable|string',
            'human_intervention_possible' => 'boolean',
            'intervention_method' => 'nullable|string|max:100',
        ]);
        $record = $this->service->createAutoDecision($validated);
        return ApiResponse::success($record, '决策记录已创建', 201);
    }

    public function autoDecisionUpdate(Request $request, AutomatedDecisionRecord $autoDecision): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'string',
            'input_data_categories' => 'array',
            'output_decision' => 'array',
            'logic_explanation' => 'nullable|string',
            'significance' => 'nullable|string',
            'human_intervention_possible' => 'boolean',
            'intervention_method' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);
        $record = $this->service->updateAutoDecision($autoDecision, $validated);
        return ApiResponse::success($record, '决策记录已更新');
    }

    // ═══════════════ 全局统计 ═══════════════

    public function allStats(): JsonResponse
    {
        return ApiResponse::success([
            'dpia' => $this->service->getDpiaStats(),
            'breaches' => $this->service->getBreachStats(),
            'ropa' => $this->service->getRopaStats(),
            'sub_processors' => SubProcessorAssessment::count(),
            'auto_decisions' => AutomatedDecisionRecord::count(),
        ]);
    }
}
