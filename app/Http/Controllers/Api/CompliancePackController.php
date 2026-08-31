<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplianceEvidence;
use App\Models\ComplianceGapAnalysis;
use App\Models\CompliancePolicyDocument;
use App\Models\ComplianceQuestionnaire;
use App\Models\ComplianceQuestionnaireResponse;
use App\Services\CompliancePackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * SOC 2 / ISO 27001 合规包控制器
 *
 * @m3-69 CompliancePack
 */
class CompliancePackController extends Controller
{
    public function __construct(
        protected CompliancePackService $compliancePack,
    ) {}

    /**
     * 获取合规包仪表盘
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->compliancePack->getDashboard();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // ─── 审计问卷 ───

    /**
     * 获取问卷模板
     */
    public function questionnaireTemplates(Request $request): JsonResponse
    {
        $validated = $request->validate(['framework' => 'required|in:SOC2,ISO27001']);
        $questions = $this->compliancePack->getQuestionnaireTemplates($validated['framework']);

        return response()->json(['success' => true, 'data' => $questions]);
    }

    /**
     * 获取问卷响应
     */
    public function questionnaireResponses(int $reportId): JsonResponse
    {
        $responses = ComplianceQuestionnaireResponse::with('question')
            ->where('report_id', $reportId)
            ->get();

        return response()->json(['success' => true, 'data' => $responses]);
    }

    /**
     * 提交问卷
     */
    public function submitQuestionnaire(Request $request, int $reportId): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:compliance_questionnaires,id'],
            'answers.*.response' => ['nullable', 'string'],
            'answers.*.evidence_refs' => ['nullable', 'array'],
            'answers.*.notes' => ['nullable', 'string'],
        ]);

        $result = $this->compliancePack->submitResponse($reportId, $validated['answers']);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => __('app.api.compliance_pack.answers_submitted', ['submitted' => $result['submitted']]),
        ]);
    }

    // ─── 证据收集 ───

    /**
     * 获取证据清单
     */
    public function evidenceChecklist(Request $request): JsonResponse
    {
        $validated = $request->validate(['framework' => 'required|in:SOC2,ISO27001']);
        $checklist = $this->compliancePack->getEvidenceChecklist($validated['framework']);

        return response()->json(['success' => true, 'data' => $checklist]);
    }

    /**
     * 证据列表
     */
    public function evidenceList(Request $request): JsonResponse
    {
        $query = ComplianceEvidence::query();

        if ($request->framework) {
            $query->framework($request->framework);
        }
        if ($request->control_ref) {
            $query->control($request->control_ref);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $evidences = $query->with('collector')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $evidences]);
    }

    /**
     * 自动收集证据
     */
    public function collectEvidence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'framework' => 'required|in:SOC2,ISO27001',
            'control_ref' => 'required|string',
            'evidence_type' => 'required|string',
        ]);

        $evidence = $this->compliancePack->collectEvidence(
            $validated['framework'],
            $validated['control_ref'],
            $validated['evidence_type'],
        );

        return response()->json([
            'success' => true,
            'data' => $evidence,
            'message' => __('app.api.compliance_pack.evidence_collected'),
        ]);
    }

    /**
     * 批量收集证据
     */
    public function batchCollectEvidence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'framework' => 'required|in:SOC2,ISO27001',
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.control_ref' => 'required|string',
            'items.*.evidence_type' => 'required|string',
        ]);

        $results = [];
        foreach ($validated['items'] as $item) {
            try {
                $evidence = $this->compliancePack->collectEvidence(
                    $validated['framework'],
                    $item['control_ref'],
                    $item['evidence_type'],
                );
                $results[] = ['control_ref' => $item['control_ref'], 'success' => true, 'id' => $evidence->id];
            } catch (\Throwable $e) {
                $results[] = ['control_ref' => $item['control_ref'], 'success' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => __('app.api.compliance_pack.batch_collection_done'),
        ]);
    }

    /**
     * 验证证据
     */
    public function validateEvidence(Request $request, int $evidenceId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:validated,rejected',
            'notes' => 'nullable|string',
        ]);

        $evidence = $this->compliancePack->validateEvidence(
            $evidenceId,
            $validated['status'],
            $validated['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $evidence,
            'message' => __('app.api.compliance_pack.evidence_updated'),
        ]);
    }

    // ─── 差距分析 ───

    /**
     * 运行差距分析
     */
    public function runGapAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'framework' => 'required|in:SOC2,ISO27001',
            'report_id' => 'required|integer|exists:compliance_reports,id',
        ]);

        $results = $this->compliancePack->runGapAnalysis(
            $validated['framework'],
            $validated['report_id'],
        );

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => __('app.api.compliance_pack.gap_analysis_done'),
        ]);
    }

    /**
     * 差距分析列表
     */
    public function gapAnalysisList(Request $request): JsonResponse
    {
        $query = ComplianceGapAnalysis::query();

        if ($request->framework) {
            $query->framework($request->framework);
        }
        if ($request->status) {
            $query->where('remediation_status', $request->status);
        }
        if ($request->priority) {
            $query->byPriority($request->priority);
        }

        $gaps = $query->orderBy('priority')
            ->orderBy('risk_level')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $gaps]);
    }

    /**
     * 更新整改
     */
    public function updateRemediation(Request $request, int $gapId): JsonResponse
    {
        $validated = $request->validate([
            'remediation_status' => 'required|in:identified,in_progress,completed,waived',
            'remediation_plan' => 'nullable|string',
            'remediation_steps' => 'nullable|array',
            'notes' => 'nullable|string',
            'owner' => 'nullable|string',
            'target_date' => 'nullable|date',
        ]);

        $gap = $this->compliancePack->updateRemediation($gapId, $validated);

        return response()->json([
            'success' => true,
            'data' => $gap,
            'message' => __('app.api.compliance_pack.remediation_updated'),
        ]);
    }

    // ─── 策略文档 ───

    /**
     * 获取策略文档模板列表
     */
    public function policyDocuments(Request $request): JsonResponse
    {
        $validated = $request->validate(['framework' => 'required|in:SOC2,ISO27001']);
        $docs = $this->compliancePack->getPolicyDocuments($validated['framework']);

        return response()->json(['success' => true, 'data' => $docs]);
    }

    /**
     * 生成正式策略文档
     */
    public function generatePolicyDocument(Request $request, int $docId): JsonResponse
    {
        $validated = $request->validate([
            'field_values' => ['required', 'array'],
        ]);

        $fileName = $this->compliancePack->generatePolicyDocument($docId, $validated['field_values']);

        return response()->json([
            'success' => true,
            'data' => ['file' => $fileName],
            'message' => __('app.api.compliance_pack.policy_generated'),
        ]);
    }

    /**
     * 下载策略文档
     */
    public function downloadPolicyDocument(string $fileName): JsonResponse
    {
        if (!Storage::disk('local')->exists("compliance/policies/{$fileName}")) {
            return response()->json(['success' => false, 'message' => __('app.api.compliance_pack.file_not_found')], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url' => Storage::disk('local')->url("compliance/policies/{$fileName}"),
                'name' => $fileName,
            ],
        ]);
    }

    // ─── 报告导出 ───

    /**
     * 导出合规报告 (HTML/PDF)
     */
    public function exportReport(Request $request, int $reportId): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'required|in:html,pdf',
        ]);

        try {
            $fileName = $this->compliancePack->generateComplianceReport($reportId, $validated['format']);

            return response()->json([
                'success' => true,
                'data' => ['file' => $fileName],
                'message' => __('app.api.compliance_pack.report_exported', ['format' => $validated['format']]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.api.compliance_pack.report_export_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * 下载合规报告
     */
    public function downloadReport(string $fileName): JsonResponse
    {
        $directory = 'compliance/reports';

        if (!Storage::disk('local')->exists("{$directory}/{$fileName}")) {
            return response()->json(['success' => false, 'message' => __('app.api.compliance_pack.report_file_not_found')], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url' => Storage::disk('local')->url("{$directory}/{$fileName}"),
                'name' => $fileName,
            ],
        ]);
    }
}
