<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * D-31: 微信小程序登录
 *
 * 使用 wx.login 获取的 code 换取 openid，
 * 查找或创建用户，返回 Bearer Token。
 *
 * 从 site_settings 表读取:
 *   wechat_mini_program_appid  — 小程序 AppID
 *   wechat_mini_program_secret — 小程序 AppSecret
 */
class MiniProgramLoginController extends Controller
{
    const WX_CODE2SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session';

    /**
     * 微信小程序登录
     *
     * POST /api/miniprogram/login
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        // 从 SiteSetting 表读取微信小程序配置
        $wxConfig = SiteSetting::getWechatMiniProgramConfig();
        $appId = $wxConfig['appid'];
        $secret = $wxConfig['secret'];

        if (!$appId || !$secret) {
            Log::warning('小程序登录: 微信配置未完善');
            return ApiResponse::error('WECHAT_CONFIG_INCOMPLETE', __('app.api.miniprogram_login.wechat_not_configured'), 500);
        }

        // 调用微信接口换取 session
        $response = Http::timeout(10)->get(self::WX_CODE2SESSION_URL, [
            'appid' => $appId,
            'secret' => $secret,
            'js_code' => $validated['code'],
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            Log::error('小程序登录: 微信接口请求失败', ['status' => $response->status()]);
            return ApiResponse::error('WECHAT_API_ERROR', __('app.api.miniprogram_login.wechat_unavailable'), 502);
        }

        $wxData = $response->json();

        // 检查微信错误
        if (!empty($wxData['errcode'])) {
            Log::warning('小程序登录: 微信返回错误', [
                'errcode' => $wxData['errcode'],
                'errmsg' => $wxData['errmsg'] ?? '',
            ]);

            $message = match ($wxData['errcode']) {
                -1 => __('app.api.miniprogram_login.system_busy'),
                40029 => __('app.api.miniprogram_login.code_invalid'),
                45011 => __('app.api.miniprogram_login.too_frequent'),
                default => __('app.api.miniprogram_login.login_failed', ['code' => $wxData['errcode']]),
            };

            return ApiResponse::error('WECHAT_LOGIN_FAILED', $message, 400);
        }

        $openid = $wxData['openid'] ?? '';
        $unionid = $wxData['unionid'] ?? '';
        $sessionKey = $wxData['session_key'] ?? '';

        if (!$openid) {
            return ApiResponse::error('WECHAT_NO_OPENID', __('app.api.miniprogram_login.no_openid'), 400);
        }

        // 查找或创建用户
        $user = User::where('wechat_openid', $openid)->first();

        if (!$user) {
            // 检查是否已有同一个 unionid 的用户
            if ($unionid) {
                $user = User::where('wechat_unionid', $unionid)->first();
            }
        }

        if (!$user) {
            // 创建新用户
            $user = User::create([
                'name' => __('app.api.miniprogram_login.wx_user_name', ['suffix' => substr($openid, -6)]),
                'email' => 'wx_' . $openid . '@wechat.mini',
                'password' => bcrypt(Str::random(32)),
                'wechat_openid' => $openid,
                'wechat_unionid' => $unionid ?: null,
                'email_verified_at' => now(),
                'source' => 'wechat_miniprogram',
            ]);
        } else {
            // 更新 openid/unionid
            $user->wechat_openid = $openid;
            if ($unionid) {
                $user->wechat_unionid = $unionid;
            }
            $user->save();
        }

        // 生成 Token（7天过期）
        $token = $user->createToken('wechat_miniprogram', ['*'], now()->addDays(7))->plainTextToken;

        $deviceBindId = 'wx_mp_' . substr(hash('sha256', $openid), 0, 24);

        return ApiResponse::success([
            'token' => $token,
            'device_bind_id' => $deviceBindId,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar_url,
                'phone' => $user->phone,
                'phone_masked' => $user->phone
                    ? (substr($user->phone, 0, 3) . '****' . substr($user->phone, -4))
                    : null,
            ],
        ], __('app.api.miniprogram_login.login_success'));
    }
}
