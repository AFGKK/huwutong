<?php

namespace App\Http\Middleware;

use App\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * 仅允许 mfa-setup 能力的临时 token 访问 MFA 绑定相关接口，禁止进入后台业务。
 * 直接解析 Bearer，不依赖 auth 中间件顺序。
 */
class RestrictMfaSetupToken
{
    /** @var list<string> */
    private array $allowedPaths = [
        'api/mfa/setup',
        'api/mfa/confirm',
        'api/logout',
        'api/user',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($bearer);
        if (! $token) {
            return $next($request);
        }

        // 拥有 * 能力 = 正常会话
        if ($token->can('*')) {
            return $next($request);
        }

        // 仅 mfa-setup：锁定可访问路径
        if ($token->can('mfa-setup')) {
            $path = trim($request->path(), '/');
            foreach ($this->allowedPaths as $allowed) {
                if ($path === $allowed || str_starts_with($path, $allowed.'/')) {
                    return $next($request);
                }
            }

            return ApiResponse::error(
                'MFA_SETUP_REQUIRED',
                __('app.auth.api.mfa_setup_required'),
                403,
                ['mfa_setup_required' => true],
            );
        }

        return $next($request);
    }
}
