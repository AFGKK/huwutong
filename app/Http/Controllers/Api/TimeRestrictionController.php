<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\TimeRestrictionConfig;
use App\Services\TimeRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * License 使用时段限制管理控制器 (M3-77)
 */
class TimeRestrictionController extends Controller
{
    public function __construct(
        protected TimeRestrictionService $timeRestriction,
    ) {}

    /**
     * 获取 License 的时段限制配置
     */
    public function show(License $license): JsonResponse
    {
        $config = TimeRestrictionConfig::where('restrictable_type', License::class)
            ->where('restrictable_id', $license->id)
            ->first();

        $summary = $config
            ? $this->timeRestriction->getConfigSummary($config)
            : ['enabled' => false, 'summary' => '未配置时段限制'];

        $data = $config ? array_merge($config->toArray(), ['summary' => $summary]) : ['summary' => $summary];

        return ApiResponse::success($data);
    }

    /**
     * 创建或更新 License 的时段限制配置
     */
    public function save(Request $request, License $license): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'boolean',
            'timezone' => 'string|max:50',
            'weekly_schedule' => 'nullable|array',
            'weekly_schedule.*.day' => 'required|integer|between:0,6',
            'weekly_schedule.*.start' => 'required|string|date_format:H:i',
            'weekly_schedule.*.end' => 'required|string|date_format:H:i',
            'special_schedule' => 'nullable|array',
            'special_schedule.*.date' => 'required|date_format:Y-m-d',
            'special_schedule.*.start' => 'required|string|date_format:H:i',
            'special_schedule.*.end' => 'required|string|date_format:H:i',
            'holidays' => 'nullable|array',
            'holidays.*' => 'date_format:Y-m-d',
            'out_of_hours_action' => 'in:deny,grace,warn',
            'grace_minutes' => 'integer|min:0|max:1440',
            'allowed_ip_ranges' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $data = $validator->validated();

        $config = TimeRestrictionConfig::updateOrCreate(
            [
                'restrictable_type' => License::class,
                'restrictable_id' => $license->id,
            ],
            $data
        );

        return ApiResponse::success($config->fresh(), '时段限制配置已保存');
    }

    /**
     * 删除 License 的时段限制配置
     */
    public function destroy(License $license): JsonResponse
    {
        $deleted = TimeRestrictionConfig::where('restrictable_type', License::class)
            ->where('restrictable_id', $license->id)
            ->delete();

        return $deleted
            ? ApiResponse::success(null, '时段限制配置已删除')
            : ApiResponse::success(null, '未找到配置');
    }

    /**
     * 获取时段限制检查日志
     */
    public function logs(License $license, Request $request): JsonResponse
    {
        $query = \App\Models\TimeRestrictionLog::where('license_id', $license->id)
            ->orderByDesc('created_at');

        return ApiResponse::success($query->paginate($request->input('per_page', 20)));
    }

    /**
     * 获取当前用户的 License 时段限制状态
     * SDK 侧调用 — 检查当前 License 是否在可用时段内
     */
    public function checkAccess(License $license, Request $request): JsonResponse
    {
        $result = $this->timeRestriction->check($license, $request->ip());

        return ApiResponse::success($result);
    }

    /**
     * 全局时段限制配置列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = TimeRestrictionConfig::with('restrictable')
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('timezone', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ApiResponse::success($query->paginate($request->input('per_page', 20)));
    }

    /**
     * 全局统计概览
     */
    public function stats(): JsonResponse
    {
        $total = TimeRestrictionConfig::count();
        $active = TimeRestrictionConfig::where('is_active', true)->count();
        $todayChecks = \App\Models\TimeRestrictionLog::whereDate('created_at', today())->count();
        $todayDenials = \App\Models\TimeRestrictionLog::whereDate('created_at', today())
            ->where('result', 'denied')->count();

        return ApiResponse::success([
            'total_configs' => $total,
            'active_configs' => $active,
            'today_checks' => $todayChecks,
            'today_denials' => $todayDenials,
        ]);
    }

    /**
     * 全局检查日志（跨所有 License）
     */
    public function globalLogs(Request $request): JsonResponse
    {
        $query = \App\Models\TimeRestrictionLog::with('license')
            ->orderByDesc('created_at');

        if ($licenseId = $request->input('license_id')) {
            $query->where('license_id', $licenseId);
        }

        if ($result = $request->input('result')) {
            $query->where('result', $result);
        }

        return ApiResponse::success($query->paginate($request->input('per_page', 20)));
    }

    /**
     * 获取所有支持的名值对（前端用）
     */
    public function metadata(): JsonResponse
    {
        return ApiResponse::success([
            'day_options' => [
                ['value' => 0, 'label' => '周日'],
                ['value' => 1, 'label' => '周一'],
                ['value' => 2, 'label' => '周二'],
                ['value' => 3, 'label' => '周三'],
                ['value' => 4, 'label' => '周四'],
                ['value' => 5, 'label' => '周五'],
                ['value' => 6, 'label' => '周六'],
            ],
            'out_of_hours_actions' => [
                ['value' => 'deny', 'label' => '拒绝访问'],
                ['value' => 'grace', 'label' => '宽限使用'],
                ['value' => 'warn', 'label' => '仅警告'],
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
