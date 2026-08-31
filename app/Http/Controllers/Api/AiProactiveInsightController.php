<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiProactiveInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiProactiveInsightController extends Controller
{
    protected AiProactiveInsightService $insightService;

    public function __construct(AiProactiveInsightService $insightService)
    {
        $this->insightService = $insightService;
    }

    /**
     * 获取用户的洞察列表
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $filters = $request->only(['status', 'type']);
        $perPage = (int) $request->input('per_page', 20);

        $insights = $this->insightService->getUserInsights($userId, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $insights->items(),
            'meta' => [
                'current_page' => $insights->currentPage(),
                'last_page' => $insights->lastPage(),
                'per_page' => $insights->perPage(),
                'total' => $insights->total(),
            ],
        ]);
    }

    /**
     * 洞察统计
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $stats = $this->insightService->getStats($userId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * 标记为已读
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$this->insightService->markRead($id, $userId)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INSIGHT_NOT_FOUND', 'message' => __('app.controller_compat.ai_proactive_insight_msg_66')],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.ai_proactive_insight_msg_72'),
        ]);
    }

    /**
     * 忽略洞察
     */
    public function dismiss(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$this->insightService->dismiss($id, $userId)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INSIGHT_NOT_FOUND', 'message' => __('app.controller_compat.ai_proactive_insight_msg_86')],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.ignored'),
        ]);
    }

    /**
     * 一键标记全部为已读
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        \App\Models\AiInsight::byUser($userId)->unread()->get()->each->markAsRead();

        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.ai_proactive_insight_msg_107'),
        ]);
    }

    /**
     * 获取配置的洞察类型列表
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => config('ai-proactive.types', []),
        ]);
    }
}
