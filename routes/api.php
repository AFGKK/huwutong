<?php

use App\Http\Controllers\Api\ActivateController;
use App\Http\Controllers\Api\AiIntegrationWizardController;
use App\Http\Controllers\Api\ApiPlaygroundController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\RetentionController;
use App\Http\Controllers\Api\DiagnosticController;
use App\Http\Controllers\Api\KbController;
use App\Http\Controllers\Api\RagController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\CustomDomainController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DependencySecurityController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\EmailTrackingController;
use App\Http\Controllers\Api\FeatureFlagController;
use App\Http\Controllers\Api\GlobalResourceController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LicenseFileCdnController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\LlmController;
use App\Http\Controllers\Api\LlmFallbackController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfflineController;
use App\Http\Controllers\Api\OpenFeatureController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SandboxController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Http\Controllers\Api\StagingController;
use App\Http\Controllers\Api\SSOController;
use App\Http\Controllers\Api\StatusPageController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\TenantRouterController;
use App\Http\Controllers\Api\TrialController;
use App\Http\Controllers\Api\UpdatePackageController;
use App\Http\Controllers\Api\WebhookReplayController;

/*

  互物通 (Huwutong) Enterprise License System - API 路由

  命名规范：
    - 公开 API 不加中间件
    - 已认证 API 使用 auth:sanctum
    - MFA 保护的 API 使用 mfa 中间件

*/

// ─── 公开 API ───

// Authentication (public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/phone/send-code', [AuthController::class, 'sendPhoneCode']);
Route::post('/phone/login', [AuthController::class, 'phoneLogin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// OAuth login (public)
Route::post('/oauth/login', [AuthController::class, 'oauthLogin']);

// Legal consents (public)
Route::get('/legal-consents', [AuthController::class, 'getLegalConsents']);

// MFA aware login (public)
Route::post('/mfa/login', [MfaController::class, 'mfaLogin']);
Route::post('/mfa/check-required', [MfaController::class, 'checkRequired']);

// SSO
Route::post('/sso/callback', [SSOController::class, 'callback'])->name('sso.login');

// License activation and validation (public - SDK calls)
Route::middleware(['nonce', 'signature', 'idempotent'])->group(function () {
    Route::post('/license/activate', [ActivateController::class, 'activate'])
        ->middleware('throttle:30,1');
    Route::post('/license/validate', [ActivateController::class, 'validate'])
        ->middleware('throttle:60,1');
});

// Feature flag check (public - SDK calls)
Route::post('/license/check-feature', [FeatureFlagController::class, 'checkFeature']);
Route::post('/license/check-features', [FeatureFlagController::class, 'checkFeatures']);
Route::post('/license/features', [FeatureFlagController::class, 'licenseFeatures']);

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

// ─── 受保护 API（需认证） ───

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Email verification
    Route::post('/email/verify/send', [AuthController::class, 'sendEmailVerification']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmail']);

    // Password management
    Route::post('/password/change', [AuthController::class, 'changePassword']);

    // Session management
    Route::get('/sessions', [AuthController::class, 'sessions']);
    Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession'])->whereNumber('tokenId');

    // Device trust
    Route::post('/devices/trust', [AuthController::class, 'trustDevice']);
    Route::get('/devices/trusted', [AuthController::class, 'trustedDevices']);
    Route::delete('/devices/trusted/{deviceId}', [AuthController::class, 'removeTrustedDevice'])->whereNumber('deviceId');

    // Invite codes (admin)
    Route::get('/invite-codes', [AuthController::class, 'inviteCodesList']);
    Route::post('/invite-codes/generate', [AuthController::class, 'generateInviteCodes']);
    Route::get('/invite-codes/stats', [AuthController::class, 'inviteCodeStats']);

    // Legal consent
    Route::post('/legal/consent', [AuthController::class, 'consentToLegal']);

    // Account deletion
    Route::post('/account/deletion', [AuthController::class, 'requestDeletion']);
    Route::post('/account/deletion/cancel', [AuthController::class, 'cancelDeletion']);
    Route::get('/account/deletion/status', [AuthController::class, 'deletionStatus']);

    // OAuth binding
    Route::get('/oauth/providers', [AuthController::class, 'boundProviders']);
    Route::post('/oauth/bind', [AuthController::class, 'bindOAuth']);
    Route::delete('/oauth/unbind/{authProviderId}', [AuthController::class, 'unbindOAuth'])->whereNumber('authProviderId');

    // Login history
    Route::get('/login-history', [AuthController::class, 'loginHistory']);

    // Custom domains (M1.4-35)
    Route::get('/domains', [CustomDomainController::class, 'index']);
    Route::post('/domains', [CustomDomainController::class, 'store']);
    Route::get('/domains/{domain}', [CustomDomainController::class, 'show'])->whereNumber('domain');
    Route::post('/domains/{domain}/verify', [CustomDomainController::class, 'verify'])->whereNumber('domain');
    Route::post('/domains/{domain}/ssl/issue', [CustomDomainController::class, 'issueSsl'])->whereNumber('domain');
    Route::get('/domains/{domain}/dns', [CustomDomainController::class, 'dnsInfo'])->whereNumber('domain');
    Route::put('/domains/{domain}/route', [CustomDomainController::class, 'updateRoute'])->whereNumber('domain');
    Route::delete('/domains/{domain}', [CustomDomainController::class, 'destroy'])->whereNumber('domain');

    // License management
    Route::get('/licenses', [LicenseController::class, 'index']);
    Route::post('/licenses', [LicenseController::class, 'store']);
    Route::get('/licenses/{license}', [LicenseController::class, 'show'])->whereNumber('license');
    Route::put('/licenses/{license}', [LicenseController::class, 'update'])->whereNumber('license');
    Route::delete('/licenses/{license}', [LicenseController::class, 'destroy'])->whereNumber('license');
    Route::post('/licenses/batch', [LicenseController::class, 'batchStore']);
    Route::post('/licenses/lookup', [LicenseController::class, 'lookup']);
    Route::post('/licenses/{license}/restore', [LicenseController::class, 'restoreFromTrash'])->whereNumber('license');
    Route::get('/licenses/stats', [LicenseController::class, 'stats']);

    // Product management
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product');
    Route::put('/products/{product}', [ProductController::class, 'update'])->whereNumber('product');
    Route::get('/products/stats', [ProductController::class, 'stats']);
    Route::get('/products/{product}/features', [ProductController::class, 'features'])->whereNumber('product');
    Route::post('/products/{product}/features', [ProductController::class, 'assignFeature'])->whereNumber('product');
    Route::get('/products/{product}/licenses', [ProductController::class, 'licenses'])->whereNumber('product');

    // Customer management
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer');
    Route::get('/customers/{customer}/licenses', [CustomerController::class, 'licenses'])->whereNumber('customer');
    Route::get('/customers/stats', [CustomerController::class, 'stats']);

    // Device management
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/devices/{device}', [DeviceController::class, 'show'])->whereNumber('device');
    Route::post('/devices/{device}/deactivate', [DeviceController::class, 'deactivate'])->whereNumber('device');
    Route::get('/devices/stats', [DeviceController::class, 'stats']);
    Route::post('/devices/batch', [DeviceController::class, 'batch']);

    // RBAC 权限管理
    Route::get('/roles', [PermissionController::class, 'roles']);
    Route::post('/roles', [PermissionController::class, 'roleStore']);
    Route::get('/roles/{role}', [PermissionController::class, 'roleShow'])->whereNumber('role');
    Route::put('/roles/{role}', [PermissionController::class, 'roleUpdate'])->whereNumber('role');
    Route::delete('/roles/{role}', [PermissionController::class, 'roleDestroy'])->whereNumber('role');
    Route::get('/permissions', [PermissionController::class, 'allPermissions']);
    Route::get('/permissions/mine', [PermissionController::class, 'myPermissions']);
    Route::get('/users/with-roles', [PermissionController::class, 'tenantUsers']);
    Route::get('/users/{user}/roles', [PermissionController::class, 'userRoles'])->whereNumber('user');
    Route::post('/users/{user}/roles', [PermissionController::class, 'assignRoles'])->whereNumber('user');

    // License status management
    Route::post('/licenses/{license}/revoke', [LicenseController::class, 'revoke'])->whereNumber('license');
    Route::post('/licenses/{license}/suspend', [LicenseController::class, 'suspend'])->whereNumber('license');
    Route::post('/licenses/{license}/freeze', [LicenseController::class, 'freeze'])->whereNumber('license');
    Route::post('/licenses/{license}/restore', [LicenseController::class, 'restore'])->whereNumber('license');
    Route::post('/licenses/{license}/blacklist', [LicenseController::class, 'blacklist'])->whereNumber('license');
    Route::post('/licenses/{license}/refund', [LicenseController::class, 'refund'])->whereNumber('license');

    // Offline management
    Route::post('/offline/generate', [OfflineController::class, 'generate']);
    Route::post('/offline/generate/batch', [OfflineController::class, 'generateBatch']);
    Route::post('/offline/revoke', [OfflineController::class, 'revoke']);
    Route::post('/offline/restore', [OfflineController::class, 'restore']);
    Route::post('/offline/init-keys', [OfflineController::class, 'initKeys']);

    // Feature Flag management
    Route::get('/feature-flags', [FeatureFlagController::class, 'index']);
    Route::post('/feature-flags/assign', [FeatureFlagController::class, 'assign']);

    // OpenFeature management (protected)
    Route::get('/openfeature/manage/flags', [OpenFeatureController::class, 'manageAllFlags']);

    // SSO management
    Route::get('/sso/providers', [SSOController::class, 'providers']);
    Route::post('/sso/providers', [SSOController::class, 'configure']);
    Route::post('/sso/providers/{provider}/toggle', [SSOController::class, 'toggle'])->whereNumber('provider');
    Route::get('/sso/providers/{provider}/login-url', [SSOController::class, 'loginUrl'])->whereNumber('provider');
    Route::get('/sso/connections', [SSOController::class, 'connections']);
    Route::delete('/sso/connections/{connection}', [SSOController::class, 'disconnect'])->whereNumber('connection');

    // Audit logs
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/stats', [AuditLogController::class, 'stats']);
    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);

    // Webhook replay
    Route::prefix('webhook-replay')->group(function () {
        Route::get('/events', [WebhookReplayController::class, 'index']);
        Route::get('/events/{id}', [WebhookReplayController::class, 'show'])->whereNumber('id');
        Route::post('/events/{id}/replay', [WebhookReplayController::class, 'replay'])->whereNumber('id');
        Route::post('/batch-replay', [WebhookReplayController::class, 'batchReplay']);
        Route::post('/endpoints/{endpoint}/replay-all', [WebhookReplayController::class, 'replayEndpoint'])->whereNumber('endpoint');
        Route::get('/stats', [WebhookReplayController::class, 'stats']);
    });

    // MFA management (protected by mfa middleware)
    Route::prefix('mfa')->middleware('mfa')->group(function () {
        Route::get('/setup', [MfaController::class, 'setup']);
        Route::post('/confirm', [MfaController::class, 'confirm']);
        Route::post('/verify', [MfaController::class, 'verify']);
        Route::get('/devices', [MfaController::class, 'devices']);
        Route::put('/devices/{device}/rename', [MfaController::class, 'renameDevice'])->whereNumber('device');
        Route::delete('/devices/{device}', [MfaController::class, 'deleteDevice'])->whereNumber('device');
        Route::get('/recovery-codes', [MfaController::class, 'recoveryCodesStatus']);
        Route::post('/recovery-codes/regenerate', [MfaController::class, 'regenerateRecoveryCodes']);
        Route::post('/disable', [MfaController::class, 'disable']);
    });

    // Notification management
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/batch', [NotificationController::class, 'batch']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->whereNumber('notification');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

    // Billing & Subscriptions
    Route::get('/billing/stats', [BillingController::class, 'stats']);
    Route::get('/billing/subscriptions', [BillingController::class, 'index']);
    Route::post('/billing/subscriptions', [BillingController::class, 'store']);
    Route::get('/billing/subscriptions/{subscription}', [BillingController::class, 'show'])->whereNumber('subscription');
    Route::put('/billing/subscriptions/{subscription}/plan', [BillingController::class, 'changePlan'])->whereNumber('subscription');
    Route::post('/billing/subscriptions/{subscription}/cancel', [BillingController::class, 'cancel'])->whereNumber('subscription');
    Route::post('/billing/subscriptions/{subscription}/resume', [BillingController::class, 'resume'])->whereNumber('subscription');
    Route::post('/billing/subscriptions/{subscription}/renew', [BillingController::class, 'renew'])->whereNumber('subscription');
    Route::get('/billing/invoices', [BillingController::class, 'invoices']);
    Route::get('/billing/invoices/{invoice}', [BillingController::class, 'showInvoice'])->whereNumber('invoice');
    Route::post('/billing/invoices/{invoice}/mark-paid', [BillingController::class, 'markPaid'])->whereNumber('invoice');
    Route::get('/billing/invoice-stats', [BillingController::class, 'invoiceStats']);

    // Renewal pipeline / retention
    Route::get('/retention/failure-stats', [RetentionController::class, 'failureStats']);
    Route::get('/retention/subscriptions/{subscription}/failures', [RetentionController::class, 'subscriptionFailures'])->whereNumber('subscription');
    Route::post('/retention/subscriptions/{subscription}/manual-retry', [RetentionController::class, 'manualRetry'])->whereNumber('subscription');
    Route::get('/retention/escalations', [RetentionController::class, 'pendingEscalations']);
    Route::post('/retention/escalations/{escalation}/resolve', [RetentionController::class, 'resolveEscalation'])->whereNumber('escalation');

    // AI Diagnostic
    Route::post('/diagnostic/diagnose', [DiagnosticController::class, 'diagnose']);
    Route::post('/diagnostic/activation', [DiagnosticController::class, 'diagnoseActivation']);
    Route::post('/diagnostic/batch', [DiagnosticController::class, 'diagnoseBatch']);
    Route::get('/diagnostic/sdk-suggestions', [DiagnosticController::class, 'sdkSuggestions']);

    // Knowledge Base (public)
    Route::get('/kb/categories', [KbController::class, 'categories']);
    Route::get('/kb/search', [KbController::class, 'search']);
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

    // Ticket System (public)
    Route::get('/tickets/categories', [TicketController::class, 'categories']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/my', [TicketController::class, 'myTickets']);

    // Site settings management
    Route::get('/settings', [SiteSettingController::class, 'grouped']);
    Route::get('/settings/all', [SiteSettingController::class, 'index']);
    Route::post('/settings', [SiteSettingController::class, 'update']);
    Route::post('/settings/create', [SiteSettingController::class, 'store']);

    // AI Integration Wizard
    Route::get('/wizard/languages', [AiIntegrationWizardController::class, 'languages']);
    Route::get('/wizard/products', [AiIntegrationWizardController::class, 'products']);
    Route::post('/wizard/generate-config', [AiIntegrationWizardController::class, 'generateConfig']);
    Route::post('/wizard/test-connectivity', [AiIntegrationWizardController::class, 'testConnectivity']);

    // Knowledge Base (admin)
    Route::get('/kb/articles', [KbController::class, 'index']);
    Route::post('/kb/articles', [KbController::class, 'store']);
    Route::put('/kb/articles/{article}', [KbController::class, 'update'])->whereNumber('article');
    Route::post('/kb/articles/{article}/publish', [KbController::class, 'publish'])->whereNumber('article');
    Route::post('/kb/articles/{article}/archive', [KbController::class, 'archive'])->whereNumber('article');
    Route::delete('/kb/articles/{article}', [KbController::class, 'destroy'])->whereNumber('article');
    Route::get('/kb/articles/{article}/versions', [KbController::class, 'versions'])->whereNumber('article');
    Route::post('/kb/categories', [KbController::class, 'storeCategory']);
    Route::put('/kb/categories/{category}', [KbController::class, 'updateCategory'])->whereNumber('category');
    Route::delete('/kb/categories/{category}', [KbController::class, 'destroyCategory'])->whereNumber('category');

    // RAG Engine (admin)
    Route::post('/rag/articles/{article}/index', [RagController::class, 'indexArticle'])->whereNumber('article');
    Route::post('/rag/rebuild', [RagController::class, 'rebuildIndex']);
    Route::get('/rag/stats', [RagController::class, 'stats']);
    Route::get('/chat/stats', [ChatController::class, 'stats']);

    // Ticket System (admin)
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->whereNumber('ticket');
    Route::post('/tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->whereNumber('ticket');
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->whereNumber('ticket');
    Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->whereNumber('ticket');
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->whereNumber('ticket');
    Route::post('/tickets/{ticket}/satisfaction', [TicketController::class, 'satisfaction'])->whereNumber('ticket');
    Route::get('/tickets/stats', [TicketController::class, 'stats']);
    Route::post('/tickets/check-sla', [TicketController::class, 'checkSla']);
    Route::post('/tickets/categories', [TicketController::class, 'storeCategory']);
    Route::delete('/tickets/categories/{category}', [TicketController::class, 'destroyCategory'])->whereNumber('category');

    // Page management
    Route::get('/pages', [PageController::class, 'index']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::get('/pages/{page}', [PageController::class, 'show'])->whereNumber('page');
    Route::put('/pages/{page}', [PageController::class, 'update'])->whereNumber('page');
    Route::post('/pages/{page}/publish', [PageController::class, 'publish'])->whereNumber('page');
    Route::post('/pages/{page}/draft', [PageController::class, 'draft'])->whereNumber('page');
    Route::delete('/pages/{page}', [PageController::class, 'destroy'])->whereNumber('page');

    // MFA 管理后台接口
    Route::post('/admin/users/{user}/reset-mfa', [MfaController::class, 'adminResetUserMfa'])->whereNumber('user');
    Route::get('/admin/mfa-audit', [MfaController::class, 'auditLog']);

    // Email Templates
    Route::get('/email-templates', [EmailTemplateController::class, 'index']);
    Route::post('/email-templates', [EmailTemplateController::class, 'store']);
    Route::get('/email-templates/{template}', [EmailTemplateController::class, 'show'])->whereNumber('template');
    Route::put('/email-templates/{template}', [EmailTemplateController::class, 'update'])->whereNumber('template');
    Route::delete('/email-templates/{template}', [EmailTemplateController::class, 'destroy'])->whereNumber('template');
    Route::post('/email-templates/preview', [EmailTemplateController::class, 'preview']);
    Route::get('/email-templates/defaults', [EmailTemplateController::class, 'defaults']);
    Route::post('/email-templates/init-defaults', [EmailTemplateController::class, 'initDefaults']);
    Route::get('/email-templates/variables', [EmailTemplateController::class, 'variables']);

    // API Playground
    Route::post('/playground/execute', [ApiPlaygroundController::class, 'execute']);
    Route::post('/playground/generate-code', [ApiPlaygroundController::class, 'generateCode']);

    // Dependency Security
    Route::get('/deps-security', [DependencySecurityController::class, 'index']);
    Route::get('/deps-security/stats', [DependencySecurityController::class, 'stats']);
    Route::put('/deps-security/{vulnerability}', [DependencySecurityController::class, 'updateStatus'])->whereNumber('vulnerability');
    Route::post('/deps-security/batch', [DependencySecurityController::class, 'batchUpdate']);
    Route::post('/deps-security/scan', [DependencySecurityController::class, 'triggerScan']);
    Route::get('/deps-security/config', [DependencySecurityController::class, 'config']);

    // Email Tracking Dashboard
    Route::get('/email-tracking/overview', [EmailTrackingController::class, 'overview']);
    Route::get('/email-tracking/logs', [EmailTrackingController::class, 'logs']);
    Route::get('/email-tracking/template/{templateCode}', [EmailTrackingController::class, 'templateDetail']);
    Route::get('/email-tracking/bounces', [EmailTrackingController::class, 'bounceStats']);

    // Developer Sandbox
    Route::post('/sandbox/create', [SandboxController::class, 'create']);
    Route::get('/sandbox/status', [SandboxController::class, 'status']);
    Route::post('/sandbox/reset', [SandboxController::class, 'reset']);
    Route::get('/sandbox/licenses', [SandboxController::class, 'licenses']);

    // Staging 集成测试环境
    Route::get('/staging', [StagingController::class, 'index']);
    Route::post('/staging/create', [StagingController::class, 'create']);
    Route::get('/staging/{staging}', [StagingController::class, 'show']);
    Route::post('/staging/{staging}/reset', [StagingController::class, 'reset']);
    Route::put('/staging/{staging}', [StagingController::class, 'update']);
    Route::get('/staging/{staging}/licenses', [StagingController::class, 'licenses']);

    // API 密钥管理
    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::get('/api-keys/{apiKey}', [ApiKeyController::class, 'show'])->whereNumber('apiKey');
    Route::put('/api-keys/{apiKey}', [ApiKeyController::class, 'update'])->whereNumber('apiKey');
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->whereNumber('apiKey');
    Route::post('/api-keys/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate'])->whereNumber('apiKey');

    // License File CDN (management)
    Route::get('/license-files', [LicenseFileCdnController::class, 'index']);
    Route::post('/license-files/generate', [LicenseFileCdnController::class, 'generate']);
    Route::post('/license-files/batch-generate', [LicenseFileCdnController::class, 'batchGenerate']);
    Route::post('/license-files/revoke', [LicenseFileCdnController::class, 'revoke']);
    Route::post('/license-files/redistribute', [LicenseFileCdnController::class, 'redistribute']);
    Route::post('/license-files/rotate-key', [LicenseFileCdnController::class, 'rotateKey']);
    Route::get('/license-files/stats', [LicenseFileCdnController::class, 'stats']);
    Route::get('/license-files/logs', [LicenseFileCdnController::class, 'logs']);

    // LLM Provider Management
    Route::get('/llm/providers', [LlmController::class, 'providers']);
    Route::put('/llm/providers/{llmProvider}', [LlmController::class, 'updateProvider']);
    Route::post('/llm/providers/{llmProvider}/test', [LlmController::class, 'testConnection']);
    Route::post('/llm/chat', [LlmController::class, 'chat']);
    Route::post('/llm/chat-stream', [LlmController::class, 'chatStream']);
    Route::get('/llm/token-stats', [LlmController::class, 'tokenStats']);
    Route::get('/llm/logs', [LlmController::class, 'logs']);

    // LLM Fallback management
    Route::get('/llm/fallback/status', [LlmFallbackController::class, 'status']);
    Route::post('/llm/fallback/reset', [LlmFallbackController::class, 'reset']);

    // Tax Calculator
    Route::get('/tax/countries', [TaxController::class, 'countries']);
    Route::get('/tax/region/{countryCode}', [TaxController::class, 'regionTaxes']);
    Route::get('/tax/rates', [TaxController::class, 'rates']);
    Route::put('/tax/rates/{taxRate}', [TaxController::class, 'updateRate']);
    Route::post('/tax/calculate', [TaxController::class, 'calculate']);
    Route::get('/tax/stats', [TaxController::class, 'stats']);

    // Tax Exempt Certificates
    Route::get('/tax/certificates', [TaxController::class, 'certificates']);
    Route::post('/tax/certificates', [TaxController::class, 'storeCertificate']);
    Route::put('/tax/certificates/{certificate}', [TaxController::class, 'approveCertificate']);
    Route::delete('/tax/certificates/{certificate}', [TaxController::class, 'destroyCertificate']);

    // Global Resource Whitelist
            Route::get('/global-resources/config', [GlobalResourceController::class, 'config']);
            Route::get('/global-resources/check-write', [GlobalResourceController::class, 'checkWrite']);
            Route::post('/global-resources/verify-access', [GlobalResourceController::class, 'verifyAccess']);
            Route::get('/global-resources/operations', [GlobalResourceController::class, 'operations'])->middleware('global-resource.write');

    // Tenant Router (多租户选择 & 切换)
    Route::get('/tenants', [TenantRouterController::class, 'index']);
    Route::post('/tenants/switch', [TenantRouterController::class, 'switch']);
    Route::get('/tenants/sso-info', [TenantRouterController::class, 'ssoInfo']);

    // Update Packages (自动更新包云分发)
    Route::get('/products/{product}/updates', [UpdatePackageController::class, 'index'])->whereNumber('product');
    Route::post('/products/{product}/updates', [UpdatePackageController::class, 'store'])->whereNumber('product');
    Route::get('/products/{product}/updates/check', [UpdatePackageController::class, 'checkUpdate'])->whereNumber('product');
    Route::get('/updates/{updatePackage}', [UpdatePackageController::class, 'show'])->whereNumber('updatePackage');
    Route::post('/updates/{updatePackage}/publish', [UpdatePackageController::class, 'publish'])->whereNumber('updatePackage');
    Route::post('/updates/{updatePackage}/deprecate', [UpdatePackageController::class, 'deprecate'])->whereNumber('updatePackage');
    Route::delete('/updates/{updatePackage}', [UpdatePackageController::class, 'destroy'])->whereNumber('updatePackage');
    Route::get('/updates/{updatePackage}/stats', [UpdatePackageController::class, 'downloadStats'])->whereNumber('updatePackage');
});

// Update download (public route)
Route::get('/updates/{updatePackage}/download', [UpdatePackageController::class, 'download'])->whereNumber('updatePackage');
