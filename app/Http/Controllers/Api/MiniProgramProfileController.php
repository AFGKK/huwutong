<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\WechatMiniProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 微信小程序：个人资料 / 绑手机 / 我的激活
 */
class MiniProgramProfileController extends Controller
{
    public function __construct(
        protected WechatMiniProgramService $wechat,
    ) {}

    /**
     * GET /api/miniprogram/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'phone_masked' => $this->maskPhone($user->phone),
            'phone_verified' => ! empty($user->phone_verified_at),
            'avatar' => $user->avatar_url ?? null,
            'device_bind_id' => $user->wechat_openid
                ? 'wx_mp_' . substr(hash('sha256', $user->wechat_openid), 0, 24)
                : null,
        ]);
    }

    /**
     * POST /api/miniprogram/bind-phone
     * body: { code: string }  — button getPhoneNumber 返回的动态令牌
     */
    public function bindPhone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();
        $result = $this->wechat->getPhoneNumber($data['code']);

        if (! ($result['success'] ?? false)) {
            return ApiResponse::error(
                'PHONE_BIND_FAILED',
                $result['message'] ?? __('app.mini_program_profile.phone_get_failed'),
                400
            );
        }

        $phone = $result['pure_phone'] ?? $result['phone'] ?? '';
        if ($phone === '') {
            return ApiResponse::error('PHONE_EMPTY', __("app.mini_program_profile.msg_52d02720"), 400);
        }

        // 若手机号已被其他账号占用
        $exists = User::where('phone', $phone)->where('id', '!=', $user->id)->exists();
        if ($exists) {
            return ApiResponse::error('PHONE_TAKEN', __("app.mini_program_profile.msg_6c9cfd2b"), 422);
        }

        $user->phone = $phone;
        $user->phone_verified_at = now();
        $user->save();

        Log::info('小程序绑定手机号成功', ['user_id' => $user->id]);

        return ApiResponse::success([
            'phone' => $phone,
            'phone_masked' => $this->maskPhone($phone),
            'phone_verified' => true,
        ], __('app.mini_program_profile.phone_bound'));
    }

    /**
     * GET /api/miniprogram/my-activations
     * 按 openid 稳定指纹列出本账号激活过的 License / 设备
     */
    public function myActivations(Request $request): JsonResponse
    {
        $user = $request->user();
        if (empty($user->wechat_openid)) {
            return ApiResponse::error('WECHAT_OPENID_REQUIRED', __("app.mini_program_profile.msg_41c85ff4"), 401);
        }

        $fingerprint = 'wx_mp_' . substr(hash('sha256', $user->wechat_openid), 0, 24);

        $devices = Device::query()
            ->where('fingerprint', $fingerprint)
            ->with(['license:id,license_key,status,expires_at,product_id', 'license.product:id,name'])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $items = $devices->map(function (Device $device) {
            $license = $device->license;
            $meta = is_array($device->metadata) ? $device->metadata : [];

            return [
                'device_id' => $device->id,
                'device_name' => $meta['device_name'] ?? ($meta['client']['device_name'] ?? null),
                'platform' => $device->platform,
                'last_seen_at' => $device->last_seen_at?->toDateTimeString()
                    ?? $device->updated_at?->toDateTimeString(),
                'license_key' => $license?->license_key,
                'license_status' => $license?->status,
                'product_name' => $license?->product?->name,
                'expires_at' => $license?->expires_at?->toDateString(),
                'is_expired' => $license?->expires_at ? $license->expires_at->isPast() : false,
            ];
        })->values();

        return ApiResponse::success([
            'items' => $items,
            'total' => $items->count(),
        ]);
    }

    protected function maskPhone(?string $phone): ?string
    {
        if (! $phone || strlen($phone) < 7) {
            return $phone;
        }

        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }
}
