<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicenseComplianceReport;
use App\Services\Reports\LicenseComplianceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LicenseComplianceReportController extends Controller
{
    public function __construct(
        protected LicenseComplianceReportService $reportService,
    ) {}

    /**
     * 报告列表（管理后台）
     */
    public function index(Request $request)
    {
        $query = LicenseComplianceReport::with(['customer', 'generator']);

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate(20),
        ]);
    }

    /**
     * 客户门户 — 查看自己的报告
     */
    public function myReports(Request $request)
    {
        $user = $request->user();
        $customerId = $user->customer_id ?? $user->tenant?->customer_id;

        $query = LicenseComplianceReport::where('customer_id', $customerId)
            ->orWhereNull('customer_id');

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate(20),
        ]);
    }

    /**
     * 创建并生成报告
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:full_inventory,activation_audit,compliance_summary,custom',
            'format' => 'sometimes|in:xlsx,csv,pdf',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'filters' => 'nullable|array',
            'filters.start_date' => 'nullable|date',
            'filters.end_date' => 'nullable|date',
            'filters.status' => 'nullable|string',
            'filters.product_id' => 'nullable|integer',
            'report_period_start' => 'nullable|date',
            'report_period_end' => 'nullable|date',
        ]);

        $report = LicenseComplianceReport::create([
            'tenant_id' => $request->user()->tenant_id ?? 1,
            'customer_id' => $validated['customer_id'] ?? $request->user()->customer_id ?? null,
            'title' => $this->buildTitle($validated['type'], $validated['filters'] ?? []),
            'type' => $validated['type'],
            'format' => $validated['format'] ?? 'xlsx',
            'status' => 'pending',
            'filters' => $validated['filters'] ?? [],
            'report_period_start' => $validated['report_period_start'] ?? null,
            'report_period_end' => $validated['report_period_end'] ?? null,
            'generated_by' => $request->user()->id,
        ]);

        // 异步生成（队列）
        $service = $this->reportService;
        dispatch(function () use ($report, $service) {
            $service->generate($report);
        })->afterResponse();

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => __('app.controller_compat.license_compliance_report_msg_97'),
        ], 201);
    }

    /**
     * 报告详情
     */
    public function show(LicenseComplianceReport $report)
    {
        $report->load(['customer', 'generator']);
        return response()->json(['success' => true, 'data' => $report]);
    }

    /**
     * 下载报告文件
     */
    public function download(LicenseComplianceReport $report)
    {
        if (!$report->isReady()) {
            return response()->json(['success' => false, 'message' => __('app.controller_compat.license_compliance_report_msg_116')], 400);
        }

        if (!Storage::disk('local')->exists($report->file_path)) {
            return response()->json(['success' => false, 'message' => __('app.controller_compat.license_compliance_report_msg_120')], 404);
        }

        // 记录下载
        $report->update(['downloaded_at' => now()]);

        $mimeTypes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
        ];

        return Storage::disk('local')->download(
            $report->file_path,
            $report->file_name,
            ['Content-Type' => $mimeTypes[$report->format] ?? 'application/octet-stream']
        );
    }

    /**
     * 删除报告
     */
    public function destroy(LicenseComplianceReport $report)
    {
        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }
        $report->delete();

        return response()->json(['success' => true]);
    }

    /**
     * 报告统计
     */
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_reports' => LicenseComplianceReport::count(),
                'completed' => LicenseComplianceReport::where('status', 'completed')->count(),
                'pending' => LicenseComplianceReport::where('status', 'pending')->count(),
                'failed' => LicenseComplianceReport::where('status', 'failed')->count(),
                'by_type' => LicenseComplianceReport::selectRaw('type, count(*) as count')
                    ->groupBy('type')->pluck('count', 'type'),
            ],
        ]);
    }

    protected function buildTitle(string $type, array $filters): string
    {
        $typeLabels = [
            'full_inventory' => __('app.controller_compat.license_compliance_report_license'),
            'activation_audit' => __('app.controller_compat.license_compliance_report_msg_174'),
            'compliance_summary' => __('app.controller_compat.license_compliance_report_msg_175'),
            'custom' => __('app.controller_compat.license_compliance_report_msg_176'),
        ];

        $title = $typeLabels[$type] ?? '合规报告';
        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $start = substr($filters['start_date'] ?? '', 0, 10);
            $end = substr($filters['end_date'] ?? '', 0, 10);
            $title .= " ({$start} ~ {$end})";
        }

        return $title;
    }
}
