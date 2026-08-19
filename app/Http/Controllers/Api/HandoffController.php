<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI → 人工客服转接管理（已下线）
 *
 * 人工客服队列已关闭。所有端点统一返回 410，避免遗留前端调用得到 500。
 * 数据表、模型与 HandoffService 本轮保留。
 */
class HandoffController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return $this->gone();
    }

    public function status(Request $request, $handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function sendMessage(Request $request, $handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function getMessages($handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function myHistory(Request $request): JsonResponse
    {
        return $this->gone();
    }

    public function queue(Request $request): JsonResponse
    {
        return $this->gone();
    }

    public function myConversations(Request $request): JsonResponse
    {
        return $this->gone();
    }

    public function accept(Request $request, $handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function agentSend(Request $request, $handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function close(Request $request, $handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function rate(Request $request, $handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function transfer(Request $request, $handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function updateStatus(Request $request): JsonResponse
    {
        return $this->gone();
    }

    public function onlineAgents(Request $request): JsonResponse
    {
        return $this->gone();
    }

    public function stats(Request $request): JsonResponse
    {
        return $this->gone();
    }

    public function queueStats(Request $request): JsonResponse
    {
        return $this->gone();
    }

    public function show($handoff = null): JsonResponse
    {
        return $this->gone();
    }

    public function visitorInfo($handoff = null): JsonResponse
    {
        return $this->gone();
    }

    protected function gone(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('app.api.handoff.disabled'),
        ], 410);
    }
}
