<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\QuotaAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuotaAlertController extends Controller
{
    public function __construct(protected QuotaAlertService $alertService) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) {
            return ApiResponse::validationError(__("app.quota_alert.msg_f0a154e5"), $validator->errors()->toArray());
        }
        return ApiResponse::success(
            $this->alertService->getDashboard($request->input('start_date'), $request->input('end_date'))
        );
    }

    /**
     * 预警列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->alertService->getList($request->all()));
    }

    /**
     * 预警详情
     */
    public function show(int $id): JsonResponse
    {
        $alert = \App\Models\QuotaAlert::with('alertable', 'logs')->findOrFail($id);
        return ApiResponse::success($alert);
    }

    /**
     * 更新配额上限
     */
    public function updateLimit(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quota_limit' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.quota_alert.param_validation_failed'), $validator->errors()->toArray());
        }

        $alert = $this->alertService->updateLimit($id, (int) $request->input('quota_limit'));
        return ApiResponse::success($alert, __("app.quota_alert.msg_e1603e17"));
    }

    /**
     * 切换通知
     */
    public function toggleNotifications(int $id): JsonResponse
    {
        $alert = $this->alertService->toggleNotifications($id);
        return ApiResponse::success($alert, $alert->notifications_enabled ? __('app.quota_alert.notifications_opened') : __("app.quota_alert.msg_b0a2bb16"));
    }

    /**
     * 预警日志
     */
    public function logs(Request $request): JsonResponse
    {
        return ApiResponse::success($this->alertService->getLogs($request->all()));
    }

    /**
     * 批量检查
     */
    public function checkAll(): JsonResponse
    {
        $results = $this->alertService->checkAll();
        return ApiResponse::success(['checked' => count($results), 'results' => $results], __('app.quota_alert.check_completed'));
    }

    /**
     * 获取配置
     */
    public function config(): JsonResponse
    {
        return ApiResponse::success($this->alertService->getConfig());
    }
}
