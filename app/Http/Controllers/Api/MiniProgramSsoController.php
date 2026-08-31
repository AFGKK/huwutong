<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 微信小程序 ↔ H5 官网一次性登录打通
 *
 * 流程：
 * 1. 小程序已登录用户 POST /miniprogram/h5-sso 拿到短时 code
 * 2. web-view 打开 /miniprogram/bridge?code=...&redirect=/products
 * 3. 桥接页 POST /miniprogram/h5-sso/exchange 换 Sanctum token，写入 localStorage.auth_token
 */
class MiniProgramSsoController extends Controller
{
    public const CACHE_PREFIX = 'mp_h5_sso:';

    public const TTL_SECONDS = 60;

    /**
     * POST /api/miniprogram/h5-sso
     * 签发一次性凭证（需小程序 Sanctum 登录）
     */
    public function issue(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('UNAUTHORIZED', __("app.mini_program_sso.msg_8d24337b"), 401);
        }

        $code = Str::random(48);
        Cache::put(self::CACHE_PREFIX . $code, [
            'user_id' => $user->id,
            'issued_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);

        Log::info('小程序 H5 SSO 签发', ['user_id' => $user->id]);

        return ApiResponse::success([
            'code' => $code,
            'expires_in' => self::TTL_SECONDS,
            'bridge_path' => '/miniprogram/bridge',
        ]);
    }

    /**
     * POST /api/miniprogram/h5-sso/exchange
     * 公开接口：一次性 code → H5 可用的 Sanctum token
     */
    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|min:32|max:80',
        ]);

        $cacheKey = self::CACHE_PREFIX . $data['code'];
        $payload = Cache::pull($cacheKey); // 一次性消费

        if (! is_array($payload) || empty($payload['user_id'])) {
            return ApiResponse::error('SSO_CODE_INVALID', __("app.mini_program_sso.msg_79a16089"), 400);
        }

        $user = User::find($payload['user_id']);
        if (! $user) {
            return ApiResponse::error('USER_NOT_FOUND', __("app.mini_program_sso.msg_489251bf"), 404);
        }

        $token = $user->createToken(
            'miniprogram-h5-sso',
            ['*'],
            now()->addDays(7)
        )->plainTextToken;

        Log::info('小程序 H5 SSO 兑换成功', ['user_id' => $user->id]);

        return ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'phone' => $user->phone,
                'roles' => [],
            ],
        ], __('app.mini_program_sso.login_success'));
    }
}
