<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UtmTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UtmTrackerController extends Controller
{
    public function __construct(protected UtmTrackerService $utmService) {}

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
            return response()->json(['message' => '参数验证失败', 'errors' => $validator->errors()], 422);
        }

        $data = $this->utmService->getDashboard(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 归因报告
     */
    public function attributionReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'model' => 'nullable|string|in:first_touch,last_touch,linear,time_decay',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => '参数验证失败', 'errors' => $validator->errors()], 422);
        }

        $data = $this->utmService->getAttributionReport(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('model', config('utm-tracker.default_model', 'first_touch'))
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 来源详细统计
     */
    public function sourceDetail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'channel_group' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => '参数验证失败', 'errors' => $validator->errors()], 422);
        }

        $data = $this->utmService->getSourceDetail(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('channel_group')
        );

        return response()->json(['data' => $data]);
    }

    /**
     * 记录 UTM 访问 (公开, 前端 JS 调用)
     */
    public function recordVisit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'nullable|string|max:100',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:200',
            'utm_content' => 'nullable|string|max:200',
            'landing_page' => 'nullable|string|max:500',
            'referrer_url' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => '参数验证失败', 'errors' => $validator->errors()], 422);
        }

        $record = $this->utmService->record(
            $request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'landing_page', 'referrer_url']),
            $request->input('session_id')
        );

        return response()->json([
            'data' => $record,
            'message' => '记录成功',
        ]);
    }

    /**
     * 注册时关联 UTM
     */
    public function associateUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'session_id' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => '参数验证失败', 'errors' => $validator->errors()], 422);
        }

        $record = $this->utmService->associateUser(
            (int) $request->input('user_id'),
            $request->input('session_id')
        );

        if (!$record) {
            return response()->json(['message' => '未找到该会话的UTM记录'], 404);
        }

        return response()->json(['data' => $record, 'message' => '关联成功']);
    }

    /**
     * 用户 UTM 历史
     */
    public function userHistory(int $userId): JsonResponse
    {
        $data = $this->utmService->getUserUtmHistory($userId);
        return response()->json(['data' => $data]);
    }

    /**
     * 获取配置选项
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'channel_groups' => $this->utmService->getChannelGroups(),
                'attribution_models' => config('utm-tracker.attribution_models', []),
                'utm_params' => config('utm-tracker.utm_params', []),
            ],
        ]);
    }

    /**
     * 记录列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = \App\Models\UtmTrackingRecord::query();

        if ($request->filled('utm_source')) {
            $query->where('utm_source', $request->input('utm_source'));
        }
        if ($request->filled('utm_medium')) {
            $query->where('utm_medium', $request->input('utm_medium'));
        }
        if ($request->filled('channel_group')) {
            $query->where('channel_group', $request->input('channel_group'));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $records = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json($records);
    }
}
