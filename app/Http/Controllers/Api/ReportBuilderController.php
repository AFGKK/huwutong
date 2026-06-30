<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomReport;
use App\Models\ReportDashboard;
use App\Models\ReportSnapshot;
use App\Services\ReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportBuilderController extends Controller
{
    public function __construct(
        protected ReportBuilderService $reportBuilder,
    ) {}

    // ─── 数据源与元数据 ───

    /**
     * 获取数据源和指标定义
     * GET /api/admin/report-builder/data-sources
     */
    public function dataSources(): JsonResponse
    {
        return ApiResponse::success($this->reportBuilder->getDataSources());
    }

    // ─── 报表 CRUD ───

    /**
     * 报表列表
     * GET /api/admin/report-builder/reports
     */
    public function reports(Request $request): JsonResponse
    {
        $query = CustomReport::with('latestSnapshot')
            ->where('user_id', $request->user()->id);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('data_source')) {
            $query->where('data_source', $request->input('data_source'));
        }
        if ($request->filled('is_template')) {
            $query->where('is_template', $request->boolean('is_template'));
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $reports = $query->orderByDesc('updated_at')->paginate($perPage);

        return ApiResponse::paginated($reports);
    }

    /**
     * 创建报表
     * POST /api/admin/report-builder/reports
     */
    public function createReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|in:' . implode(',', CustomReport::CATEGORIES),
            'data_source' => 'required|string|in:' . implode(',', CustomReport::DATA_SOURCES),
            'metrics' => 'required|array|min:1',
            'dimensions' => 'nullable|array',
            'filters' => 'nullable|array',
            'sorts' => 'nullable|array',
            'chart_type' => 'nullable|string|in:' . implode(',', CustomReport::CHART_TYPES),
            'chart_options' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->tenant_id;

        $report = $this->reportBuilder->createReport($data);
        return ApiResponse::created($report, '报表已创建');
    }

    /**
     * 报表详情
     * GET /api/admin/report-builder/reports/{id}
     */
    public function showReport(int $id): JsonResponse
    {
        $report = CustomReport::with('snapshots')->findOrFail($id);
        return ApiResponse::success($report);
    }

    /**
     * 更新报表
     * PUT /api/admin/report-builder/reports/{id}
     */
    public function updateReport(Request $request, int $id): JsonResponse
    {
        $report = CustomReport::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string|max:2000',
            'category' => 'sometimes|string|in:' . implode(',', CustomReport::CATEGORIES),
            'data_source' => 'sometimes|string|in:' . implode(',', CustomReport::DATA_SOURCES),
            'metrics' => 'sometimes|array|min:1',
            'dimensions' => 'nullable|array',
            'filters' => 'nullable|array',
            'sorts' => 'nullable|array',
            'chart_type' => 'nullable|string|in:' . implode(',', CustomReport::CHART_TYPES),
            'chart_options' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
            'is_scheduled' => 'nullable|boolean',
            'schedule_cron' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $report = $this->reportBuilder->updateReport($report, $validator->validated());
        return ApiResponse::success($report, '报表已更新');
    }

    /**
     * 删除报表
     * DELETE /api/admin/report-builder/reports/{id}
     */
    public function deleteReport(int $id): JsonResponse
    {
        $report = CustomReport::findOrFail($id);
        $this->reportBuilder->deleteReport($report);
        return ApiResponse::success(null, '报表已删除');
    }

    /**
     * 生成报表数据
     * POST /api/admin/report-builder/reports/{id}/generate
     */
    public function generateReport(int $id): JsonResponse
    {
        $report = CustomReport::findOrFail($id);

        try {
            $data = $this->reportBuilder->generateReportData($report);
            return ApiResponse::success($data, '报表已生成');
        } catch (\Exception $e) {
            return ApiResponse::error('GENERATION_FAILED', '报表生成失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 保存报表快照
     * POST /api/admin/report-builder/reports/{id}/snapshot
     */
    public function saveSnapshot(int $id): JsonResponse
    {
        $report = CustomReport::findOrFail($id);

        try {
            $snapshot = $this->reportBuilder->generateSnapshot($report);
            return ApiResponse::created($snapshot, '快照已保存');
        } catch (\Exception $e) {
            return ApiResponse::error('SNAPSHOT_FAILED', '快照生成失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 快照列表
     * GET /api/admin/report-builder/reports/{id}/snapshots
     */
    public function snapshots(int $id): JsonResponse
    {
        $snapshots = ReportSnapshot::where('report_id', $id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return ApiResponse::success($snapshots);
    }

    /**
     * 导出报表
     * POST /api/admin/report-builder/reports/{id}/export
     */
    public function exportReport(Request $request, int $id): JsonResponse
    {
        $report = CustomReport::findOrFail($id);

        $format = $request->input('format', 'csv');
        if (!in_array($format, ['csv', 'json'])) {
            return ApiResponse::error('INVALID_FORMAT', '不支持的导出格式', 422);
        }

        try {
            $result = $this->reportBuilder->exportReport($report, $format);
            return ApiResponse::success($result, '报表已导出');
        } catch (\Exception $e) {
            return ApiResponse::error('EXPORT_FAILED', '导出失败: ' . $e->getMessage(), 500);
        }
    }

    // ─── 仪表盘 ───

    /**
     * 获取/创建仪表盘
     * GET /api/admin/report-builder/dashboards
     */
    public function dashboards(Request $request): JsonResponse
    {
        $dashboards = $this->reportBuilder->getDashboards($request->user()->id);
        return ApiResponse::success($dashboards);
    }

    /**
     * 创建仪表盘
     * POST /api/admin/report-builder/dashboards
     */
    public function createDashboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'layout' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->tenant_id;

        $dashboard = $this->reportBuilder->createDashboard($data);
        return ApiResponse::created($dashboard, '看板已创建');
    }

    /**
     * 更新仪表盘
     * PUT /api/admin/report-builder/dashboards/{id}
     */
    public function updateDashboard(Request $request, int $id): JsonResponse
    {
        $dashboard = ReportDashboard::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string|max:1000',
            'layout' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $dashboard = $this->reportBuilder->updateDashboard($dashboard, $validator->validated());
        return ApiResponse::success($dashboard, '看板已更新');
    }

    /**
     * 删除仪表盘
     * DELETE /api/admin/report-builder/dashboards/{id}
     */
    public function deleteDashboard(int $id): JsonResponse
    {
        $dashboard = ReportDashboard::findOrFail($id);
        $this->reportBuilder->deleteDashboard($dashboard);
        return ApiResponse::success(null, '看板已删除');
    }

    // ─── 主仪表盘 ───

    /**
     * 报表生成器主仪表盘
     * GET /api/admin/report-builder/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->reportBuilder->getDashboard(
            $request->user()->id,
            $request->user()->tenant_id
        );
        return ApiResponse::success($data);
    }
}
