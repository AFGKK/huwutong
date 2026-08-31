<?php

use App\Http\Controllers\Api\CspViolationController;
use App\Http\Controllers\Api\MaintenanceModeController;
use App\Http\Controllers\Api\ActivateController;
use App\Http\Controllers\Api\AppealController;
use App\Http\Controllers\Api\FeatureFlagController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\OpenFeatureController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StatusPageController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ChatFaqController;
use App\Http\Controllers\Api\ProductDemoController;
use App\Http\Controllers\Api\NpsSurveyController;
use App\Http\Controllers\Api\ApiPlaygroundController;
use App\Http\Controllers\Api\EmailTrackingController;
use App\Http\Controllers\Api\TrialController;
use App\Http\Controllers\Api\OfflineController;
use App\Http\Controllers\Api\LicenseFileCdnController;
use App\Http\Controllers\Api\EmbeddedWidgetController;
use App\Http\Controllers\Api\ErrorCodeController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BlogCommentController;
use App\Http\Controllers\Api\MeilisearchController;
use App\Http\Controllers\Api\AnnounceBannerController;
use App\Http\Controllers\Api\CookieConsentController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\KbController;
use App\Http\Controllers\Api\RagController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\UpdatePackageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserChatController;
use App\Http\Controllers\Api\CloudMarketplaceController;
use App\Http\Controllers\Api\CiCdController;
use App\Http\Controllers\Api\LocaleController;
use App\Http\Controllers\Api\MiniProgramLoginController;
use App\Http\Controllers\Api\MiniProgramSubscribeController;

// D-31: 微信小程序登录
Route::post('/miniprogram/login', [MiniProgramLoginController::class, 'login']);

// A4: 订阅配置（公开，便于未登录时展示开关）
Route::get('/miniprogram/subscribe-config', [MiniProgramSubscribeController::class, 'config']);

// A4: 订阅过期提醒（需登录）
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::post('/miniprogram/subscribe-expiry', [MiniProgramSubscribeController::class, 'subscribeExpiry']);
    Route::get('/miniprogram/profile', [\App\Http\Controllers\Api\MiniProgramProfileController::class, 'profile']);
    Route::post('/miniprogram/bind-phone', [\App\Http\Controllers\Api\MiniProgramProfileController::class, 'bindPhone']);
    Route::get('/miniprogram/my-activations', [\App\Http\Controllers\Api\MiniProgramProfileController::class, 'myActivations']);
    Route::post('/miniprogram/h5-sso', [\App\Http\Controllers\Api\MiniProgramSsoController::class, 'issue']);
});

// 小程序 → H5 一次性登录兑换（公开，短时 code）
Route::post('/miniprogram/h5-sso/exchange', [\App\Http\Controllers\Api\MiniProgramSsoController::class, 'exchange'])
    ->middleware('throttle:20,1');

// CSP 违规报告（公开端点，浏览器可直接 POST）
Route::post('/csp-violations/report', [CspViolationController::class, 'report']);

// 维护模式检查（公开端点）
Route::get('/maintenance/status', [MaintenanceModeController::class, 'status']);

// License activation and validation (public - SDK calls)
Route::middleware(['nonce', 'signature', 'idempotent', 'body-limit:activate'])->group(function () {
    Route::post('/license/activate', [ActivateController::class, 'activate'])
        ->middleware('throttle:30,1');
    Route::post('/license/validate', [ActivateController::class, 'validate'])
        ->middleware('throttle:60,1');
});

// 账号申诉（公开 - 被封禁用户无需登录）
Route::post('/appeal/submit', [AppealController::class, 'submit']);
Route::get('/appeal/lookup', [AppealController::class, 'lookup']);

// 微信小程序 License 激活（通过 Sanctum Token 认证，绕过签名中间件）
Route::middleware(['auth:sanctum', 'throttle:30,1'])->post('/license/miniprogram/activate', [ActivateController::class, 'activate']);

// Feature flag check (public - SDK calls)
Route::post('/license/check-feature', [FeatureFlagController::class, 'checkFeature']);
Route::post('/license/check-features', [FeatureFlagController::class, 'checkFeatures']);
Route::post('/license/features', [FeatureFlagController::class, 'licenseFeatures']);

// 公开 License 查询（无需认证 - 官网前台用）
Route::post('/license/public-lookup', [LicenseController::class, 'publicLookup']);

// ── SDK Telemetry 心跳/事件上报 (M2-32) ──
// SDK 端调用，通过 license_key + fingerprint 验证身份
Route::post('/telemetry/heartbeat', [TelemetryController::class, 'heartbeat']);
Route::post('/telemetry/events', [TelemetryController::class, 'reportEvents']);

// OpenFeature Provider (public - OTel/flagd integration)
Route::prefix('openfeature')->group(function () {
    Route::get('/metadata', [OpenFeatureController::class, 'metadata']);
    Route::post('/evaluate', [OpenFeatureController::class, 'evaluate']);
    Route::post('/evaluate/bulk', [OpenFeatureController::class, 'evaluateBulk']);
    Route::post('/flags', [OpenFeatureController::class, 'allFlags']);
    Route::get('/health', [OpenFeatureController::class, 'health']);
});

// flagd-compatible endpoints
Route::prefix('flagd/evaluation/v1')->group(function () {
    Route::get('/health', [OpenFeatureController::class, 'health']);
    Route::post('/{type}', [OpenFeatureController::class, 'flagdEvaluate'])->where('type', 'boolean|string|integer|float|object');
    Route::post('/bulk', [OpenFeatureController::class, 'flagdBulk']);
});

// Health check endpoints (public - K8s probes)
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/health/status', [HealthController::class, 'status']);

// Public status page (for status.huwutong.com)
Route::get('/status', [StatusPageController::class, 'index']);
Route::get('/status/history', [StatusPageController::class, 'history']);
Route::post('/status/subscribe', [StatusPageController::class, 'subscribe']);

// Public settings and pages
Route::get('/settings/public', [SiteSettingController::class, 'publicSettings']);
Route::get('/pages/public/{slug}', [PageController::class, 'showBySlug']);
Route::get('/public/products', [\App\Http\Controllers\Public\PublicPageController::class, 'apiProducts']);

// 聊天 FAQ（公开）
Route::get('/chat-faqs', [ChatFaqController::class, 'index']);

// Product Demo（公开）
Route::get('/products/{product}/demo', [ProductDemoController::class, 'publicShow'])
    ->whereNumber('product');

// NPS 满意度调查提交（公开）
Route::post('/nps-survey/submit-response', [NpsSurveyController::class, 'submitResponse']);

// API Playground - 公开端点列表
Route::get('/playground/endpoints', [ApiPlaygroundController::class, 'endpoints']);

// Email tracking (public - embedded in HTML emails)
Route::get('/track/pixel', [EmailTrackingController::class, 'trackingPixel']);
Route::get('/track/click', [EmailTrackingController::class, 'clickRedirect']);

// Trial
Route::post('/trial', [TrialController::class, 'store']);
Route::get('/trial/{license}', [TrialController::class, 'status']);
Route::post('/trial/{license}/convert', [TrialController::class, 'convert']);

// Offline verification (public - SDK integration)
Route::post('/offline/verify', [OfflineController::class, 'verify']);
Route::get('/offline/public-key', [OfflineController::class, 'publicKey']);
Route::get('/offline/crl', [OfflineController::class, 'crl']);

// License file CDN (public - client download)
Route::get('/license-file/download/{licenseKey}', [LicenseFileCdnController::class, 'download']);
Route::get('/license-file/public-keys', [LicenseFileCdnController::class, 'publicKeys']);
Route::get('/license-file/crl', [LicenseFileCdnController::class, 'crl']);

// ─── 受保护 API（需认证）───

// 嵌入式 Widget API（独立 JWT 认证）
Route::prefix('widget')->group(function () {
    Route::post('/token', [EmbeddedWidgetController::class, 'generateToken'])->middleware('auth:sanctum');
    Route::get('/data', [EmbeddedWidgetController::class, 'getWidgetData'])->middleware('widget-auth');
    Route::get('/config', [EmbeddedWidgetController::class, 'getWidgetConfig'])->middleware('widget-auth');
});

// ─── 公开知识库 ───
Route::get('/kb/categories', [KbController::class, 'categories']);
Route::get('/kb/search', [KbController::class, 'search']);
Route::get('/kb/suggest', [KbController::class, 'suggest']);
Route::get('/kb/articles/{article}', [KbController::class, 'show'])->whereNumber('article');
Route::post('/kb/articles/{article}/feedback', [KbController::class, 'feedback'])->whereNumber('article');

// RAG Engine (public)
Route::post('/rag/retrieve', [RagController::class, 'retrieve']);
Route::post('/rag/ask', [RagController::class, 'ask']);
Route::get('/rag/history', [RagController::class, 'history']);
Route::post('/rag/feedback', [RagController::class, 'feedback']);

// Chat Dialog (public)
Route::post('/chat/send', [ChatController::class, 'send']);
Route::post('/chat/stream', [ChatController::class, 'sendStream']);
Route::get('/chat/history', [ChatController::class, 'history']);
Route::post('/chat/feedback', [ChatController::class, 'feedback']);
Route::get('/chat/intents', [ChatController::class, 'intents']);

// Update download (public route)
Route::get('/updates/{updatePackage}/download', [UpdatePackageController::class, 'download'])->whereNumber('updatePackage');

// 公告横幅（公开接口，未登录也能获取）
Route::get('/announce-banners/active', [AnnounceBannerController::class, 'active']);

// Cookie Consent（公开接口）
Route::get('/cookie-consent/config', [CookieConsentController::class, 'config']);
Route::post('/cookie-consent/consent', [CookieConsentController::class, 'consent']);

// ── 公开产品列表 ──
Route::prefix('public')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
});

// ── Blog 公开端点 ──
Route::prefix('public/blog')->group(function () {
    Route::get('/categories', [BlogController::class, 'categories']);
    Route::get('/published/{type?}', [BlogController::class, 'publishedList']);
    Route::get('/author/{authorId}/followers-count', [BlogController::class, 'authorFollowerCount'])->whereNumber('authorId');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/follow', [BlogController::class, 'follow']);
        Route::post('/unfollow', [BlogController::class, 'unfollow']);
        Route::get('/follow-status', [BlogController::class, 'followStatus']);
    });
    Route::get('/changelog/versions', [BlogController::class, 'latestChangelog']);
    Route::post('/subscriptions', [BlogController::class, 'subscribe']);
    // ID 路由（必须在 {slug} 之前）
    Route::get('/id/{id}/comments', [BlogCommentController::class, 'publicIndexById'])->whereNumber('id');
    Route::post('/{id}/generate-summary', [BlogController::class, 'generateSummary'])->whereNumber('id');
    Route::post('/{id}/ai-summary', [BlogController::class, 'generateSummary'])->whereNumber('id');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/id/{id}/comments', [BlogCommentController::class, 'publicStoreById'])->whereNumber('id');
    });
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/{id}/like', [BlogController::class, 'toggleLike'])->whereNumber('id');
        Route::post('/{id}/favorite', [BlogController::class, 'toggleFavorite'])->whereNumber('id');
        Route::post('/{id}/readlater', [BlogController::class, 'toggleReadLater'])->whereNumber('id');
        Route::get('/{id}/readlater/status', [BlogController::class, 'readLaterStatus'])->whereNumber('id');
        Route::post('/{id}/share', [BlogController::class, 'recordShare'])->whereNumber('id');
        Route::get('/{id}/interaction', [BlogController::class, 'interactionStatus'])->whereNumber('id');
    });
    Route::get('/{id}/related', [BlogController::class, 'relatedPosts'])->whereNumber('id');
    // {slug} 路由（通配，必须放在最后）
    Route::get('/{slug}', [BlogController::class, 'showBySlug']);
    Route::get('/{slug}/comments', [BlogCommentController::class, 'publicIndex']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/{slug}/comments', [BlogCommentController::class, 'publicStore']);
    });
});

// ── Blog 公开操作（无 auth） ──
Route::post('/blog/{id}/view', [BlogController::class, 'recordView'])->whereNumber('id');

// ── RSS 订阅 ──
Route::get('/rss/{feedType?}', [BlogController::class, 'rss']);

// ── SDK 错误码标准化文档 (M2-34) ──
// 公开端点 — SDK 可查询错误码定义
Route::get('/error-codes', [ErrorCodeController::class, 'index']);
Route::get('/error-codes/by-domain', [ErrorCodeController::class, 'byDomain']);
Route::get('/error-codes/search', [ErrorCodeController::class, 'search']);
Route::get('/error-codes/{code}', [ErrorCodeController::class, 'show']);

// 公开 API 文档（配置回退 + DB active 端点）
Route::get('/api-docs/public', [\App\Http\Controllers\Public\IntegrationDocsController::class, 'publicApiJson']);

// 公开邀请（无需认证）
Route::get('/invite/{token}', [UserChatController::class, 'inviteInfo']);
Route::post('/invite/{token}/join', [UserChatController::class, 'joinViaInvite']);

// 前端错误报告
Route::post('/errors/report', function (Illuminate\Http\Request $request) {
    try {
        Illuminate\Support\Facades\Log::warning('Frontend error report', $request->all());
    } catch (\Throwable $e) {}
    return response()->json(['success' => true]);
});

// ── 公开搜索 - 互物库 ──
Route::get('/meilisearch/unified-search', [MeilisearchController::class, 'unifiedSearch']);
Route::get('/meilisearch/suggest', [MeilisearchController::class, 'suggest']);
Route::get('/meilisearch/trending', [MeilisearchController::class, 'trending']);

// ── 公开定价方案 ──
Route::get('/public/pricing-plans', [\App\Http\Controllers\Public\PublicPageController::class, 'pricingPlans']);
Route::get('/public/sdks', [\App\Http\Controllers\Public\PublicPageController::class, 'publicSdks']);
Route::post('/public/contact', [\App\Http\Controllers\Public\PublicPageController::class, 'submitContact'])
    ->middleware('throttle:10,1');
Route::post('/public/enterprise-contact', [\App\Http\Controllers\Public\PublicPageController::class, 'enterpriseContact'])
    ->middleware('throttle:10,1');

// ── API 版本管理 - 公开端点 ──
Route::get('/api-version', [\App\Http\Controllers\Api\ApiVersionController::class, 'defaultInfo']);

// ── SDK Integrity 公开端点 ──
Route::prefix('sdk-integrity')->group(function () {
    Route::post('/submit-check', [\App\Http\Controllers\Api\SdkIntegrityController::class, 'submitCheck']);
    Route::post('/poll-destroy', [\App\Http\Controllers\Api\SdkIntegrityController::class, 'pollDestroy']);
    Route::post('/confirm-destroy', [\App\Http\Controllers\Api\SdkIntegrityController::class, 'confirmDestroy']);
    Route::post('/heartbeat', [\App\Http\Controllers\Api\SdkIntegrityController::class, 'heartbeat']);
    Route::get('/sdk-config', [\App\Http\Controllers\Api\SdkIntegrityController::class, 'sdkConfig']);
});

// ── SDK 本地缓存公开端点 ──
Route::prefix('sdk-cache')->group(function () {
    Route::post('/report-status', [\App\Http\Controllers\Api\SdkLocalCacheController::class, 'reportStatus']);
    Route::post('/check-status', [\App\Http\Controllers\Api\SdkLocalCacheController::class, 'checkStatus']);
    Route::get('/config', [\App\Http\Controllers\Api\SdkLocalCacheController::class, 'getConfig']);
});

// ── CI/CD 公开端点 ──
Route::prefix('ci')->group(function () {
    Route::get('/license/fetch', [CiCdController::class, 'fetchLicense'])->middleware('throttle:30,1');
    Route::post('/license/activate', [CiCdController::class, 'activateLicense'])->middleware('throttle:30,1');
    Route::get('/token/info', [CiCdController::class, 'tokenInfo'])->middleware('throttle:60,1');
    Route::get('/examples', [CiCdController::class, 'examples']);
});

// ── Cloud Marketplace 公开端点 ──
Route::post('/marketplace/aws/sns', [CloudMarketplaceController::class, 'awsSnsNotification']);

// ── 公开广场/社区系统 ──
Route::prefix('moments')->group(function () {
    Route::get('/public', [\App\Http\Controllers\Api\MomentController::class, 'index']);
    Route::get('/public/tags', [\App\Http\Controllers\Api\MomentController::class, 'trendingTags']);
    Route::get('/public/top-contributors', [\App\Http\Controllers\Api\MomentController::class, 'topContributors']);
    Route::get('/public/suggested-users', [\App\Http\Controllers\Api\MomentController::class, 'suggestedUsers']);
    Route::get('/public/tag-suggestions', [\App\Http\Controllers\Api\MomentController::class, 'tagSuggestions']);
    Route::get('/public/{id}', [\App\Http\Controllers\Api\MomentController::class, 'showPublic'])->whereNumber('id');
    Route::get('/public/{id}/comments', [\App\Http\Controllers\Api\MomentController::class, 'commentsPublic'])->whereNumber('id');
});

// ── 互物号系统（公开路由） ──
Route::prefix('official-accounts')->group(function () {
    Route::get('/public', [\App\Http\Controllers\Api\OfficialAccountController::class, 'index']);
    Route::get('/public/articles', [\App\Http\Controllers\Api\OfficialAccountController::class, 'allArticles']);
    Route::get('/public/ranking', [\App\Http\Controllers\Api\OfficialAccountController::class, 'ranking']);
    Route::get('/public/categories', [\App\Http\Controllers\Api\OfficialAccountController::class, 'categories']);
    Route::get('/public/search', [\App\Http\Controllers\Api\OfficialAccountController::class, 'search']);
    Route::get('/public/{id}/articles', [\App\Http\Controllers\Api\OfficialAccountController::class, 'articles'])->whereNumber('id');
    Route::get('/public/recommended-channels', [\App\Http\Controllers\Api\OfficialAccountController::class, 'recommendedChannels']);
    Route::get('/public/popular-tags', [\App\Http\Controllers\Api\OfficialAccountController::class, 'popularTags']);
    Route::get('/public/{id}/collections', [\App\Http\Controllers\Api\OfficialAccountController::class, 'collections'])->whereNumber('id');
    Route::get('/articles/{articleId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'articleDetail'])->whereNumber('articleId');
    Route::get('/articles/{articleId}/comments', [\App\Http\Controllers\Api\OfficialAccountController::class, 'articleCommentsPublic'])->whereNumber('articleId');
});

// ── 公开 Bot Webhook ──
Route::prefix('bots')->group(function () {
    Route::post('/webhook', [\App\Http\Controllers\Api\BotController::class, 'webhook'])->withoutMiddleware(['auth:sanctum']);
});

// ── 产品交互式演示（公开） ──
Route::prefix('demo')->group(function () {
    Route::post('/start', [DemoController::class, 'start']);
    Route::get('/data', [DemoController::class, 'data']);
    Route::post('/step', [DemoController::class, 'step']);
    Route::post('/action', [DemoController::class, 'action']);
    Route::post('/heartbeat', [DemoController::class, 'heartbeat']);
    Route::post('/extend', [DemoController::class, 'extend']);
    Route::post('/complete', [DemoController::class, 'complete']);
    Route::post('/register', [DemoController::class, 'register']);
});

// ── D-22: 多语言切换 ──
Route::prefix('locale')->group(function () {
    Route::post('/switch', [LocaleController::class, 'switch']);
    Route::get('/current', [LocaleController::class, 'current']);
    Route::get('/supported', [LocaleController::class, 'supported']);
});
