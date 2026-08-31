<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PiracyEvidence;
use App\Models\PiracyForensicReport;
use App\Models\PiracyScanTask;
use App\Services\PiracyTraceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PiracyTraceController extends Controller
{
    public function __construct(protected PiracyTraceService $service) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboard());
    }

    /**
     * 扫描任务列表
     */
    public function scanTasks(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            PiracyScanTask::with('creator:id,name')
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    /**
     * 创建扫描任务
     */
    public function createScan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => 'required|in:github,manual',
            'query' => 'nullable|string|max:500',
        ]);

        $task = PiracyScanTask::create([
            'source' => $validated['source'],
            'query' => $validated['query'] ?? null,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created($task, __("app.piracy_trace.msg_be996e10"));
    }

    /**
     * 执行扫描
     */
    public function runScan(PiracyScanTask $scanTask): JsonResponse
    {
        if ($scanTask->status === 'running') {
            return ApiResponse::error('SCAN_IN_PROGRESS', __("app.piracy_trace.msg_9cd347a5"), 409);
        }

        // 异步执行 - 实际应使用队列
        $this->service->runScan($scanTask->id);

        return ApiResponse::success(
            $scanTask->fresh()->load('creator:id,name'),
            __('app.piracy_trace.scan_completed')
        );
    }

    /**
     * 证据列表
     */
    public function evidence(Request $request): JsonResponse
    {
        $query = PiracyEvidence::with(['scanTask', 'license']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('confidence_level')) {
            $query->where('confidence_level', $request->confidence_level);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('detected_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    /**
     * 证据详情
     */
    public function showEvidence(PiracyEvidence $evidence): JsonResponse
    {
        $evidence->load(['scanTask', 'license.customer', 'forensicReports']);
        return ApiResponse::success($evidence);
    }

    /**
     * 更新证据状态
     */
    public function updateEvidence(Request $request, PiracyEvidence $evidence): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:open,investigating,confirmed,false_positive,resolved',
            'confidence_level' => 'nullable|in:low,medium,high,confirmed',
            'notes' => 'nullable|string|max:1000',
            'assignee' => 'nullable|string|max:100',
        ]);

        if (in_array($validated['status'] ?? '', ['resolved', 'false_positive'])) {
            $validated['resolved_at'] = now();
        }

        $evidence->update($validated);
        return ApiResponse::success($evidence->fresh(), __('app.piracy_trace.evidence_updated'));
    }

    /**
     * 自动处理（吊销+通知+取证）
     */
    public function autoRemediate(PiracyEvidence $evidence): JsonResponse
    {
        $result = $this->service->autoRemediate($evidence);
        return $result['action_taken']
            ? ApiResponse::success($result, __("app.piracy_trace.msg_7e619d45"))
            : ApiResponse::error('REMEDIATE_FAILED', $result['reason'], 400);
    }

    /**
     * 生成取证报告
     */
    public function generateReport(Request $request, PiracyEvidence $evidence): JsonResponse
    {
        $report = $this->service->generateForensicReport($evidence);
        return ApiResponse::created($report->load('generator:id,name'), __('app.piracy_trace.forensic_report_generated'));
    }

    /**
     * 取证报告列表
     */
    public function forensicReports(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            PiracyForensicReport::with(['evidence', 'generator:id,name'])
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    /**
     * 取证报告详情
     */
    public function showReport(PiracyForensicReport $report): JsonResponse
    {
        $report->load(['evidence.license.customer', 'generator:id,name']);
        return ApiResponse::success($report);
    }
}
