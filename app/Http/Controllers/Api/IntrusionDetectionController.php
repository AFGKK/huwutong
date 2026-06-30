<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\IdsAlert;
use App\Models\IdsRule;
use App\Services\IntrusionDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IntrusionDetectionController extends Controller
{
    public function __construct(
        protected IntrusionDetectionService $idsService
    ) {}

    // ─── 概览 ───

    /**
     * IDS/IPS 概览仪表盘
     */
    public function dashboard(Request $request)
    {
        return ApiResponse::success(
            $this->idsService->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 告警趋势
     */
    public function trends(Request $request)
    {
        $days = min((int)$request->input('days', 7), 90);
        return ApiResponse::success(
            $this->idsService->getAlertTrends($request->user()->tenant_id, $days)
        );
    }

    // ─── 规则管理 ───

    /**
     * 规则列表
     */
    public function rules(Request $request)
    {
        return ApiResponse::paginated(
            $this->idsService->getRules(
                array_merge($request->all(), ['tenant_id' => $request->user()->tenant_id]),
                (int)$request->input('per_page', 20)
            )
        );
    }

    /**
     * 规则详情
     */
    public function showRule(IdsRule $rule)
    {
        return ApiResponse::success($rule);
    }

    /**
     * 创建规则
     */
    public function storeRule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'detection_type' => 'required|string|in:' . implode(',', array_keys(IdsRule::DETECTION_TYPES)),
            'severity' => 'required|string|in:' . implode(',', array_keys(IdsRule::SEVERITIES)),
            'threshold_count' => 'nullable|integer|min:1',
            'threshold_window_minutes' => 'nullable|integer|min:1',
            'conditions' => 'nullable|array',
            'actions' => 'nullable|array',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        $rule = $this->idsService->createRule($data);

        return ApiResponse::success($rule, '规则创建成功', 201);
    }

    /**
     * 更新规则
     */
    public function updateRule(Request $request, IdsRule $rule)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'detection_type' => 'nullable|string|in:' . implode(',', array_keys(IdsRule::DETECTION_TYPES)),
            'severity' => 'nullable|string|in:' . implode(',', array_keys(IdsRule::SEVERITIES)),
            'threshold_count' => 'nullable|integer|min:1',
            'threshold_window_minutes' => 'nullable|integer|min:1',
            'conditions' => 'nullable|array',
            'actions' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        $rule = $this->idsService->updateRule($rule, $request->all());

        return ApiResponse::success($rule, '规则更新成功');
    }

    /**
     * 删除规则
     */
    public function destroyRule(IdsRule $rule)
    {
        $deleted = $this->idsService->deleteRule($rule);

        if (!$deleted) {
            return ApiResponse::error('SYSTEM_RULE', '系统规则不可删除', 403);
        }

        return ApiResponse::success(null, '规则已删除');
    }

    /**
     * 播种默认规则
     */
    public function seedRules(Request $request)
    {
        $count = $this->idsService->seedSystemRules($request->user()->tenant_id);
        return ApiResponse::success(['seeded' => $count], "已播种 {$count} 条系统规则");
    }

    /**
     * 获取检测类型选项
     */
    public function detectionTypes()
    {
        return ApiResponse::success([
            'types' => IdsRule::DETECTION_TYPES,
            'severities' => IdsRule::SEVERITIES,
        ]);
    }

    // ─── 告警管理 ───

    /**
     * 告警列表
     */
    public function alerts(Request $request)
    {
        return ApiResponse::paginated(
            $this->idsService->getAlerts(
                array_merge($request->all(), ['tenant_id' => $request->user()->tenant_id]),
                (int)$request->input('per_page', 20)
            )
        );
    }

    /**
     * 告警详情
     */
    public function showAlert(int $id)
    {
        $alert = $this->idsService->getAlert($id);

        if (!$alert) {
            return ApiResponse::error('NOT_FOUND', '告警不存在', 404);
        }

        return ApiResponse::success($alert);
    }

    /**
     * 更新告警状态
     */
    public function updateAlertStatus(Request $request, IdsAlert $alert)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:' . implode(',', array_keys(IdsAlert::STATUSES)),
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', '验证失败', 422, $validator->errors()->toArray());
        }

        $alert = $this->idsService->updateAlertStatus($alert, $request->input('status'), $request->input('notes'));

        return ApiResponse::success($alert, '告警状态已更新');
    }

    /**
     * 获取告警状态选项
     */
    public function alertStatuses()
    {
        return ApiResponse::success([
            'statuses' => IdsAlert::STATUSES,
        ]);
    }

    /**
     * 清警报告警
     */
    public function clearAlerts(Request $request)
    {
        $olderThan = $request->input('older_than', '30 days');
        $count = $this->idsService->clearAlerts($request->user()->tenant_id, $olderThan);

        return ApiResponse::success(['deleted' => $count], "已清除 {$count} 条告警");
    }
}
