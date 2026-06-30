<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Services\CustomerAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * M2-130 客户侧审计日志 API
 *
 * 企业客户查看租户内操作记录：
 * - 谁在何时做了什么（激活设备/修改License/邀请成员/改支付方式）
 * - IP + User-Agent 记录
 * - 可筛选导出
 * - 统计概览
 */
class CustomerAuditLogController extends Controller
{
    public function __construct(
        protected CustomerAuditLogService $auditLogService,
    ) {}

    /**
     * 审计日志列表
     *
     * GET /api/customer/audit-logs
     *
     * @queryParam filter[action_prefix] string 操作前缀筛选（如 license. / device. / team.）
     * @queryParam filter[action] string 具体操作筛选
     * @queryParam filter[user_id] int 按用户筛选
     * @queryParam date_from string 开始日期
     * @queryParam date_to string 结束日期
     * @queryParam search string 关键词搜索
     * @queryParam sort string 排序（默认 -created_at）
     * @queryParam per_page int 每页条数（默认 20，最大 100）
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $filters = $request->only([
            'action_prefix', 'action', 'user_id',
            'date_from', 'date_to', 'search', 'sort',
        ]);

        $result = $this->auditLogService->getAuditLogs(
            tenant: $tenant,
            filters: $filters,
            perPage: (int) $request->input('per_page', 20),
        );

        return ApiResponse::paginated($result);
    }

    /**
     * 审计日志详情
     *
     * GET /api/customer/audit-logs/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $log = $this->auditLogService->getAuditLogDetail($tenant, $id);

        if (! $log) {
            return ApiResponse::notFound('审计日志不存在');
        }

        return ApiResponse::success($log->load(['user:id,name,email']));
    }

    /**
     * 审计日志统计概览
     *
     * GET /api/customer/audit-logs/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $stats = $this->auditLogService->getStats($tenant);

        return ApiResponse::success($stats);
    }

    /**
     * 获取操作分类（用于前端筛选下拉）
     *
     * GET /api/customer/audit-logs/action-categories
     */
    public function actionCategories(): JsonResponse
    {
        return ApiResponse::success(
            $this->auditLogService->getActionCategories()
        );
    }

    /**
     * 导出审计日志（CSV）
     *
     * GET /api/customer/audit-logs/export
     *
     * @queryParam filter[action_prefix] string 操作前缀
     * @queryParam filter[action] string 具体操作
     * @queryParam filter[user_id] int 按用户
     * @queryParam date_from string 开始日期
     * @queryParam date_to string 结束日期
     * @queryParam max_rows int 最大导出行数（默认 10000）
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $filters = $request->only([
            'action_prefix', 'action', 'user_id',
            'date_from', 'date_to',
        ]);

        $maxRows = min((int) $request->input('max_rows', 10000), 50000);

        $export = $this->auditLogService->exportCsv(
            tenant: $tenant,
            filters: $filters,
            maxRows: $maxRows,
        );

        $response = new StreamedResponse(function () use ($export) {
            // UTF-8 BOM
            echo "\xEF\xBB\xBF";

            $output = fopen('php://output', 'w');

            // 表头
            fputcsv($output, $export['headers']);

            // 数据行
            foreach ($export['rows'] as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="audit-logs-' . now()->format('Ymd-His') . '.csv"'
        );

        return $response;
    }

    /**
     * 解析当前活跃租户
     */
    protected function resolveTenant(Request $request, \App\Models\User $user): Tenant
    {
        $tenantId = $request->header('X-Tenant-Id')
            ?? $user->remember_tenant_id
            ?? $user->tenant_id;

        if (! $tenantId) {
            abort(400, '未选择租户');
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            abort(404, '租户不存在');
        }

        // 验证用户是该租户的活跃成员（或超管）
        $isMember = TenantMember::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $isMember && ! $user->hasRole('super-admin')) {
            abort(403, '您无权访问该租户');
        }

        return $tenant;
    }
}
