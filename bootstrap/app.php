<?php

use App\Http\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \App\Providers\EventServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'tenant' => \App\Http\Middleware\SetTenantContext::class,
            'idempotent' => \App\Http\Middleware\IdempotencyMiddleware::class,
            'nonce' => \App\Http\Middleware\NonceMiddleware::class,
            'signature' => \App\Http\Middleware\SignatureMiddleware::class,
            'ratelimit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'throttle.enhanced' => \App\Http\Middleware\EnhancedThrottleMiddleware::class,
            'circuit-breaker' => \App\Http\Middleware\CircuitBreakerMiddleware::class,
            'brute-force' => \App\Http\Middleware\BruteForceMiddleware::class,
            'mfa' => \App\Http\Middleware\MfaMiddleware::class,
            'global-resource' => \App\Http\Middleware\GlobalResourceWhitelist::class,
            'global-resource.write' => \App\Http\Middleware\GlobalResourceWriteProtection::class,
            'security-headers' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
            'mask' => \App\Http\Middleware\DataMaskingMiddleware::class,
            'body-limit' => \App\Http\Middleware\BodySizeLimiter::class,
            'maintenance' => \App\Http\Middleware\MaintenanceMiddleware::class,
            'apm' => \App\Http\Middleware\ApmMiddleware::class,
            'api-key' => \App\Http\Middleware\ApiKeyAuthMiddleware::class,
            'impersonate' => \App\Http\Middleware\ImpersonateMiddleware::class,
        ]);

        // 应用层中间件统一注册（按 M0-11 ADR：这些由应用层处理，网关层不应重复）
        $middleware->api(prepend: [
            \App\Http\Middleware\SetTenantContext::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class, // CORS/CSP/安全头 — 应用层统一处理
            \App\Http\Middleware\ImpersonateMiddleware::class, // 模拟登录 — 在所有认证路由之前检查
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 404 → 统一格式
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::notFound('资源不存在');
            }
        });

        // 验证错误 → 统一格式
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::validationError('验证失败', $e->errors());
            }
        });

        // 认证错误 → 统一格式
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::unauthorized($e->getMessage() ?: '未授权访问');
            }
        });
    })->create();
