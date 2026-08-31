<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\MiniprogramExpirySubscription;
use App\Models\SiteSetting;
use App\Services\WechatMiniProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A4: 微信小程序过期提醒订阅
 */
class MiniProgramSubscribeController extends Controller
{
    public function __construct(
        protected WechatMiniProgramService $wechat,
    ) {}

    /**
     * 获取订阅配置（模板 ID）
     * GET /api/miniprogram/subscribe-config
     */
    public function config(): JsonResponse
    {
        $cfg = $this->wechat->getConfig();

        return ApiResponse::success([
            'template_id' => $cfg['subscribe_template_id'] ?: null,
            'enabled' => ! empty($cfg['subscribe_template_id']) && ! empty($cfg['appid']),
        ]);
    }

    /**
     * 订阅 License 过期提醒
     * POST /api/miniprogram/subscribe-expiry
     */
    public function subscribeExpiry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string|max:100',
            'remind_days' => 'nullable|integer|in:1,3,7',
        ]);

        $user = $request->user();
        if (! $user || empty($user->wechat_openid)) {
            return ApiResponse::error('WECHAT_OPENID_REQUIRED', __("app.mini_program_subscribe.msg_2ee9a723"), 401);
        }

        $cfg = $this->wechat->getConfig();
        if (empty($cfg['subscribe_template_id'])) {
            return ApiResponse::error('SUBSCRIBE_NOT_CONFIGURED', __("app.mini_program_subscribe.msg_cf297a7e"), 503);
        }

        $license = License::where('license_key', $data['license_key'])->first();
        if (! $license) {
            return ApiResponse::error('LICENSE_NOT_FOUND', __("app.mini_program_subscribe.msg_1c063fb0"), 404);
        }

        if (! $license->expires_at) {
            return ApiResponse::error('LICENSE_NO_EXPIRY', __("app.mini_program_subscribe.msg_24e4fb20"), 422);
        }

        $sub = MiniprogramExpirySubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'license_key' => $license->license_key,
            ],
            [
                'wechat_openid' => $user->wechat_openid,
                'license_id' => $license->id,
                'license_expires_at' => $license->expires_at,
                'remind_days' => $data['remind_days'] ?? 7,
                'status' => 'active',
                'last_sent_at' => null,
            ]
        );

        return ApiResponse::success([
            'id' => $sub->id,
            'license_key' => $sub->license_key,
            'expires_at' => $sub->license_expires_at?->toDateString(),
            'status' => $sub->status,
        ], __('app.mini_program_subscribe.subscribe_success'));
    }
}
