<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AiInteractionLog;
use App\Models\SelfLearningPattern;
use App\Services\SelfLearningEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfLearningController extends Controller
{
    protected SelfLearningEngine $engine;

    public function __construct(SelfLearningEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * 执行一次学习周期
     */
    public function learn(Request $request): JsonResponse
    {
        $hours = (int) $request->input('hours', 24);
        $autoApply = $request->boolean('auto_apply', true);

        $result = $this->engine->learn([
            'lookback_hours' => $hours,
            'auto_apply' => $autoApply,
        ]);

        return ApiResponse::success($result, __("app.self_learning.msg_0c1d172d"));
    }

    /**
     * 获取学习状态
     */
    public function status(): JsonResponse
    {
        return ApiResponse::success($this->engine->getStatus());
    }

    /**
     * 获取交互日志
     */
    public function logs(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $sourceType = $request->input('source_type');
        $status = $request->input('status');

        $query = AiInteractionLog::orderBy('created_at', 'desc');
        if ($sourceType) $query->where('source_type', $sourceType);
        if ($status) $query->where('status', $status);

        return ApiResponse::success($query->paginate($perPage));
    }

    /**
     * 获取学习模式列表
     */
    public function patterns(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $type = $request->input('type');
        $status = $request->input('status');

        $query = SelfLearningPattern::orderBy('confidence', 'desc');
        if ($type) $query->where('pattern_type', $type);
        if ($status) $query->where('status', $status);

        return ApiResponse::success($query->paginate($perPage));
    }
}
