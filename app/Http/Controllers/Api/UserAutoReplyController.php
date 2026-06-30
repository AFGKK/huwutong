<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserAutoReply;
use App\Services\UserAutoReplyService;
use Illuminate\Http\Request;

class UserAutoReplyController extends Controller
{
    /**
     * 获取当前用户的自动回复列表
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $rules = UserAutoReply::where('user_id', $request->user()->id)
            ->orderBy('type')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success($rules);
    }

    /**
     * 创建自动回复规则
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:away,vacation,keyword,busy',
            'keyword' => 'nullable|string|max:100',
            'match_mode' => 'nullable|in:exact,contains,regex',
            'reply_content' => 'required|string|max:500',
            'time_start' => 'nullable|date_format:H:i',
            'time_end' => 'nullable|date_format:H:i',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['user_id'] = $request->user()->id;

        $rule = UserAutoReply::create($validated);

        return ApiResponse::success($rule, '自动回复已创建', 201);
    }

    /**
     * 更新自动回复规则
     */
    public function update(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $rule = UserAutoReply::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|in:away,vacation,keyword,busy',
            'keyword' => 'nullable|string|max:100',
            'match_mode' => 'nullable|in:exact,contains,regex',
            'reply_content' => 'sometimes|string|max:500',
            'time_start' => 'nullable|date_format:H:i',
            'time_end' => 'nullable|date_format:H:i',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'nullable|boolean',
        ]);

        $rule->update($validated);

        return ApiResponse::success($rule->fresh(), '已更新');
    }

    /**
     * 删除自动回复规则
     */
    public function destroy(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $rule = UserAutoReply::where('user_id', $request->user()->id)->findOrFail($id);
        $rule->delete();

        return ApiResponse::success(null, '已删除');
    }

    /**
     * 获取当前用户状态
     */
    public function status(Request $request, UserAutoReplyService $svc): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success(
            $svc->getUserAutoStatus($request->user()->id)
        );
    }
}
