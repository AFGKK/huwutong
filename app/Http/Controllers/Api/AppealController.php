<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAppeal;
use App\Services\AccountAppealService;
use Illuminate\Http\Request;

class AppealController extends Controller
{
    public function __construct(protected AccountAppealService $appealService) {}

    /**
     * 提交申诉（允许未登录用户）
     */
    public function submit(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required_without:phone|email|exists:users,email',
            'phone' => 'required_without:email|string|exists:users,phone',
            'reason' => 'required|string|in:misunderstanding,behavior_changed,urgent_need,other',
            'explanation' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'url|max:1000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        // 通过邮箱或电话查找用户
        $user = null;
        if (!empty($validated['email'])) {
            $user = User::where('email', $validated['email'])->first();
        } elseif (!empty($validated['phone'])) {
            $user = User::where('phone', $validated['phone'])->first();
        }

        if (!$user) {
            return ApiResponse::error('USER_NOT_FOUND', __('app.api.appeal.user_missing'), 404);
        }

        try {
            $appeal = $this->appealService->submitAppeal($user->id, [
                'reason' => $validated['reason'],
                'explanation' => $validated['explanation'] ?? null,
                'attachments' => $validated['attachments'] ?? null,
                'contact_email' => $validated['contact_email'] ?? $user->email,
                'contact_phone' => $validated['contact_phone'] ?? $user->phone,
            ]);

            return ApiResponse::success([
                'appeal_id' => $appeal->id,
                'status' => $appeal->status,
                'appealed_at' => $appeal->appealed_at,
                'expected_response_days' => 3,
            ], __('app.api.appeal.submitted'), 201);
        } catch (\RuntimeException $e) {
            return ApiResponse::error('APPEAL_FAILED', $e->getMessage(), 422);
        }
    }

    /**
     * 公开查询申诉进度（邮箱）
     */
    public function lookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();
        $appeal = UserAppeal::where('user_id', $user->id)->latest()->first();

        if (!$appeal) {
            return ApiResponse::success([
                'has_appeal' => false,
            ], __('app.api.appeal.not_found'));
        }

        return ApiResponse::success([
            'has_appeal' => true,
            'appeal' => [
                'id' => $appeal->id,
                'status' => $appeal->status,
                'reason' => $appeal->reason,
                'explanation' => $appeal->explanation,
                'review_comment' => $appeal->review_comment,
                'appealed_at' => $appeal->appealed_at,
                'reviewed_at' => $appeal->reviewed_at,
            ],
        ]);
    }

    /**
     * 查询申诉状态（需要登录）
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $appeal = UserAppeal::where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$appeal) {
            return ApiResponse::success([
                'has_appeal' => false,
                'can_appeal' => in_array($user->status, ['inactive', 'locked']),
            ], __('app.api.appeal.empty'));
        }

        return ApiResponse::success([
            'has_appeal' => true,
            'appeal' => [
                'id' => $appeal->id,
                'status' => $appeal->status,
                'reason' => $appeal->reason,
                'explanation' => $appeal->explanation,
                'review_comment' => $appeal->review_comment,
                'appealed_at' => $appeal->appealed_at,
                'reviewed_at' => $appeal->reviewed_at,
            ],
            'can_appeal' => false,
        ]);
    }
}
