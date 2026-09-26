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
 * - 策略要求但未绑定：仅放行 setup/confirm，其余返回 MFA_SETUP_REQUIRED
 * - 已绑定：敏感写操作要求 X-MFA-Code；只读状态查询放行（避免设置页误判未启用）
 */
class MfaMiddleware
{
    /** @var list<string> 未绑定时允许的引导接口 */
    private array $bootstrapPaths = [
        'api/mfa/setup',
        'api/mfa/confirm',
    ];

    /** @var list<string> 已绑定后允许无 MFA 码的只读接口 */
    private array $readOnlyPaths = [
        'api/mfa/devices',
        'api/mfa/recovery-codes',
        'api/mfa/setup',
        'api/mfa/confirm',
    ];

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

        $path = trim($request->path(), '/');
        $isBootstrap = in_array($path, $this->bootstrapPaths, true);
        $isReadOnly = $request->isMethod('GET') && in_array($path, $this->readOnlyPaths, true);

        // 3. 策略要求但尚未启用：只允许绑定向导
        if (! $user->mfa_enabled) {
            if ($isBootstrap) {
                return $next($request);
            }

            return ApiResponse::error(
                'MFA_SETUP_REQUIRED',
                __('app.auth.api.mfa_setup_required'),
                403,
                ['mfa_setup_required' => true, 'mfa_setup_url' => url('/api/mfa/setup')],
            );
        }

        // 4. 已启用但密钥缺失
        if ($user->mfa_secret === null) {
            if ($isBootstrap) {
                return $next($request);
            }

            return ApiResponse::error(
                'MFA_NOT_CONFIGURED',
                '请先配置 MFA 认证',
                403,
                ['mfa_required' => true, 'mfa_setup_url' => url('/api/mfa/setup')],
            );
        }

        // 5. 绑定引导 / 只读状态查询：已登录会话即可
        if ($isBootstrap || $isReadOnly) {
            return $next($request);
        }

        // 6. 敏感操作（禁用/删设备/重生成恢复码等）要求 MFA 码
        $mfaCode = $request->header('X-MFA-Code') ?? $request->input('mfa_code');

        if (! $mfaCode) {
            return ApiResponse::error(
                'MFA_CODE_REQUIRED',
                '需要 MFA 验证码',
                403,
                ['mfa_required' => true],
            );
        }

        // 7. 验证 MFA 码
        $result = $mfaService->verifyMfa($user, $mfaCode);

        if (! $result['verified']) {
            return ApiResponse::error(
                'MFA_CODE_INVALID',
                'MFA 验证码无效',
                401,
            );
        }

        $request->attributes->set('mfa_verified', true);
        $request->attributes->set('mfa_method', $result['method']);

        return $next($request);
    }
}
