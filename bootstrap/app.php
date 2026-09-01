<?php

use App\Enums\ErrorCode;
use App\Exceptions\SdkException;
use App\Http\ApiResponse;
use App\Services\ErrorCodeService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Echo 私有频道鉴权：SPA 使用 Bearer Token（auth:sanctum），非仅 session cookie
            Broadcast::routes(['middleware' => ['auth:sanctum']]);
            require base_path('routes/channels.php');

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/portal.php'));
        },
    )
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
        \App\Providers\EventServiceProvider::class,
        \App\Providers\TelescopeServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'tenant' => \App\Http\Middleware\SetTenantContext::class,
            'idempotent' => \App\Http\Middleware\IdempotencyMiddleware::class,
            'nonce' => \App\Http\Middleware\NonceMiddleware::class,
            'smart-contract' => \App\Http\Middleware\SmartContractMiddleware::class,
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
            'api-version' => \App\Http\Middleware\ApiVersionMiddleware::class,
            'domain-tenant' => \App\Http\Middleware\ResolveDomainTenant::class,
            'introspect' => \App\Http\Middleware\TokenIntrospectionMiddleware::class,
            'fine-grained-api-key' => \App\Http\Middleware\FineGrainedApiKeyMiddleware::class,
            'widget-auth' => \App\Http\Middleware\WidgetAuthMiddleware::class,
            'waf' => \App\Http\Middleware\WafMiddleware::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
        ]);

        // 应用层中间件统一注册（按 M0-11 ADR：这些由应用层处理，网关层不应重复）
        $middleware->web(prepend: [
            \App\Http\Middleware\SetLocale::class, // D-22: 自动语言检测 — web 路由组首页/公共页面
            \App\Http\Middleware\MaintenanceMiddleware::class,
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\ResolveDomainTenant::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class, // CORS/CSP/安全头 — 应用层统一处理
            \App\Http\Middleware\ImpersonateMiddleware::class, // 模拟登录 — 在所有认证路由之前检查
            \App\Http\Middleware\SetLocale::class, // D-22: 自动语言检测
            \App\Http\Middleware\MaintenanceMiddleware::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\ApiVersionMiddleware::class, // API 版本管理 — 在路由处理之后添加版本响应头
        ]);

        // Webhook + 广播认证路由排除 CSRF 保护
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
            'api/broadcasting/auth',
            'api/payment/stripe/webhook',
            'api/payment/alipay/webhook',
            'api/payment/paypal/webhook',
            'api/payment/wechat/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /** @var ErrorCodeService $errorCodeService */
        $errorCodeService = app(ErrorCodeService::class);

        // M2-34: SdkException → 自动使用 ErrorCode 枚举渲染
        $exceptions->render(function (SdkException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $e->render($request);
            }
        });

        // 404 → 统一格式
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error(
                    ErrorCode::NOT_FOUND->value,
                    $errorCodeService->message(ErrorCode::NOT_FOUND),
                    404
                );
            }
        });

        // Symfony 404
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $svc = app(\App\Services\ErrorCodeService::class);
                return ApiResponse::error(
                    ErrorCode::NOT_FOUND->value,
                    $svc->message(ErrorCode::NOT_FOUND),
                    404
                );
            }
        });

        // 验证错误 → 统一格式
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $svc = app(\App\Services\ErrorCodeService::class);
                return ApiResponse::validationError(
                    $svc->message(ErrorCode::VALIDATION_ERROR),
                    $e->errors()
                );
            }
        });

        // 认证错误 → 统一格式
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $svc = app(\App\Services\ErrorCodeService::class);
                return ApiResponse::error(
                    ErrorCode::UNAUTHORIZED->value,
                    $e->getMessage() ?: $svc->message(ErrorCode::UNAUTHORIZED),
                    401
                );
            }
        });

        // HttpException 兜底（如 403/429 等）
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $statusCode = $e->getStatusCode();
                $code = match ($statusCode) {
                    403 => ErrorCode::FORBIDDEN,
                    404 => ErrorCode::NOT_FOUND,
                    429 => ErrorCode::RATE_LIMITED,
                    default => ErrorCode::SYS_INTERNAL_ERROR,
                };

                $svc = app(\App\Services\ErrorCodeService::class);
                return ApiResponse::error(
                    $code->value,
                    $e->getMessage() ?: $svc->message($code),
                    $statusCode
                );
            }
        });

        // 全局兜底 — 捕获未处理异常
        $exceptions->render(function (\Throwable $e, Request $request) use ($errorCodeService) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $message = config('app.debug') ? $e->getMessage() : $errorCodeService->message(ErrorCode::SYS_INTERNAL_ERROR);

                return ApiResponse::error(
                    ErrorCode::SYS_INTERNAL_ERROR->value,
                    $message,
                    500
                );
            }
        });
    })->create();
