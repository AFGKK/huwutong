<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Services\AuthService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 设备信任管理控制器
 *
 * 提供：
 * - 登录时记住此设备
 * - 查看已信任设备列表
 * - 移除已信任设备
 * - 新设备登录通知
 */
class DeviceTrustController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * 登录后记住此设备
     */
    public function trust(Request $request): JsonResponse
    {
        $request->validate([
            'device_fingerprint' => 'required|string|max:64',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        $device = $this->authService->trustDevice(
            $user,
            $request->input('device_fingerprint'),
            $request->input('device_name'),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success([
            'device' => $device,
        ], __('app.device_trust.device_added_trust'));
    }

    /**
     * 获取用户已信任的设备列表
     */
    public function index(Request $request): JsonResponse
    {
        $devices = $this->authService->getTrustedDevices($request->user());

        return ApiResponse::success($devices);
    }

    /**
     * 移除已信任设备
     */
    public function destroy(Request $request, TrustedDevice $trustedDevice): JsonResponse
    {
        if ($trustedDevice->user_id !== $request->user()->id) {
            return ApiResponse::forbidden(__("app.device_trust.msg_021bfc05"));
        }

        $trustedDevice->delete();

        return ApiResponse::success(null, __("app.device_trust.msg_3327867a"));
    }

    /**
     * 清除所有已信任设备
     */
    public function clearAll(Request $request): JsonResponse
    {
        TrustedDevice::where('user_id', $request->user()->id)->delete();

        return ApiResponse::success(null, __("app.device_trust.msg_0d6b5689"));
    }

    /**
     * 登录时检查设备状态并记录新设备通知
     */
    public function checkDevice(Request $request): JsonResponse
    {
        $request->validate([
            'device_fingerprint' => 'required|string|max:64',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $fingerprint = $request->input('device_fingerprint');

        $isTrusted = $this->authService->isDeviceTrusted($user, $fingerprint);

        if (!$isTrusted) {
            // 记录新设备登录通知
            $this->notificationService->sendNewDeviceNotification(
                $user,
                $request->input('device_name', '未知设备'),
                $request->ip(),
                $request->userAgent(),
            );
        } else {
            // 更新最后看到时间
            TrustedDevice::where('user_id', $user->id)
                ->where('device_fingerprint', $fingerprint)
                ->update(['last_seen_at' => now()]);
        }

        return ApiResponse::success([
            'is_trusted' => $isTrusted,
            'device_fingerprint' => $fingerprint,
        ]);
    }
}
