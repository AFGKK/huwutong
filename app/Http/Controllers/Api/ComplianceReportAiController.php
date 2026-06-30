<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ComplianceAiReport;
use App\Services\ComplianceReportAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceReportAiController extends Controller
{
    public function __construct(protected ComplianceReportAiService $service) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboard($request->user()->tenant_id));
    }

    /**
     * 生成报告
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'framework' => 'required|in:gdpr,soc2,iso27001',
            'language' => 'nullable|in:zh-CN,en',
        ]);

        $report = $this->service->generateReport(
            $request->user()->tenant_id,
            $request->user()->id,
            $validated['framework'],
            $validated['language'] ?? 'zh-CN'
        );

        return ApiResponse::success($report->load('generator:id,name'), '报告生成完成');
    }

    /**
     * 报告列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            ComplianceAiReport::with('generator:id,name')
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    /**
     * 报告详情
     */
    public function show(ComplianceAiReport $complianceAiReport): JsonResponse
    {
        $complianceAiReport->load(['generator:id,name', 'evidenceItems']);
        return ApiResponse::success($complianceAiReport);
    }

    /**
     * 获取框架配置
     */
    public function frameworks(): JsonResponse
    {
        return ApiResponse::success(config('compliance-report.frameworks'));
    }
}
