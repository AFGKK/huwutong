<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        protected NotificationPreferenceService $preferenceService
    ) {}

    // ─── 客户门户端点 ───

    /**
     * 获取我的通知偏好
     */
    public function myPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->preferenceService->getPreferences($user);
        $channels = $this->preferenceService->getAvailableChannels($user);

        return response()->json([
            'preferences' => $result['preferences'],
            'general' => $result['general'],
            'channels' => $channels,
        ]);
    }

    /**
     * 更新我的通知偏好
     */
    public function updateMyPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->input('preferences', []);

        if (empty($data)) {
            $this->preferenceService->initializeDefaults($user);
            return response()->json(['message' => __('app.controller_compat.notification_preference_msg_45')]);
        }

        [$preferences, $general] = $this->preferenceService->updatePreferences($user, $data);

        return response()->json([
            'message' => __('app.controller_compat.notification_preference_msg_51'),
            'preferences' => $preferences,
            'general' => $general,
        ]);
    }

    /**
     * 更新通用设置（免打扰、摘要频率、时区）
     */
    public function updateGeneralSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quiet_hours_start' => 'nullable|string|max:5',
            'quiet_hours_end' => 'nullable|string|max:5',
            'timezone' => 'nullable|string|max:64',
            'digest_frequency' => 'nullable|in:none,daily,weekly,monthly',
        ]);

        $pref = $this->preferenceService->updateGeneralSettings($request->user(), $validated);

        return response()->json([
            'message' => __('app.controller_compat.notification_preference_msg_72'),
            'general' => [
                'quiet_hours_start' => $pref->quiet_hours_start,
                'quiet_hours_end' => $pref->quiet_hours_end,
                'timezone' => $pref->timezone,
                'digest_frequency' => $pref->digest_frequency,
                'in_quiet_hours' => $pref->isInQuietHours(),
                'digest_due' => $pref->isDigestDue(),
            ],
        ]);
    }

    /**
     * 初始化默认偏好
     */
    public function initializeMyPreferences(Request $request): JsonResponse
    {
        $this->preferenceService->initializeDefaults($request->user());
        return response()->json(['message' => __('app.controller_compat.notification_preference_msg_90')]);
    }

    /**
     * 检查特定通知是否应发送
     */
    public function checkNotification(Request $request): JsonResponse
    {
        $user = $request->user();
        $channel = $request->input('channel', 'mail');
        $category = $request->input('category', 'system');

        $allowed = $this->preferenceService->shouldNotify($user, $channel, $category);

        return response()->json([
            'channel' => $channel,
            'category' => $category,
            'allowed' => $allowed,
        ]);
    }

    /**
     * 解析事件分类 → 应发送的渠道列表
     */
    public function resolveChannels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string',
        ]);

        $channels = $this->preferenceService->resolveChannels(
            $request->user(),
            $validated['category']
        );

        return response()->json([
            'category' => $validated['category'],
            'channels' => $channels,
        ]);
    }

    // ─── 管理员端点 ───

    /**
     * 管理员: 查看指定用户的通知偏好
     */
    public function adminShow(Request $request, int $userId): JsonResponse
    {
        $user = \App\Models\User::findOrFail($userId);
        $result = $this->preferenceService->getPreferences($user);
        $channels = $this->preferenceService->getAvailableChannels($user);

        return response()->json([
            'user_id' => $userId,
            'preferences' => $result['preferences'],
            'general' => $result['general'],
            'channels' => $channels,
        ]);
    }

    /**
     * 管理员: 统计数据
     */
    public function adminStats(): JsonResponse
    {
        return response()->json($this->preferenceService->getStats());
    }

    /**
     * 管理员: 全部偏好列表
     * 由于 channel/category/enabled 存储在 JSON 中，筛选改由 Service 处理
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['channel', 'search']);
        $perPage = $request->input('per_page', 15);

        $result = $this->preferenceService->adminList($filters, $perPage);

        return response()->json($result);
    }

    /**
     * 管理员: 批量更新用户偏好
     */
    public function adminBatchUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
            'channel' => 'required|string|in:mail,sms,database',
            'category' => 'required|string',
            'enabled' => 'required|boolean',
        ]);

        $count = $this->preferenceService->batchUpdate(
            $validated['user_ids'],
            $validated['channel'],
            $validated['category'],
            $validated['enabled']
        );

        return response()->json([
            'message' => "已更新 {$count} 个用户的偏好",
            'updated_count' => $count,
        ]);
    }

    /**
     * 管理员: 更新指定用户的通用设置
     */
    public function adminUpdateUserGeneral(Request $request, int $userId): JsonResponse
    {
        $user = \App\Models\User::findOrFail($userId);

        $validated = $request->validate([
            'quiet_hours_start' => 'nullable|string|max:5',
            'quiet_hours_end' => 'nullable|string|max:5',
            'timezone' => 'nullable|string|max:64',
            'digest_frequency' => 'nullable|in:none,daily,weekly,monthly',
        ]);

        $pref = $this->preferenceService->updateGeneralSettings($user, $validated);

        return response()->json([
            'message' => __('app.controller_compat.notification_preference_msg_215'),
            'general' => [
                'quiet_hours_start' => $pref->quiet_hours_start,
                'quiet_hours_end' => $pref->quiet_hours_end,
                'timezone' => $pref->timezone,
                'digest_frequency' => $pref->digest_frequency,
            ],
        ]);
    }

    /**
     * 管理员: 初始化/重置指定用户的偏好
     */
    public function adminInitializeForUser(int $userId): JsonResponse
    {
        $user = \App\Models\User::findOrFail($userId);
        $this->preferenceService->initializeDefaults($user);

        return response()->json(['message' => __('app.controller_compat.notification_preference_msg_233')]);
    }
}
