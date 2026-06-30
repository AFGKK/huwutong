<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLogAnnotation;
use App\Models\AuditLogTag;
use App\Services\AuditGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditGovernanceController extends Controller
{
    public function __construct(
        protected AuditGovernanceService $governance,
    ) {}

    // ─── 合规报告 ───

    /**
     * 获取合规框架列表
     * GET /api/admin/compliance/frameworks
     */
    public function frameworks(): JsonResponse
    {
        $frameworks = $this->governance->getFrameworks();
        return ApiResponse::success($frameworks);
    }

    /**
     * 初始化/种子合规框架数据
     * POST /api/admin/compliance/frameworks/seed
     */
    public function seedFrameworks(): JsonResponse
    {
        $this->governance->seedFrameworks();
        $frameworks = $this->governance->getFrameworks();
        return ApiResponse::success($frameworks, '合规框架已初始化');
    }

    /**
     * 生成合规报告
     * POST /api/admin/compliance/reports
     */
    public function generateReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'framework_id' => 'required|integer|exists:compliance_frameworks,id',
            'title' => 'nullable|string|max:200',
            'type' => 'nullable|string|in:scheduled,on_demand,continuous',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $report = $this->governance->generateReport(
            $request->input('framework_id'),
            $validator->validated()
        );

        return ApiResponse::created($report->load(['framework', 'generator']), '合规报告已生成');
    }

    /**
     * 合规报告列表
     * GET /api/admin/compliance/reports
     */
    public function reports(Request $request): JsonResponse
    {
        $query = \App\Models\ComplianceReport::with(['framework', 'generator']);

        if ($request->filled('framework_id')) {
            $query->where('framework_id', $request->input('framework_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $reports = $query->orderByDesc('created_at')->paginate($perPage);

        return ApiResponse::paginated($reports);
    }

    /**
     * 合规报告详情
     * GET /api/admin/compliance/reports/{id}
     */
    public function showReport(int $id): JsonResponse
    {
        $report = \App\Models\ComplianceReport::with(['framework', 'generator'])->findOrFail($id);
        return ApiResponse::success($report);
    }

    /**
     * 删除合规报告
     * DELETE /api/admin/compliance/reports/{id}
     */
    public function deleteReport(int $id): JsonResponse
    {
        $report = \App\Models\ComplianceReport::findOrFail($id);
        $report->delete();
        return ApiResponse::success(null, '合规报告已删除');
    }

    // ─── 审计日志标签 ───

    /**
     * 标签列表
     * GET /api/admin/audit-tags
     */
    public function tags(): JsonResponse
    {
        $tags = $this->governance->getTags();
        return ApiResponse::success($tags);
    }

    /**
     * 创建标签
     * POST /api/admin/audit-tags
     */
    public function createTag(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:audit_log_tags,name',
            'color' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tag = $this->governance->createTag($validator->validated());
        return ApiResponse::created($tag, '标签已创建');
    }

    /**
     * 更新标签
     * PUT /api/admin/audit-tags/{id}
     */
    public function updateTag(Request $request, int $id): JsonResponse
    {
        $tag = AuditLogTag::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100|unique:audit_log_tags,name,' . $id,
            'color' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tag = $this->governance->updateTag($tag, $validator->validated());
        return ApiResponse::success($tag, '标签已更新');
    }

    /**
     * 删除标签
     * DELETE /api/admin/audit-tags/{id}
     */
    public function deleteTag(int $id): JsonResponse
    {
        $tag = AuditLogTag::findOrFail($id);
        $this->governance->deleteTag($tag);
        return ApiResponse::success(null, '标签已删除');
    }

    /**
     * 批量标记日志
     * POST /api/admin/audit-logs/batch-tag
     */
    public function batchTag(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'log_ids' => 'required|array|min:1',
            'log_ids.*' => 'integer|exists:logs,id',
            'tag_ids' => 'required|array|min:1',
            'tag_ids.*' => 'integer|exists:audit_log_tags,id',
            'action' => 'nullable|string|in:add,remove',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $action = $request->input('action', 'add');
        $count = $action === 'remove'
            ? $this->governance->untagLogs($request->input('log_ids'), $request->input('tag_ids'))
            : $this->governance->tagLogs($request->input('log_ids'), $request->input('tag_ids'));

        return ApiResponse::success(['affected' => $count], '批量操作完成');
    }

    // ─── 审计日志备注 ───

    /**
     * 添加备注
     * POST /api/admin/audit-logs/{logId}/annotations
     */
    public function addAnnotation(Request $request, int $logId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $annotation = $this->governance->addAnnotation($logId, $request->input('content'));
        return ApiResponse::created($annotation->load('user'), '备注已添加');
    }

    /**
     * 获取备注
     * GET /api/admin/audit-logs/{logId}/annotations
     */
    public function annotations(int $logId): JsonResponse
    {
        $annotations = $this->governance->getAnnotations($logId);
        return ApiResponse::success($annotations);
    }

    /**
     * 删除备注
     * DELETE /api/admin/audit-logs/annotations/{id}
     */
    public function deleteAnnotation(int $id): JsonResponse
    {
        $this->governance->deleteAnnotation($id);
        return ApiResponse::success(null, '备注已删除');
    }

    // ─── 批量操作历史 ───

    /**
     * 批量操作历史
     * GET /api/admin/audit-batch-operations
     */
    public function batchOperations(): JsonResponse
    {
        $ops = $this->governance->getBatchOperations();
        return ApiResponse::success($ops);
    }

    // ─── 数据保留治理 ───

    /**
     * 数据保留仪表盘
     * GET /api/admin/retention-governance/dashboard
     */
    public function retentionDashboard(): JsonResponse
    {
        $dashboard = $this->governance->getRetentionDashboard();
        return ApiResponse::success($dashboard);
    }

    /**
     * 执行数据清理
     * POST /api/admin/retention-governance/cleanup
     */
    public function executeCleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:audit,security,error,system',
            'custom_days' => 'nullable|integer|min:1|max:3650',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $audit = $this->governance->executeRetentionCleanup(
            $request->input('type'),
            $request->input('custom_days')
        );

        return ApiResponse::success($audit->load('initiator'), '数据清理完成');
    }

    /**
     * 清理历史
     * GET /api/admin/retention-governance/cleanup-history
     */
    public function cleanupHistory(): JsonResponse
    {
        $history = \App\Models\DataRetentionAudit::with('initiator')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ApiResponse::success($history);
    }

    // ─── 治理概览仪表盘 ───

    /**
     * 审计治理概览
     * GET /api/admin/audit-governance/dashboard
     */
    public function governanceDashboard(): JsonResponse
    {
        $dashboard = $this->governance->getGovernanceDashboard();
        return ApiResponse::success($dashboard);
    }

    // ══════════════════════════════════════════
    //  多数据源保留策略管理
    // ══════════════════════════════════════════

    /**
     * 所有数据源保留策略列表
     * GET /api/admin/retention-policies
     */
    public function retentionPolicies(): JsonResponse
    {
        $policies = $this->governance->getAllRetentionPolicies();
        return ApiResponse::success($policies);
    }

    /**
     * 创建/更新保留策略
     * POST /api/admin/retention-policies
     */
    public function saveRetentionPolicy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data_source' => 'required|string|max:50',
            'retention_days' => 'required|integer|min:1|max:3650',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $policy = $this->governance->saveRetentionPolicy($validator->validated());
        return ApiResponse::success($policy, '保留策略已保存');
    }

    /**
     * 切换策略状态
     * POST /api/admin/retention-policies/{id}/toggle
     */
    public function toggleRetentionPolicy(int $id): JsonResponse
    {
        $active = $this->governance->toggleRetentionPolicy($id);
        return ApiResponse::success(['is_active' => $active], '策略状态已切换');
    }

    /**
     * 删除保留策略
     * DELETE /api/admin/retention-policies/{id}
     */
    public function deleteRetentionPolicy(int $id): JsonResponse
    {
        $deleted = $this->governance->deleteRetentionPolicy($id);
        if (!$deleted) {
            return ApiResponse::error('DELETE_FAILED', '系统预置策略不可删除', 422);
        }
        return ApiResponse::success(null, '策略已删除');
    }

    /**
     * 增强的数据保留审计仪表盘
     * GET /api/admin/retention-governance/extended-dashboard
     */
    public function extendedRetentionDashboard(): JsonResponse
    {
        $dashboard = $this->governance->getExtendedRetentionDashboard();
        return ApiResponse::success($dashboard);
    }

    /**
     * 执行多数据源清理
     * POST /api/admin/retention-governance/extended-cleanup
     */
    public function executeExtendedCleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data_source' => 'required|string|max:50',
            'custom_days' => 'nullable|integer|min:1|max:3650',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $audit = $this->governance->executeExtendedCleanup(
            $request->input('data_source'),
            $request->input('custom_days')
        );

        return ApiResponse::success($audit->load('initiator'), '数据清理完成');
    }

    // ─── 清理调度配置 ───

    /**
     * 清理调度配置列表
     * GET /api/admin/cleanup-schedules
     */
    public function cleanupSchedules(): JsonResponse
    {
        $schedules = $this->governance->getCleanupSchedules();
        return ApiResponse::success($schedules);
    }

    /**
     * 保存清理调度配置
     * POST /api/admin/cleanup-schedules
     */
    public function saveCleanupSchedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data_source' => 'required|string|max:50',
            'frequency' => 'nullable|string|in:daily,weekly,monthly,manual',
            'time_of_day' => 'nullable|string|max:5',
            'day_of_week' => 'nullable|string|max:10',
            'batch_size' => 'nullable|integer|min:100|max:10000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        $schedule = $this->governance->saveCleanupSchedule($validator->validated());
        return ApiResponse::success($schedule, '调度配置已保存');
    }

    // ─── 合规报告导出 ───

    /**
     * 获取报告导出列表
     * GET /api/admin/compliance/reports/{reportId}/exports
     */
    public function reportExports(int $reportId): JsonResponse
    {
        $exports = $this->governance->getReportExports($reportId);
        return ApiResponse::success($exports);
    }

    /**
     * 导出合规报告
     * POST /api/admin/compliance/reports/{reportId}/export
     */
    public function exportReport(Request $request, int $reportId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:json,csv',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数验证失败', $validator->errors()->toArray());
        }

        try {
            $export = $this->governance->exportReport($reportId, $request->input('format'));
            return ApiResponse::success($export, '报告已导出');
        } catch (\Exception $e) {
            return ApiResponse::error('EXPORT_FAILED', '导出失败: ' . $e->getMessage(), 500);
        }
    }
}
