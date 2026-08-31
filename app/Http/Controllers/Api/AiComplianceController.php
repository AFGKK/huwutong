<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ISO 42001 AI 管理系统合规 (M2-140)
 */
class AiComplianceController extends Controller
{
    public function __construct(
        protected AiComplianceService $compliance,
    ) {}

    // ─── 看板 ───

    public function dashboard(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->dashboard()]);
    }

    // ─── AI 系统清单 ───

    public function listSystems(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->listSystems($request)]);
    }

    public function storeSystem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'version' => 'required|string|max:50',
            'purpose' => 'required|string',
            'provider' => 'nullable|string|max:200',
            'deployment_status' => 'required|in:development,staging,production,retired',
            'risk_level' => 'required|in:low,medium,high,critical',
            'owner_department' => 'nullable|string|max:100',
            'owner_email' => 'nullable|email|max:200',
            'capabilities' => 'nullable|array',
            'limitations' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->storeSystem($validated)], 201);
    }

    public function showSystem(int $id): JsonResponse
    {
        $system = \App\Models\AiSystemRegistry::withCount([
            'riskAssessments', 'biasDetections', 'decisionLogs', 'trainingDataSources', 'disclosures'
        ])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $system]);
    }

    public function updateSystem(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'version' => 'sometimes|string|max:50',
            'purpose' => 'sometimes|string',
            'provider' => 'nullable|string|max:200',
            'deployment_status' => 'sometimes|in:development,staging,production,retired',
            'risk_level' => 'sometimes|in:low,medium,high,critical',
            'owner_department' => 'nullable|string|max:100',
            'owner_email' => 'nullable|email|max:200',
            'capabilities' => 'nullable|array',
            'limitations' => 'nullable|array',
            'tags' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->updateSystem($id, $validated)]);
    }

    public function destroySystem(int $id): JsonResponse
    {
        $this->compliance->deleteSystem($id);
        return response()->json(['success' => true, 'message' => __('app.common.deleted')]);
    }

    // ─── 风险影响评估 ───

    public function listAssessments(int $systemId, Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->listAssessments($systemId, $request)]);
    }

    public function storeAssessment(int $systemId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assessor_name' => 'required|string|max:100',
            'severity' => 'required|in:negligible,minor,moderate,major,critical',
            'likelihood_score' => 'required|numeric|min:0|max:1',
            'impact_score' => 'required|numeric|min:0|max:1',
            'impact_analysis' => 'nullable|array',
            'mitigation_measures' => 'nullable|string',
            'residual_risk' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->storeAssessment($systemId, $validated)], 201);
    }

    // ─── 偏见检测 ───

    public function listBiasDetections(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->listBiasDetections($request)]);
    }

    public function storeBiasDetection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_system_id' => 'required|exists:ai_system_registries,id',
            'metric' => 'required|string|max:50',
            'score' => 'required|numeric|min:0|max:1',
            'description' => 'nullable|string',
            'segment_data' => 'nullable|array',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->storeBiasDetection($validated)], 201);
    }

    public function mitigateBias(int $id, Request $request): JsonResponse
    {
        $request->validate(['mitigation_action' => 'required|string']);
        return response()->json(['success' => true, 'data' => $this->compliance->mitigateBias($id, $request->mitigation_action)]);
    }

    public function resolveBias(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->resolveBias($id)]);
    }

    // ─── 训练数据来源 ───

    public function listTrainingData(int $systemId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->listTrainingData($systemId)]);
    }

    public function storeTrainingData(int $systemId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_name' => 'required|string|max:200',
            'source_type' => 'required|in:public,internal,third_party,synthetic',
            'description' => 'nullable|string',
            'collection_method' => 'nullable|string|max:100',
            'has_pii' => 'sometimes|boolean',
            'has_sensitive_data' => 'sometimes|boolean',
            'license' => 'nullable|string|max:200',
            'record_count' => 'nullable|integer|min:0',
            'date_range_start' => 'nullable|string|max:20',
            'date_range_end' => 'nullable|string|max:20',
            'preprocessing' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->storeTrainingData($systemId, $validated)], 201);
    }

    public function destroyTrainingData(int $id): JsonResponse
    {
        $this->compliance->deleteTrainingData($id);
        return response()->json(['success' => true, 'message' => __('app.common.deleted')]);
    }

    // ─── 透明度披露 ───

    public function listDisclosures(int $systemId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->listDisclosures($systemId)]);
    }

    public function storeDisclosure(int $systemId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:10',
            'disclosure_text' => 'required|string',
            'disclosure_type' => 'required|in:decision,batch,general',
            'is_active' => 'sometimes|boolean',
            'effective_from' => 'nullable|date',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->storeDisclosure($systemId, $validated)], 201);
    }

    // ─── AI 决策审计日志 ───

    public function listDecisionLogs(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->listDecisionLogs($request)]);
    }

    public function showDecisionLog(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->showDecisionLog($id)]);
    }

    public function storeDecisionLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_system_id' => 'nullable|exists:ai_system_registries,id',
            'model_name' => 'nullable|string|max:200',
            'decision_type' => 'required|string|max:100',
            'input_summary' => 'nullable|string',
            'output_summary' => 'nullable|string',
            'full_input' => 'nullable|array',
            'full_output' => 'nullable|array',
            'confidence_score' => 'nullable|numeric|min:0|max:1',
            'customer_id' => 'nullable|string|max:64',
            'tenant_id' => 'nullable|string|max:64',
            'result' => 'required|in:approved,rejected,flagged',
            'ip_address' => 'nullable|string|max:45',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->logDecision($validated)], 201);
    }

    // ─── 人工申诉 Override ───

    public function listOverrides(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->listOverrideRequests($request)]);
    }

    public function storeOverride(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_decision_log_id' => 'nullable|exists:ai_decision_logs,id',
            'customer_identifier' => 'required|string|max:200',
            'customer_email' => 'nullable|email|max:200',
            'reason' => 'required|string',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->storeOverrideRequest($validated)], 201);
    }

    public function processOverride(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:in_review,resolved,rejected',
            'assigned_to' => 'nullable|string|max:100',
            'resolution_notes' => 'nullable|string',
            'final_decision' => 'nullable|in:override,uphold,partially',
        ]);
        return response()->json(['success' => true, 'data' => $this->compliance->processOverride($id, $validated)]);
    }

    // ─── 合规差距分析 & 报告 ───

    public function gapAnalysis(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->gapAnalysis()]);
    }

    public function complianceReport(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->compliance->complianceReport()]);
    }
}
