<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ReportDeliveryLog;
use App\Models\ReportSchedule;
use App\Services\ReportSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportSchedulerController extends Controller
{
    public function __construct(
        protected ReportSchedulerService $schedulerService,
    ) {}

    /**
     * 调度仪表盘
     * GET /api/admin/report-scheduler/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->schedulerService->getDashboard($request->user()->id)
        );
    }

    /**
     * 调度列表
     * GET /api/admin/report-scheduler/schedules
     */
    public function schedules(Request $request): JsonResponse
    {
        $filters = $request->only(['is_active', 'report_id', 'page', 'per_page']);
        return ApiResponse::success(
            $this->schedulerService->getSchedules($request->user()->id, $filters)
        );
    }

    /**
     * 创建调度
     * POST /api/admin/report-scheduler/schedules
     */
    public function createSchedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_id' => 'required|integer|exists:custom_reports,id',
            'cron_expression' => 'required|string|max:100',
            'export_format' => 'nullable|string|in:csv,json,xlsx,pdf',
            'recipients' => 'nullable|array',
            'recipients.*.email' => 'required_with:recipients|email',
            'recipients.*.name' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:200',
            'message' => 'nullable|string|max:2000',
            'include_chart' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'max_retries' => 'nullable|integer|min:0|max:10',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.report_scheduler.param_error'), $validator->errors()->toArray());
        }

        $schedule = $this->schedulerService->createSchedule(
            $request->user()->id,
            $request->user()->tenant_id,
            $validator->validated()
        );

        return ApiResponse::created($schedule, __('app.api.report_scheduler.schedule_created'));
    }

    /**
     * 更新调度
     * PUT /api/admin/report-scheduler/schedules/{id}
     */
    public function updateSchedule(Request $request, int $id): JsonResponse
    {
        $schedule = ReportSchedule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$schedule) {
            return ApiResponse::error('NOT_FOUND', __('app.api.report_scheduler.schedule_not_found'), 404);
        }

        $validator = Validator::make($request->all(), [
            'cron_expression' => 'sometimes|required|string|max:100',
            'export_format' => 'nullable|string|in:csv,json,xlsx,pdf',
            'recipients' => 'nullable|array',
            'recipients.*.email' => 'required_with:recipients|email',
            'recipients.*.name' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:200',
            'message' => 'nullable|string|max:2000',
            'include_chart' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'max_retries' => 'nullable|integer|min:0|max:10',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.report_scheduler.param_error'), $validator->errors()->toArray());
        }

        $schedule = $this->schedulerService->updateSchedule($schedule, $validator->validated());
        return ApiResponse::success($schedule, __('app.api.report_scheduler.schedule_updated'));
    }

    /**
     * 删除调度
     * DELETE /api/admin/report-scheduler/schedules/{id}
     */
    public function deleteSchedule(Request $request, int $id): JsonResponse
    {
        $schedule = ReportSchedule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$schedule) {
            return ApiResponse::error('NOT_FOUND', __('app.api.report_scheduler.schedule_not_found'), 404);
        }

        $this->schedulerService->deleteSchedule($schedule);
        return ApiResponse::success(null, __('app.api.report_scheduler.schedule_deleted'));
    }

    /**
     * 切换调度启用/禁用
     * POST /api/admin/report-scheduler/schedules/{id}/toggle
     */
    public function toggleSchedule(Request $request, int $id): JsonResponse
    {
        $schedule = ReportSchedule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$schedule) {
            return ApiResponse::error('NOT_FOUND', __('app.api.report_scheduler.schedule_not_found'), 404);
        }

        $schedule = $this->schedulerService->toggleSchedule($schedule);
        return ApiResponse::success($schedule, $schedule->is_active ? __('app.api.report_scheduler.schedule_enabled') : __('app.api.report_scheduler.schedule_paused'));
    }

    /**
     * 手动触发调度
     * POST /api/admin/report-scheduler/schedules/{id}/trigger
     */
    public function triggerSchedule(Request $request, int $id): JsonResponse
    {
        $schedule = ReportSchedule::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$schedule) {
            return ApiResponse::error('NOT_FOUND', __('app.api.report_scheduler.schedule_not_found'), 404);
        }

        $log = $this->schedulerService->triggerNow($schedule);
        return ApiResponse::success($log, __('app.api.report_scheduler.schedule_triggered'));
    }

    /**
     * 投递日志列表
     * GET /api/admin/report-scheduler/delivery-logs
     */
    public function deliveryLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['schedule_id', 'status', 'date_from', 'date_to', 'page', 'per_page']);
        return ApiResponse::success(
            $this->schedulerService->getDeliveryLogs($request->user()->id, $filters)
        );
    }

    /**
     * 获取可调度的报表列表
     * GET /api/admin/report-scheduler/schedulable-reports
     */
    public function schedulableReports(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->schedulerService->getSchedulableReports($request->user()->id)
        );
    }
}
