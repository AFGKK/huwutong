<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use App\Models\User;
use App\Services\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MFA 中间件
 *
 * 功能：
 * - 检查用户是否已启用 MFA（首次登录时要求配置）
 * - 检查请求是否包含有效的 MFA 验证码
 * - 检查 IP 是否在白名单内
 *
 * 使用方式：
 *   Route::middleware('auth:sanctum')->middleware('mfa')->get(...);
 */
class MfaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $mfaService = app(MfaService::class);

        // 1. IP 白名单检查
        if (! $mfaService->checkIpWhitelist($user, $request->ip() ?? '0.0.0.0')) {
            return ApiResponse::error(
                'IP_NOT_WHITELISTED',
                '您的 IP 不在允许访问的白名单中',
                403,
            );
        }

        // 2. MFA 策略检查
        if (! $mfaService->requiresMfa($user)) {
            return $next($request);
        }

        // 3. 如果用户启用 MFA 但没有配置设备—返回需要配置
        if ($user->mfa_enabled && $user->mfa_secret === null) {
            return ApiResponse::error(
                'MFA_NOT_CONFIGURED',
                '请先配置 MFA 认证',
                403,
                ['mfa_required' => true, 'mfa_setup_url' => url('/api/mfa/setup')],
            );
        }

        // 4. 用户启用了 MFA，检查请求是否包含 MFA 码
        $mfaCode = $request->header('X-MFA-Code') ?? $request->input('mfa_code');

        if (! $mfaCode) {
            return ApiResponse::error(
                'MFA_CODE_REQUIRED',
                '需要 MFA 验证码',
                403,
                ['mfa_required' => true],
            );
        }

        // 5. 验证 MFA 码
        $result = $mfaService->verifyMfa($user, $mfaCode);

        if (! $result['verified']) {
            return ApiResponse::error(
                'MFA_CODE_INVALID',
                'MFA 验证码无效',
                401,
            );
        }

        // 6. 标记 MFA 已验证（可用于下次请求的缓存—但不在此实现）
        $request->attributes->set('mfa_verified', true);
        $request->attributes->set('mfa_method', $result['method']);

        return $next($request);
    }
}
