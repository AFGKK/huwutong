<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * D-28: FCM 设备 Token 注册/更新
 * 
 * Flutter App 在获取到 FCM Token 后调用此接口。
 * Token 过期后 FCM 会自动刷新，Flutter 端应重新注册。
 */
class DeviceTokenController extends Controller
{
    /**
     * 注册或更新当前用户的 FCM Token
     */
    public function registerToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
            'platform' => 'sometimes|string|in:ios,android',
            'device_name' => 'sometimes|string|max:255',
        ]);

        $user = Auth::user();
        $user->fcm_token = $validated['token'];
        $user->fcm_platform = $validated['platform'] ?? null;
        $user->fcm_device_name = $validated['device_name'] ?? null;
        $user->fcm_token_updated_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'FCM token registered',
        ]);
    }

    /**
     * 删除当前用户的 FCM Token（登出时调用）
     */
    public function removeToken(): JsonResponse
    {
        $user = Auth::user();
        $user->fcm_token = null;
        $user->fcm_platform = null;
        $user->fcm_device_name = null;
        $user->fcm_token_updated_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'FCM token removed',
        ]);
    }
}
