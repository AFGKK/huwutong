<?php

use App\Http\Controllers\Api\ActivateController;
use App\Http\Controllers\Api\AiIntegrationWizardController;
use App\Http\Controllers\Api\ApiPlaygroundController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutoInvoiceController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\RetentionController;
use App\Http\Controllers\Api\DiagnosticController;
use App\Http\Controllers\Api\KbController;
use App\Http\Controllers\Api\RagController;
use App\Http\Controllers\Api\KbAutoGrowController;
use App\Http\Controllers\Api\DeepResearchController;
use App\Http\Controllers\Api\VectorSearchController;
use App\Http\Controllers\Api\HallucinationController;
use App\Http\Controllers\Api\ContentSignatureController;
use App\Http\Controllers\Api\ContentQualityController;
use App\Http\Controllers\Api\ElectronicSignatureController;
use App\Http\Controllers\Api\SelfLearningController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\ImEnhanceController;
use App\Http\Controllers\Api\ImIntegrationController;
use App\Http\Controllers\Api\HandoffController;
use App\Http\Controllers\Api\LiveChatController;
use App\Http\Controllers\Api\UserChatController;
use App\Http\Controllers\Api\AiFriendController;
use App\Http\Controllers\Api\StickerController;
use App\Http\Controllers\Api\EmojiController;
use App\Http\Controllers\Api\AICustomerServiceController;
use App\Http\Controllers\Api\EnterpriseAIController;
use App\Http\Controllers\Api\MediaSecurityAIController;
use App\Http\Controllers\Api\CrossSellController;
use App\Http\Controllers\Api\EmbeddedWidgetController;
use App\Http\Controllers\Api\CspViolationController;
use App\Http\Controllers\Api\FraudRiskController;
use App\Http\Controllers\Api\HoneypotController;
use App\Http\Controllers\Api\WatermarkController;
use App\Http\Controllers\Api\AutoPenTestController;
use App\Http\Controllers\Api\SyntheticMonitorController;
use App\Http\Controllers\Api\SlowQueryMonitorController;
use App\Http\Controllers\Api\SiemExportController;
use App\Http\Controllers\Api\UtmTrackerController;
use App\Http\Controllers\Api\ChaosEngineeringController;
use App\Http\Controllers\Api\CompatTestController;
use App\Http\Controllers\Api\CustomerMergeController;
use App\Http\Controllers\Api\OwnershipTransferController;
use App\Http\Controllers\Api\LicenseAnalyticsController;
use App\Http\Controllers\Api\CompliancePackController;
use App\Http\Controllers\Api\PwaController;
use App\Http\Controllers\Api\LocalLLMController;
use App\Http\Controllers\Api\UsageDashboardController;
use App\Http\Controllers\Api\CustomDomainController;
use App\Http\Controllers\Api\DomainWhitelistController;
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
use App\Http\Controllers\Api\NpsSurveyController;
use App\Http\Controllers\Api\OfflineController;
use App\Http\Controllers\Api\OpenFeatureController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SandboxController;
use App\Http\Controllers\Api\SkuController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\BillingCycleController;
use App\Http\Controllers\Api\EcommerceOpsController;
use App\Http\Controllers\Api\SdkManagerController;
use App\Http\Controllers\Api\SdkIntegrityController;
use App\Http\Controllers\Api\SdkVersionController;
use App\Http\Controllers\Api\SdkLocalCacheController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BlogCommentController;
use App\Http\Controllers\Api\PromptTemplateController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Http\Controllers\Api\StagingController;
use App\Http\Controllers\Api\StoreAffiliateController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SSOController;
use App\Http\Controllers\Api\StatusPageController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\TenantRouterController;
use App\Http\Controllers\Api\TrialController;
use App\Http\Controllers\Api\UpdatePackageController;
use App\Http\Controllers\Api\PersonalizationController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\AlertManagerController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\AutoRenewalController;
use App\Http\Controllers\Api\AlertingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\AnomalyDetectionController;
use App\Http\Controllers\Api\PaymentAdminController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\ApiDocsController;
use App\Http\Controllers\Api\AuditExportController;
use App\Http\Controllers\Api\AuditGovernanceController;
use App\Http\Controllers\Api\AuditVisualizationController;
use App\Http\Controllers\Api\AiComplianceController;
use App\Http\Controllers\Api\AdvancedSearchController;
use App\Http\Controllers\Api\MeilisearchController;
use App\Http\Controllers\Api\DomainOverviewController;
use App\Http\Controllers\Api\OpenPlatformController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MarketplacePushController;
use App\Http\Controllers\Api\MarketplaceRolloutController;
use App\Http\Controllers\Api\MarketplaceSecurityController;
use App\Http\Controllers\Api\DevPortalController;
use App\Http\Controllers\Api\SeatPoolController;
use App\Http\Controllers\Api\SlaController;
use App\Http\Controllers\Api\TenantIsolationController;
use App\Http\Controllers\Api\FineGrainedApiKeyController;
use App\Http\Controllers\Api\CustomerApiKeyController;
use App\Http\Controllers\Api\WebhookEndpointController;
use App\Http\Controllers\Api\WebhookReplayController;
use App\Http\Controllers\Api\AnnounceBannerController;
use App\Http\Controllers\Api\CookieConsentController;
use App\Http\Controllers\Api\CircuitBreakerController;
use App\Http\Controllers\Api\MaintenanceModeController;
use App\Http\Controllers\Api\CorsConfigController;
use App\Http\Controllers\Api\CspConfigController;
use App\Http\Controllers\Api\ApmController;
use App\Http\Controllers\Api\CodeSandboxController;
use App\Http\Controllers\Api\AccessibilityController;
use App\Http\Controllers\Api\A11yController;
use App\Http\Controllers\Api\AnnouncementReadController;
use App\Http\Controllers\Api\SlashCommandController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\UserAutoReplyController;
use App\Http\Controllers\Api\ImpersonateController;
use App\Http\Controllers\Api\DeviceTrustController;
use App\Http\Controllers\Api\PasswordPolicyController;
use App\Http\Controllers\Api\LegalConsentController;
use App\Http\Controllers\Api\AccountDeletionAdminController;
use App\Http\Controllers\Api\MerkleChainController;
use App\Http\Controllers\Api\UsageMeterController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\HealthScoreController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\SlaTierController;
use App\Http\Controllers\Api\TelemetryController;
use App\Http\Controllers\Api\ApiVersionController;
use App\Http\Controllers\Api\ErrorCodeController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\FilePreviewController;
use App\Http\Controllers\Api\AppealController;
use App\Http\Controllers\Api\AdminAppealController;
use App\Http\Controllers\Api\ImAdminController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\E2eeController;
use App\Http\Controllers\Api\LicenseTemplateController;
use App\Http\Controllers\Api\LicenseTemplateExtController;
use App\Http\Controllers\Api\LicenseSnapshotController;
use App\Http\Controllers\Api\LicenseTrashController;
use App\Http\Controllers\Api\BudgetGuardController;
use App\Http\Controllers\Api\SecretScanController;
use App\Http\Controllers\Api\IncidentAlertingController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ApprovalWorkflowController;
use App\Http\Controllers\Api\AdminPageDataController;

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
Route::get('/oauth/available-providers', [AuthController::class, 'availableOauthProviders']);

// Magic Link (public)
Route::post('/auth/magic-link/send', [AuthController::class, 'sendMagicLink']);
Route::get('/auth/magic-link/verify', [AuthController::class, 'verifyMagicLink'])->name('magic-link.verify');

// WebAuthn / Passkey (public)
Route::post('/auth/webauthn/login/options', [AuthController::class, 'webauthnLoginOptions']);
Route::post('/auth/webauthn/login/verify', [AuthController::class, 'webauthnLoginVerify']);

// QR Scan login (public)
Route::post('/auth/qrcode/session', [AuthController::class, 'createQrSession']);
Route::get('/auth/qrcode/session/{sessionId}', [AuthController::class, 'pollQrSession']);
Route::post('/auth/qrcode/confirm', [AuthController::class, 'confirmQrSession']);

// Legal consents (public)
Route::get('/legal-consents', [AuthController::class, 'getLegalConsents']);

// MFA aware login (public)
Route::post('/mfa/login', [MfaController::class, 'mfaLogin']);
Route::post('/mfa/check-required', [MfaController::class, 'checkRequired']);

// SSO
Route::post('/sso/callback', [SSOController::class, 'callback'])->name('sso.login');

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
Route::get('/chat-faqs', [App\Http\Controllers\Api\ChatFaqController::class, 'index']);

// Product Demo（公开）
Route::get('/products/{product}/demo', [App\Http\Controllers\Api\ProductDemoController::class, 'publicShow'])
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

// ─── 受保护 API（需认证） ───

// 嵌入式 Widget API（独立 JWT 认证）
Route::prefix('widget')->group(function () {
    Route::post('/token', [EmbeddedWidgetController::class, 'generateToken'])->middleware('auth:sanctum');
    Route::get('/data', [EmbeddedWidgetController::class, 'getWidgetData'])->middleware('widget-auth');
    Route::get('/config', [EmbeddedWidgetController::class, 'getWidgetConfig'])->middleware('widget-auth');
});

Route::middleware(['auth:sanctum', 'apm', 'tenant'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/token/refresh', [AuthController::class, 'refreshToken']);

    // Email verification
    Route::post('/email/verify/send', [AuthController::class, 'sendEmailVerification']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmail']);

    // Password management
    Route::post('/password/change', [AuthController::class, 'changePassword']);

    // WebAuthn / Passkey 管理（需认证）
    Route::post('/auth/webauthn/register/options', [AuthController::class, 'webauthnRegisterOptions']);
    Route::post('/auth/webauthn/register/verify', [AuthController::class, 'webauthnRegisterVerify']);
    Route::get('/auth/webauthn/credentials', [AuthController::class, 'webauthnCredentials']);
    Route::delete('/auth/webauthn/credentials/{id}', [AuthController::class, 'webauthnDeleteCredential']);

    // ─── 以下路由应用数据脱敏中间件 ───
    Route::middleware('mask')->group(function () {

    // Session management
    Route::get('/sessions', [AuthController::class, 'sessions']);
    Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession'])->whereNumber('tokenId');

    // Admin Session 管理
    Route::get('/admin/sessions/dashboard', [AuthController::class, 'adminSessionDashboard']);
    Route::get('/admin/sessions', [AuthController::class, 'adminSessions']);
    Route::get('/admin/sessions/{tokenId}', [AuthController::class, 'adminSessionDetail'])->whereNumber('tokenId');
    Route::post('/admin/sessions/{tokenId}/terminate', [AuthController::class, 'adminTerminateSession'])->whereNumber('tokenId');
    Route::post('/admin/sessions/batch-terminate', [AuthController::class, 'adminBatchTerminate']);
    Route::post('/admin/sessions/terminate-user/{userId}', [AuthController::class, 'adminTerminateUserSessions'])->whereNumber('userId');

    // Device trust
    Route::post('/devices/trust', [AuthController::class, 'trustDevice']);
    Route::get('/devices/trusted', [AuthController::class, 'trustedDevices']);
    Route::delete('/devices/trusted/{deviceId}', [AuthController::class, 'removeTrustedDevice'])->whereNumber('deviceId');
    Route::delete('/devices/trusted', [AuthController::class, 'clearTrustedDevices']);
    Route::post('/devices/check', [AuthController::class, 'checkDevice']);

    // Password policy & account lock management
    Route::get('/password-policy/config', [PasswordPolicyController::class, 'getConfig']);
    Route::put('/password-policy/config', [PasswordPolicyController::class, 'updateConfig']);
    Route::get('/password-policy/locked-accounts', [PasswordPolicyController::class, 'lockedAccounts']);
    Route::post('/password-policy/unlock', [PasswordPolicyController::class, 'unlockAccount']);

    // Legal consent management (admin)
    Route::get('/legal-consents', [LegalConsentController::class, 'index']);
    Route::post('/legal-consents', [LegalConsentController::class, 'store']);
    Route::get('/legal-consents/{legalConsent}', [LegalConsentController::class, 'show'])->whereNumber('legalConsent');
    Route::put('/legal-consents/{legalConsent}', [LegalConsentController::class, 'update'])->whereNumber('legalConsent');
    Route::post('/legal-consents/{legalConsent}/publish', [LegalConsentController::class, 'publish'])->whereNumber('legalConsent');
    Route::get('/legal-consents/logs', [LegalConsentController::class, 'consentLogs']);

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

    // 账号申诉状态查询
    Route::get('/appeal/status', [AppealController::class, 'status']);

    // Account deletion admin
    Route::get('/account/deletions/pending', [AccountDeletionAdminController::class, 'pending']);
    Route::get('/account/deletions/history', [AccountDeletionAdminController::class, 'history']);
    Route::post('/account/deletions/approve', [AccountDeletionAdminController::class, 'approve']);
    Route::post('/account/deletions/reject', [AccountDeletionAdminController::class, 'reject']);
    Route::get('/account/deletions/stats', [AccountDeletionAdminController::class, 'stats']);

    // ── Merkle 审计链验证 ──
    Route::get('/merkle/stats', [MerkleChainController::class, 'stats']);
    Route::get('/merkle/verify', [MerkleChainController::class, 'verify']);
    Route::get('/merkle/verify/{logId}', [MerkleChainController::class, 'verify']);
    Route::post('/merkle/anchor', [MerkleChainController::class, 'anchor']);
    Route::get('/merkle/anchors', [MerkleChainController::class, 'anchors']);
    Route::post('/merkle/backfill', [MerkleChainController::class, 'backfill']);

    // OAuth binding
    Route::get('/oauth/providers', [AuthController::class, 'boundProviders']);
    Route::post('/oauth/bind', [AuthController::class, 'bindOAuth']);
    Route::delete('/oauth/unbind/{authProviderId}', [AuthController::class, 'unbindOAuth'])->whereNumber('authProviderId');

    // 头像上传
    Route::post('/avatar/upload', [AuthController::class, 'uploadAvatar']);
    Route::delete('/avatar', [AuthController::class, 'deleteAvatar']);

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

    // 🆕 域名白名单验证 (M2-71)
    Route::prefix('admin/domain-whitelist')->group(function () {
        Route::post('/verify', [DomainWhitelistController::class, 'verify']);
        Route::get('/approvals/pending', [DomainWhitelistController::class, 'pendingApprovals']);
        Route::post('/approvals/{id}/approve', [DomainWhitelistController::class, 'approve'])->whereNumber('id');
        Route::post('/approvals/{id}/reject', [DomainWhitelistController::class, 'reject'])->whereNumber('id');
    });
    Route::prefix('admin/licenses/{license}/domain-whitelist')->whereNumber('license')->group(function () {
        Route::get('/', [DomainWhitelistController::class, 'index']);
        Route::post('/', [DomainWhitelistController::class, 'store']);
        Route::post('/batch', [DomainWhitelistController::class, 'batchStore']);
        Route::delete('/{id}', [DomainWhitelistController::class, 'destroy'])->whereNumber('id');
        Route::get('/logs', [DomainWhitelistController::class, 'logs']);
        Route::get('/stats', [DomainWhitelistController::class, 'stats']);
    });

    // License management
    Route::get('/licenses', [LicenseController::class, 'index']);
    Route::post('/licenses', [LicenseController::class, 'store']);
    Route::get('/licenses/{license}', [LicenseController::class, 'show'])->whereNumber('license');
    Route::put('/licenses/{license}', [LicenseController::class, 'update'])->whereNumber('license');
    Route::delete('/licenses/{license}', [LicenseController::class, 'destroy'])->whereNumber('license');
    Route::post('/licenses/batch', [LicenseController::class, 'batchStore']);
    Route::post('/licenses/batch/operation', [LicenseController::class, 'batchOperation']);
    Route::get('/licenses/export', [LicenseController::class, 'export']);
    Route::post('/licenses/lookup', [LicenseController::class, 'lookup']);
    Route::post('/licenses/{license}/restore', [LicenseController::class, 'restoreFromTrash'])->whereNumber('license');
    Route::get('/licenses/stats', [LicenseController::class, 'stats']);

    // License 变更审批 (M2-11)
    Route::get('/licenses/approvals/dashboard', [ApprovalWorkflowController::class, 'dashboard']);
    Route::get('/licenses/approvals/check/requires', [ApprovalWorkflowController::class, 'check']);
    Route::get('/licenses/approvals', [ApprovalWorkflowController::class, 'index']);
    Route::post('/licenses/approvals', [ApprovalWorkflowController::class, 'store']);
    Route::get('/licenses/approvals/{id}', [ApprovalWorkflowController::class, 'show'])->whereNumber('id');
    Route::post('/licenses/approvals/{id}/approve', [ApprovalWorkflowController::class, 'approve'])->whereNumber('id');
    Route::post('/licenses/approvals/{id}/reject', [ApprovalWorkflowController::class, 'reject'])->whereNumber('id');
    Route::post('/licenses/approvals/{id}/cancel', [ApprovalWorkflowController::class, 'cancel'])->whereNumber('id');

    // License Seat Pool 操作（委托 SeatPoolController）
    Route::get('/licenses/{license}/pool/status', [SeatPoolController::class, 'licensePoolStatus'])->whereNumber('license');
    Route::get('/licenses/{license}/pool/assignments', [SeatPoolController::class, 'licenseAssignments'])->whereNumber('license');
    Route::get('/licenses/{license}/pool/queue', [SeatPoolController::class, 'licenseQueue'])->whereNumber('license');
    Route::post('/licenses/{license}/pool/assign', [SeatPoolController::class, 'licenseAssign'])->whereNumber('license');
    Route::post('/licenses/{license}/pool/release', [SeatPoolController::class, 'licenseRelease'])->whereNumber('license');
    Route::post('/licenses/{license}/pool/heartbeat', [SeatPoolController::class, 'licenseHeartbeat'])->whereNumber('license');
    Route::post('/licenses/{license}/pool/cancel-queue', [SeatPoolController::class, 'licenseCancelQueue'])->whereNumber('license');
    Route::get('/licenses/{license}/pool/config', [SeatPoolController::class, 'licensePoolConfig'])->whereNumber('license');
    Route::post('/licenses/pool/batch-release-expired', [SeatPoolController::class, 'batchReleaseExpired']);

    // Product management
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product');
    Route::put('/products/{product}', [ProductController::class, 'update'])->whereNumber('product');
    Route::get('/products/stats', [ProductController::class, 'stats']);
    Route::post('/products/upload-image', [ProductController::class, 'uploadImage']);
    Route::post('/products/batch-action', [ProductController::class, 'batchAction']);
    Route::post('/products/{product}/clone', [ProductController::class, 'clone'])->whereNumber('product');
    // Product SKUs
    Route::get('/products/{product}/skus', [OrderController::class, 'skus'])->whereNumber('product');
    Route::post('/products/{product}/skus', [OrderController::class, 'storeSku'])->whereNumber('product');
    Route::put('/skus/{id}', [OrderController::class, 'updateSku'])->whereNumber('id');
    Route::delete('/skus/{id}', [OrderController::class, 'destroySku'])->whereNumber('id');
    // 🆕 SKU 管理后台独立路由（供 product-sku 页面使用）
    Route::prefix('admin/product-skus')->group(function () {
        Route::get('/dashboard', [SkuController::class, 'dashboard']);
        Route::get('/', [SkuController::class, 'index']);
        Route::get('/{id}', [SkuController::class, 'show'])->whereNumber('id');
        Route::post('/', [SkuController::class, 'store']);
        Route::put('/{id}', [SkuController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [SkuController::class, 'destroy'])->whereNumber('id');
        Route::post('/{id}/toggle', [SkuController::class, 'toggle'])->whereNumber('id');
        Route::post('/{id}/clone', [SkuController::class, 'clone'])->whereNumber('id');
        Route::post('/{id}/adjust-stock', [SkuController::class, 'adjustStock'])->whereNumber('id');
        Route::get('/{id}/stock-logs', [SkuController::class, 'stockLogs'])->whereNumber('id');
        Route::get('/{id}/currency-prices', [SkuController::class, 'currencyPrices'])->whereNumber('id');
        Route::post('/{id}/currency-prices', [SkuController::class, 'saveCurrencyPrices'])->whereNumber('id');
        Route::post('/batch-stock', [SkuController::class, 'batchStock']);
        Route::post('/batch-action', [SkuController::class, 'batchAction']);
        Route::post('/upload-deliverable', [SkuController::class, 'uploadDeliverable']);
        Route::post('/upload-image', [SkuController::class, 'uploadImage']);
        Route::get('/export/csv', [SkuController::class, 'export']);
        Route::post('/import/csv', [SkuController::class, 'import']);
        Route::get('/low-stock', [SkuController::class, 'lowStockList']);
    });

    // 🆕 库存管理（电商运营专用路由）
    Route::prefix('ecommerce/inventory')->group(function () {
        Route::get('/snapshot', [InventoryController::class, 'snapshot']);
        Route::get('/alerts', [InventoryController::class, 'alerts']);
        Route::get('/logs/{skuId}', [InventoryController::class, 'logs'])->whereNumber('skuId');
        Route::post('/{skuId}/adjust', [InventoryController::class, 'adjust'])->whereNumber('skuId');
        Route::post('/{skuId}/initialize', [InventoryController::class, 'initialize'])->whereNumber('skuId');
    });

    // 🆕 支付安全 (M2-153)
    Route::prefix('ecommerce/security')->group(function () {
        Route::get('/stats', [EcommerceOpsController::class, 'securityStats']);
        Route::get('/logs', [EcommerceOpsController::class, 'securityLogs']);
        Route::post('/pre-check', [EcommerceOpsController::class, 'prePaymentCheck']);
    });

    // 🆕 计费周期管理
    Route::prefix('admin/billing-cycles')->group(function () {
        Route::get('/', [BillingCycleController::class, 'index']);
        Route::post('/', [BillingCycleController::class, 'store']);
        Route::put('/{id}', [BillingCycleController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [BillingCycleController::class, 'destroy'])->whereNumber('id');
    });
    Route::get('/billing-cycles/options', [BillingCycleController::class, 'options']);
    // Product Specs
    Route::post('/products/{product}/specs', [ProductController::class, 'saveSpecs'])->whereNumber('product');
    Route::get('/products/{product}/specs', [ProductController::class, 'getSpecs'])->whereNumber('product');
    // Product SEO
    Route::get('/products/{product}/seo', [ProductController::class, 'getSeo'])->whereNumber('product');
    Route::post('/products/{product}/seo', [ProductController::class, 'saveSeo'])->whereNumber('product');
    // Product Translations
    Route::post('/products/{product}/translations', [ProductController::class, 'saveTranslations'])->whereNumber('product');
    Route::get('/products/{product}/features', [ProductController::class, 'features'])->whereNumber('product');
    Route::post('/products/{product}/features', [ProductController::class, 'assignFeature'])->whereNumber('product');
    Route::get('/products/{product}/licenses', [ProductController::class, 'licenses'])->whereNumber('product');
    // Product Demos
    Route::get('/products/{product}/demos', [App\Http\Controllers\Api\ProductDemoController::class, 'index'])->whereNumber('product');
    Route::post('/products/{product}/demos', [App\Http\Controllers\Api\ProductDemoController::class, 'store'])->whereNumber('product');
    Route::put('/products/demos/{demo}', [App\Http\Controllers\Api\ProductDemoController::class, 'update'])->whereNumber('demo');
    Route::delete('/products/demos/{demo}', [App\Http\Controllers\Api\ProductDemoController::class, 'destroy'])->whereNumber('demo');
    Route::post('/products/{product}/demos/settings', [App\Http\Controllers\Api\ProductDemoController::class, 'updateSettings'])->whereNumber('product');

    // Product categories
    Route::get('/product-categories', [ProductCategoryController::class, 'index']);
    Route::get('/product-categories/tree', [ProductCategoryController::class, 'tree']);
    Route::get('/product-categories/options', [ProductCategoryController::class, 'options']);
    Route::get('/product-categories/{productCategory}', [ProductCategoryController::class, 'show'])->whereNumber('productCategory');
    Route::get('/product-categories/{productCategory}/products', [ProductCategoryController::class, 'products'])->whereNumber('productCategory');
    Route::post('/product-categories', [ProductCategoryController::class, 'store']);
    Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->whereNumber('productCategory');
    Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->whereNumber('productCategory');
    Route::post('/product-categories/reorder', [ProductCategoryController::class, 'reorder']);
    // 🆕 批量操作
    Route::post('/product-categories/batch/toggle', [ProductCategoryController::class, 'batchToggle']);
    Route::post('/product-categories/batch/delete', [ProductCategoryController::class, 'batchDelete']);
    // 🆕 移动/路径/统计/合并
    Route::put('/product-categories/{productCategory}/move', [ProductCategoryController::class, 'move'])->whereNumber('productCategory');
    Route::get('/product-categories/{productCategory}/path', [ProductCategoryController::class, 'path'])->whereNumber('productCategory');
    Route::get('/product-categories/stats/data', [ProductCategoryController::class, 'stats']);
    Route::post('/product-categories/merge', [ProductCategoryController::class, 'merge']);
    // 🆕 导入导出
    Route::get('/product-categories/export/csv', [ProductCategoryController::class, 'export']);
    Route::post('/product-categories/import/csv', [ProductCategoryController::class, 'import']);

    // Store Affiliate (分销联盟)
    Route::get('/store-affiliate/dashboard', [StoreAffiliateController::class, 'dashboard']);
    Route::get('/store-affiliate/campaigns', [StoreAffiliateController::class, 'campaigns']);
    Route::post('/store-affiliate/campaigns', [StoreAffiliateController::class, 'storeCampaign']);
    Route::put('/store-affiliate/campaigns/{campaign}', [StoreAffiliateController::class, 'updateCampaign']);
    Route::delete('/store-affiliate/campaigns/{campaign}', [StoreAffiliateController::class, 'destroyCampaign']);
    Route::get('/store-affiliate/agents/{agent}/summary', [StoreAffiliateController::class, 'agentSummary']);
    Route::get('/store-affiliate/agents/{agent}/upline', [StoreAffiliateController::class, 'upline']);
    Route::get('/store-affiliate/agents/{agent}/downline', [StoreAffiliateController::class, 'downline']);
    Route::post('/store-affiliate/tree', [StoreAffiliateController::class, 'buildTree']);
    Route::post('/store-affiliate/campaigns/{campaign}/deposit', [StoreAffiliateController::class, 'depositBudget']);
    Route::get('/store-affiliate/campaigns/{campaign}/creatives', [StoreAffiliateController::class, 'creatives']);
    Route::post('/store-affiliate/campaigns/{campaign}/creatives', [StoreAffiliateController::class, 'storeCreative']);
    Route::put('/store-affiliate/campaigns/{campaign}/creatives/{creative}', [StoreAffiliateController::class, 'updateCreative']);
    Route::delete('/store-affiliate/campaigns/{campaign}/creatives/{creative}', [StoreAffiliateController::class, 'destroyCreative']);
    Route::get('/store-affiliate/campaigns/{campaign}/creative-stats', [StoreAffiliateController::class, 'creativeStats']);
    Route::get('/store-affiliate/clicks', [StoreAffiliateController::class, 'clickLogs']);
    Route::get('/store-affiliate/promotable-skus', [StoreAffiliateController::class, 'promotableSkus']);
    Route::post('/store-affiliate/generate-links', [StoreAffiliateController::class, 'generateLink']);
    Route::post('/store-affiliate/link-order', [StoreAffiliateController::class, 'linkOrder']);
    Route::post('/store-affiliate/{orderId}/settle-commission', [StoreAffiliateController::class, 'settleCommission'])->whereNumber('orderId');
    Route::get('/store-affiliate/orders', [StoreAffiliateController::class, 'orders']);
    Route::get('/store-affiliate/links', [StoreAffiliateController::class, 'links']);
    Route::get('/store-affiliate/campaigns/{campaign}/my-link', [StoreAffiliateController::class, 'myCampaignLink']);

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
    // Device 生命周期
    Route::get('/devices/{device}/profile', [DeviceController::class, 'profile'])->whereNumber('device');
    Route::get('/devices/{device}/timeline', [DeviceController::class, 'timeline'])->whereNumber('device');
    Route::get('/devices/{device}/lifecycle-events', [DeviceController::class, 'lifecycleEvents'])->whereNumber('device');
    Route::post('/devices/{device}/adjust-trust', [DeviceController::class, 'adjustTrustScore'])->whereNumber('device');
    Route::post('/devices/{device}/mark-suspicious', [DeviceController::class, 'markSuspicious'])->whereNumber('device');
    Route::post('/devices/{device}/retire', [DeviceController::class, 'retireDevice'])->whereNumber('device');
    Route::get('/devices/profile-stats', [DeviceController::class, 'profileStats']);

    // ══════════════════════════════════════════
    //  RBAC 权限管理 (P3 增强)
    // ══════════════════════════════════════════

    // ── 角色 CRUD ──
    Route::get('/roles', [PermissionController::class, 'roles']);
    Route::post('/roles', [PermissionController::class, 'roleStore']);
    Route::get('/roles/{role}', [PermissionController::class, 'roleShow'])->whereNumber('role');
    Route::put('/roles/{role}', [PermissionController::class, 'roleUpdate'])->whereNumber('role');
    Route::delete('/roles/{role}', [PermissionController::class, 'roleDestroy'])->whereNumber('role');
    Route::post('/roles/{role}/duplicate', [PermissionController::class, 'roleDuplicate'])->whereNumber('role');

    // ── 角色层级 ──
    Route::get('/roles/hierarchy', [PermissionController::class, 'roleHierarchy']);

    // ── 角色模板 ──
    Route::get('/role-templates', [PermissionController::class, 'roleTemplates']);
    Route::post('/role-templates', [PermissionController::class, 'templateStore']);
    Route::post('/role-templates/{template}/create-role', [PermissionController::class, 'roleFromTemplate'])->whereNumber('template');
    Route::delete('/role-templates/{template}', [PermissionController::class, 'templateDestroy'])->whereNumber('template');
    Route::post('/role-templates/seed', [PermissionController::class, 'seedTemplates']);

    // ── 权限 ──
    Route::get('/permissions', [PermissionController::class, 'allPermissions']);
    Route::get('/permissions/mine', [PermissionController::class, 'myPermissions']);
    Route::post('/permissions', [PermissionController::class, 'permissionStore']);
    Route::post('/permissions/batch', [PermissionController::class, 'permissionBatchStore']);
    Route::delete('/permissions/{permission}', [PermissionController::class, 'permissionDestroy'])->whereNumber('permission');

    // ── 用户角色分配 ──
    Route::get('/users/with-roles', [PermissionController::class, 'tenantUsers']);
    Route::get('/users/{user}/roles', [PermissionController::class, 'userRoles'])->whereNumber('user');
    Route::post('/users/{user}/roles', [PermissionController::class, 'assignRoles'])->whereNumber('user');

    // ── 用户直接权限 ──
    Route::get('/users/{user}/direct-permissions', [PermissionController::class, 'userDirectPermissions'])->whereNumber('user');
    Route::put('/users/{user}/direct-permissions', [PermissionController::class, 'assignUserDirectPermissions'])->whereNumber('user');

    // ── 权限审计日志 ──
    Route::get('/permission-audit-logs', [PermissionController::class, 'auditLogs']);
    Route::get('/permission-audit-logs/stats', [PermissionController::class, 'auditStats']);

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
    Route::post('/feature-flags', [FeatureFlagController::class, 'store']);
    Route::put('/feature-flags/{id}', [FeatureFlagController::class, 'update']);
    Route::patch('/feature-flags/{id}', [FeatureFlagController::class, 'toggle']);
    Route::delete('/feature-flags/{id}', [FeatureFlagController::class, 'destroy']);
    Route::get('/feature-flags/assignments', [FeatureFlagController::class, 'assignments']);
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

    // Webhook endpoints management
    Route::get('/webhook-endpoints/event-types', [WebhookEndpointController::class, 'eventTypes']);
    Route::get('/webhook-endpoints', [WebhookEndpointController::class, 'index']);
    Route::post('/webhook-endpoints', [WebhookEndpointController::class, 'store']);
    Route::get('/webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'show'])->whereNumber('endpoint');
    Route::put('/webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'update'])->whereNumber('endpoint');
    Route::delete('/webhook-endpoints/{endpoint}', [WebhookEndpointController::class, 'destroy'])->whereNumber('endpoint');
    Route::post('/webhook-endpoints/{endpoint}/toggle-pause', [WebhookEndpointController::class, 'togglePause'])->whereNumber('endpoint');
    Route::post('/webhook-endpoints/{endpoint}/test', [WebhookEndpointController::class, 'test'])->whereNumber('endpoint');

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

    // NPS 满意度调查 (M2-59)
    Route::prefix('nps-survey')->group(function () {
        Route::get('/dashboard', [NpsSurveyController::class, 'dashboard']);
        Route::get('/report', [NpsSurveyController::class, 'report']);
        Route::get('/surveys', [NpsSurveyController::class, 'surveys']);
        Route::get('/responses', [NpsSurveyController::class, 'responses']);
        Route::post('/send', [NpsSurveyController::class, 'sendSurvey']);
        Route::get('/trend', [NpsSurveyController::class, 'trend']);
        Route::post('/generate-snapshot', [NpsSurveyController::class, 'generateSnapshot']);
        Route::get('/eligible-users', [NpsSurveyController::class, 'eligibleUsers']);
        Route::get('/config', [NpsSurveyController::class, 'config']);
    });

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

    // ── 支付方式 ──
    Route::get('/billing/payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('/billing/payment-methods', [PaymentMethodController::class, 'store']);
    Route::post('/billing/payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault'])->whereNumber('paymentMethod');
    Route::delete('/billing/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->whereNumber('paymentMethod');

    // ── 支付管理 (PaymentAdmin) ──
    Route::get('/payment/stats', [PaymentAdminController::class, 'stats']);
    Route::get('/payment/transactions', [PaymentAdminController::class, 'transactions']);
    Route::get('/payment/transactions/{id}', [PaymentAdminController::class, 'transactionDetail'])->whereNumber('id');
    Route::get('/payment/gateway-config', [PaymentAdminController::class, 'gatewayConfig']);
    Route::post('/payment/switch-driver', [PaymentAdminController::class, 'switchDriver']);
    Route::get('/payment/webhook-logs', [PaymentAdminController::class, 'webhookLogs']);

    // ── 支付记录 (Payment) ──
    Route::get('/payments/dashboard', [PaymentController::class, 'dashboard']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment');
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->whereNumber('payment');
    Route::get('/payments/trend/data', [PaymentController::class, 'trend']);

    // ══════════════════════════════════════════
    //  财务结算系统 (P3)
    // ══════════════════════════════════════════

    // ── 结算仪表盘 ──
    Route::get('/settlement/dashboard', [SettlementController::class, 'dashboard']);

    // ── 结算周期 ──
    Route::get('/settlement/cycles', [SettlementController::class, 'cycles']);
    Route::post('/settlement/cycles', [SettlementController::class, 'cycleStore']);
    Route::get('/settlement/cycles/{cycle}', [SettlementController::class, 'cycleShow'])->whereNumber('cycle');
    Route::post('/settlement/cycles/generate', [SettlementController::class, 'cycleGenerate']);

    // ── 可结算佣金扫描 ──
    Route::get('/settlement/releasable', [SettlementController::class, 'scanReleasable']);

    // ── 结算批次 ──
    Route::get('/settlement/batches', [SettlementController::class, 'batches']);
    Route::post('/settlement/batches', [SettlementController::class, 'batchStore']);
    Route::get('/settlement/batches/{batch}', [SettlementController::class, 'batchShow'])->whereNumber('batch');
    Route::post('/settlement/batches/{batch}/submit', [SettlementController::class, 'batchSubmit'])->whereNumber('batch');
    Route::post('/settlement/batches/{batch}/approve', [SettlementController::class, 'batchApprove'])->whereNumber('batch');
    Route::post('/settlement/batches/{batch}/complete', [SettlementController::class, 'batchComplete'])->whereNumber('batch');
    Route::post('/settlement/batches/{batch}/cancel', [SettlementController::class, 'batchCancel'])->whereNumber('batch');

    // ── 平台费用 ──
    Route::get('/settlement/fees', [SettlementController::class, 'feeStats']);

    // ── 开放平台 (OpenPlatform) ──
    Route::get('/open-platform/stats', [OpenPlatformController::class, 'stats']);
    Route::get('/open-platform/metadata', [OpenPlatformController::class, 'metadata']);
    Route::get('/open-platform/developers', [OpenPlatformController::class, 'developers']);
    Route::post('/open-platform/developers/{developer}/verify', [OpenPlatformController::class, 'verifyDeveloper']);
    Route::get('/open-platform/apps', [OpenPlatformController::class, 'apps']);
    Route::get('/open-platform/apps/pending', [OpenPlatformController::class, 'pendingApps']);
    Route::get('/open-platform/apps/{app}', [OpenPlatformController::class, 'showApp']);
    Route::post('/open-platform/apps/{app}/review', [OpenPlatformController::class, 'reviewApp']);
    Route::post('/open-platform/apps/{app}/suspend', [OpenPlatformController::class, 'suspendApp'])->whereNumber('app');
    Route::post('/open-platform/apps/{app}/unsuspend', [OpenPlatformController::class, 'unsuspendApp'])->whereNumber('app');
    Route::post('/open-platform/apps/{app}/force-update', [OpenPlatformController::class, 'forceUpdate'])->whereNumber('app');
    Route::get('/open-platform/installations', [OpenPlatformController::class, 'installations']);
    Route::post('/open-platform/developer/register', [OpenPlatformController::class, 'registerDeveloper']);
    Route::get('/open-platform/developer/me', [OpenPlatformController::class, 'myDeveloper']);
    Route::get('/open-platform/my/apps', [OpenPlatformController::class, 'myApps']);
    Route::post('/open-platform/my/apps', [OpenPlatformController::class, 'createApp']);
    Route::put('/open-platform/my/apps/{app}', [OpenPlatformController::class, 'updateApp']);
    Route::post('/open-platform/my/apps/{app}/submit', [OpenPlatformController::class, 'submitApp']);
    Route::post('/open-platform/my/apps/{app}/versions', [OpenPlatformController::class, 'addVersion']);
    Route::get('/open-platform/marketplace', [OpenPlatformController::class, 'marketplace']);
    Route::post('/open-platform/marketplace/{app}/install', [OpenPlatformController::class, 'installApp']);
    Route::delete('/open-platform/marketplace/{installation}', [OpenPlatformController::class, 'uninstallApp']);
    Route::get('/open-platform/my/installations', [OpenPlatformController::class, 'myInstallations']);

    // ── 排行榜 & 趋势 & 更新检测 ──
    Route::get('/open-platform/rankings', [OpenPlatformController::class, 'rankings']);
    Route::get('/open-platform/download-trend', [OpenPlatformController::class, 'downloadTrend']);
    Route::get('/open-platform/apps/{app}/download-trend', [OpenPlatformController::class, 'appDownloadTrend'])->whereNumber('app');
    Route::post('/open-platform/apps/{app}/check-update', [OpenPlatformController::class, 'checkUpdate'])->whereNumber('app');
    Route::get('/open-platform/summary', [OpenPlatformController::class, 'summary']);
    Route::post('/open-platform/upload/package', [OpenPlatformController::class, 'uploadPackage']);
    Route::post('/open-platform/upload/screenshot', [OpenPlatformController::class, 'uploadScreenshot']);

    // ── 开发者收益 ──
    Route::post('/open-platform/earnings/init', [OpenPlatformController::class, 'initEarnings']);
    Route::get('/open-platform/earnings/my', [OpenPlatformController::class, 'myEarnings']);
    Route::get('/open-platform/earnings/developers/{developer}', [OpenPlatformController::class, 'developerEarnings'])->whereNumber('developer');
    Route::post('/open-platform/earnings/withdraw', [OpenPlatformController::class, 'requestWithdrawal']);
    Route::get('/open-platform/earnings/withdrawals', [OpenPlatformController::class, 'myWithdrawals']);
    Route::put('/open-platform/earnings/tax-info', [OpenPlatformController::class, 'updateTaxInfo']);
    Route::get('/open-platform/earnings/financial-dashboard', [OpenPlatformController::class, 'financialDashboard']);

    // ── 应用市场增强 (Marketplace Reviews/Banners/Categories) ──
    Route::get('/marketplace/categories', [MarketplaceController::class, 'categories']);
    Route::post('/marketplace/categories', [MarketplaceController::class, 'categoryStore']);
    Route::put('/marketplace/categories/{category}', [MarketplaceController::class, 'categoryUpdate'])->whereNumber('category');
    Route::delete('/marketplace/categories/{category}', [MarketplaceController::class, 'categoryDestroy'])->whereNumber('category');

    Route::get('/marketplace/apps/{app}/reviews', [MarketplaceController::class, 'reviews'])->whereNumber('app');
    Route::post('/marketplace/apps/{app}/reviews', [MarketplaceController::class, 'reviewStore'])->whereNumber('app');
    Route::get('/marketplace/apps/{app}/review-stats', [MarketplaceController::class, 'reviewStats'])->whereNumber('app');
    Route::put('/marketplace/reviews/{review}', [MarketplaceController::class, 'reviewUpdate'])->whereNumber('review');
    Route::delete('/marketplace/reviews/{review}', [MarketplaceController::class, 'reviewDestroy'])->whereNumber('review');
    Route::post('/marketplace/reviews/{review}/reply', [MarketplaceController::class, 'reviewReply'])->whereNumber('review');
    Route::post('/marketplace/reviews/{review}/moderate', [MarketplaceController::class, 'reviewModerate'])->whereNumber('review');

    Route::get('/marketplace/banners', [MarketplaceController::class, 'banners']);
    Route::get('/marketplace/banners/manage', [MarketplaceController::class, 'bannersAdmin']);
    Route::post('/marketplace/banners', [MarketplaceController::class, 'bannerStore']);
    Route::put('/marketplace/banners/{banner}', [MarketplaceController::class, 'bannerUpdate'])->whereNumber('banner');
    Route::delete('/marketplace/banners/{banner}', [MarketplaceController::class, 'bannerDestroy'])->whereNumber('banner');

    Route::get('/marketplace/apps/{app}/analytics', [MarketplaceController::class, 'analytics'])->whereNumber('app');

    // ── 市场推送 (Marketplace Push) ──
    Route::get('/marketplace/push/campaigns', [MarketplacePushController::class, 'index']);
    Route::post('/marketplace/push/campaigns', [MarketplacePushController::class, 'store']);
    Route::get('/marketplace/push/campaigns/{campaign}', [MarketplacePushController::class, 'show'])->whereNumber('campaign');
    Route::put('/marketplace/push/campaigns/{campaign}', [MarketplacePushController::class, 'update'])->whereNumber('campaign');
    Route::post('/marketplace/push/campaigns/{campaign}/send', [MarketplacePushController::class, 'send'])->whereNumber('campaign');
    Route::post('/marketplace/push/campaigns/{campaign}/cancel', [MarketplacePushController::class, 'cancel'])->whereNumber('campaign');
    Route::delete('/marketplace/push/campaigns/{campaign}', [MarketplacePushController::class, 'destroy'])->whereNumber('campaign');
    Route::get('/marketplace/push/stats', [MarketplacePushController::class, 'stats']);

    // ── 灰度发布 (Staged Rollout) ──
    Route::get('/marketplace/rollouts', [MarketplaceRolloutController::class, 'index']);
    Route::post('/marketplace/rollouts', [MarketplaceRolloutController::class, 'store']);
    Route::get('/marketplace/rollouts/available-apps', [MarketplaceRolloutController::class, 'availableApps']);
    Route::get('/marketplace/rollouts/available-tenants', [MarketplaceRolloutController::class, 'availableTenants']);
    Route::get('/marketplace/rollouts/{rollout}', [MarketplaceRolloutController::class, 'show'])->whereNumber('rollout');
    Route::put('/marketplace/rollouts/{rollout}', [MarketplaceRolloutController::class, 'update'])->whereNumber('rollout');
    Route::post('/marketplace/rollouts/{rollout}/start', [MarketplaceRolloutController::class, 'start'])->whereNumber('rollout');
    Route::post('/marketplace/rollouts/{rollout}/pause', [MarketplaceRolloutController::class, 'pause'])->whereNumber('rollout');
    Route::post('/marketplace/rollouts/{rollout}/complete', [MarketplaceRolloutController::class, 'complete'])->whereNumber('rollout');
    Route::post('/marketplace/rollouts/{rollout}/rollback', [MarketplaceRolloutController::class, 'rollback'])->whereNumber('rollout');
    Route::get('/marketplace/rollouts/{rollout}/stats', [MarketplaceRolloutController::class, 'stats'])->whereNumber('rollout');

    // ── 市场内容安全 (Marketplace Security) ──
    Route::get('/marketplace/security/stats', [MarketplaceSecurityController::class, 'stats']);
    Route::get('/marketplace/security/apps/{app}', [MarketplaceSecurityController::class, 'scanApp'])->whereNumber('app');
    Route::get('/marketplace/security/reviews/{review}', [MarketplaceSecurityController::class, 'scanReview'])->whereNumber('review');
    Route::post('/marketplace/security/scan-apps', [MarketplaceSecurityController::class, 'scanAllApps']);
    Route::post('/marketplace/security/scan-reviews', [MarketplaceSecurityController::class, 'scanAllReviews']);

    // ── 开发者门户 (DevPortal) ──
    Route::get('/dev-portal/dashboard', [DevPortalController::class, 'dashboard'])->withoutMiddleware(['auth:sanctum']);
    Route::get('/dev-portal/public', [DevPortalController::class, 'publicData'])->withoutMiddleware(['auth:sanctum']);
    Route::get('/dev-portal/sdks', [DevPortalController::class, 'sdks'])->withoutMiddleware(['auth:sanctum']);
    Route::get('/dev-portal/quickstart-steps', [DevPortalController::class, 'quickstartSteps'])->withoutMiddleware(['auth:sanctum']);

    // ── 开发者门户 (DevPortal) 管理端 ──
    Route::get('/admin/dev-portal/dashboard', [DevPortalController::class, 'dashboard']);
    Route::get('/admin/dev-portal/sdks', [DevPortalController::class, 'sdks']);
    Route::get('/admin/dev-portal/quickstart-steps', [DevPortalController::class, 'quickstartSteps']);

    // ── 支付回调 (PaymentCallback) ──
    Route::get('/payment-callbacks/stats', [PaymentCallbackController::class, 'stats']);
    Route::get('/payment-callbacks', [PaymentCallbackController::class, 'index']);
    Route::post('/payment-callbacks/{id}/retry', [PaymentCallbackController::class, 'retry'])->whereNumber('id');
    Route::post('/payment-callbacks/batch-retry', [PaymentCallbackController::class, 'batchRetry']);
    Route::post('/payment-callbacks/simulate', [PaymentCallbackController::class, 'simulate']);

    // ── 自动续费状态 ──
    Route::get('/billing/auto-renewal', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        return \App\Http\ApiResponse::success([
            'enabled' => \App\Models\AutoRenewalSubscription::where('tenant_id', $user->tenant_id)
                ->where('status', 'active')
                ->exists(),
        ]);
    });
    Route::post('/billing/auto-renewal', function (\Illuminate\Http\Request $request) {
        $data = $request->validate(['enabled' => 'required|boolean']);
        return \App\Http\ApiResponse::success(['enabled' => $data['enabled']], $data['enabled'] ? '自动续费已开启' : '自动续费已关闭');
    });

    // ── 自助发票 (AutoInvoice) ──
    Route::get('/auto-invoice/stats', [AutoInvoiceController::class, 'stats']);
    Route::get('/auto-invoice', [AutoInvoiceController::class, 'index']);
    Route::get('/auto-invoice/{invoice}', [AutoInvoiceController::class, 'show'])->whereNumber('invoice');
    Route::get('/auto-invoice/{invoice}/preview', [AutoInvoiceController::class, 'preview'])->whereNumber('invoice');
    Route::post('/auto-invoice/{order}/generate', [AutoInvoiceController::class, 'generate'])->whereNumber('order');
    Route::post('/auto-invoice/{invoice}/resend', [AutoInvoiceController::class, 'resend'])->whereNumber('invoice');
    // 发票抬头
    Route::get('/auto-invoice/titles/list', [AutoInvoiceController::class, 'titles']);
    Route::post('/auto-invoice/titles', [AutoInvoiceController::class, 'storeTitle']);
    Route::put('/auto-invoice/titles/{title}', [AutoInvoiceController::class, 'updateTitle'])->whereNumber('title');
    Route::delete('/auto-invoice/titles/{title}', [AutoInvoiceController::class, 'destroyTitle'])->whereNumber('title');

    // Billing 订阅/方案/优惠券管理
    Route::post('/billing/subscriptions/{subscription}/suspend', [BillingController::class, 'suspend'])->whereNumber('subscription');
    Route::get('/billing/plans', [BillingController::class, 'plans']);
    Route::post('/billing/plans', [BillingController::class, 'storePlan']);
    Route::put('/billing/plans/{plan}', [BillingController::class, 'updatePlan']);
    Route::delete('/billing/plans/{plan}', [BillingController::class, 'destroyPlan']);
    Route::get('/billing/plans/public', [BillingController::class, 'publicPlans']);
    Route::get('/billing/coupons', [BillingController::class, 'coupons']);
    Route::post('/billing/coupons', [BillingController::class, 'storeCoupon']);
    Route::put('/billing/coupons/{coupon}', [BillingController::class, 'updateCoupon']);
    Route::get('/billing/coupons/validate', [BillingController::class, 'validateCoupon']);
    Route::get('/billing/coupons/stats', [BillingController::class, 'couponStats']);
    Route::get('/billing/coupons/{coupon}/redemptions', [BillingController::class, 'couponRedemptions']);

    // ── 购物车 ──
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'show']);
        Route::post('/add', [CartController::class, 'add']);
        Route::put('/update', [CartController::class, 'update']);
        Route::post('/remove', [CartController::class, 'remove']);
        Route::post('/clear', [CartController::class, 'clear']);
        Route::get('/summary', [CartController::class, 'summary']);
        Route::post('/apply-coupon', [CartController::class, 'applyCoupon']);
        Route::post('/remove-coupon', [CartController::class, 'removeCoupon']);
        Route::post('/merge', [CartController::class, 'merge']);
        Route::post('/validate-checkout', [CartController::class, 'validateCheckout']);
        Route::post('/checkout', [CartController::class, 'checkout']);
        Route::post('/quick-buy', [CartController::class, 'quickBuy']);
    });

    // ── 收藏 ──
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\WishlistController::class, 'myWishlists']);
        Route::get('/stats', [\App\Http\Controllers\Api\WishlistController::class, 'myStats']);
        Route::get('/product-ids', [\App\Http\Controllers\Api\WishlistController::class, 'myWishlistedProductIds']);
        Route::get('/check/{productId}', [\App\Http\Controllers\Api\WishlistController::class, 'isWishlisted'])->whereNumber('productId');
        Route::post('/toggle', [\App\Http\Controllers\Api\WishlistController::class, 'toggle']);
        Route::post('/add', [\App\Http\Controllers\Api\WishlistController::class, 'add']);
    });

    // ── 商品评论（公开）──
    Route::get('/products/{product}/reviews/stats', [\App\Http\Controllers\Api\ProductReviewController::class, 'productRatingStats'])->whereNumber('product')->withoutMiddleware('auth:sanctum');
    Route::get('/products/{product}/reviews', [\App\Http\Controllers\Api\ProductReviewController::class, 'productReviews'])->whereNumber('product')->withoutMiddleware('auth:sanctum');
    Route::post('/products/reviews', [\App\Http\Controllers\Api\ProductReviewController::class, 'store'])->withoutMiddleware('auth:sanctum');

    // ── 订单管理 ──
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/stats', [OrderController::class, 'stats']);
        Route::get('/my', [OrderController::class, 'myOrders']);
        Route::get('/{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::post('/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id');
        Route::post('/{id}/pay', [OrderController::class, 'pay'])->whereNumber('id');
        Route::get('/{id}/payment-status', [OrderController::class, 'paymentStatus'])->whereNumber('id');
        Route::post('/{id}/mark-paid', [OrderController::class, 'markPaid'])->whereNumber('id');
    });

    // ── 套餐管理 ──
    Route::prefix('plans')->group(function () {
        Route::get('/bundle-rules', [PlanController::class, 'bundleRules']);
        Route::post('/bundle-rules', [PlanController::class, 'storeBundleRule']);
        Route::put('/bundle-rules/{bundle}', [PlanController::class, 'updateBundleRule']);
        Route::delete('/bundle-rules/{bundle}', [PlanController::class, 'destroyBundleRule']);
        Route::get('/upgrade-paths', [PlanController::class, 'upgradePaths']);
        Route::post('/upgrade-paths', [PlanController::class, 'storeUpgradePath']);
        Route::put('/upgrade-paths/{path}', [PlanController::class, 'updateUpgradePath']);
        Route::delete('/upgrade-paths/{path}', [PlanController::class, 'destroyUpgradePath']);
        Route::post('/calculate-upgrade', [PlanController::class, 'calculateUpgrade']);
        Route::post('/subscriptions/{subscription}/upgrade', [PlanController::class, 'executeUpgrade']);
        Route::get('/upgrade-logs', [PlanController::class, 'upgradeLogs']);
        Route::get('/', [PlanController::class, 'index']);
        Route::get('/{plan}', [PlanController::class, 'show']);
    });

    // ── SKU 管理（兼容前端 order.js 调用） ──
    Route::get('/skus', [OrderController::class, 'skus']);

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

    // 到货通知
    Route::post('/stock-notify/subscribe', [App\Http\Controllers\Api\StockNotifyController::class, 'subscribe']);

    // Ticket System (public)
    Route::get('/tickets/categories', [TicketController::class, 'categories']);
    Route::post('/tickets', [TicketController::class, 'store']);

    // Handoff (客服转接)
    Route::post('/handoff', [HandoffController::class, 'store']);
    Route::get('/handoff/{handoff}', [HandoffController::class, 'show'])->whereNumber('handoff');
    Route::get('/handoff/{handoff}/messages', [HandoffController::class, 'getMessages'])->whereNumber('handoff');
    Route::post('/handoff/{handoff}/messages', [HandoffController::class, 'sendMessage'])->whereNumber('handoff');
    Route::post('/handoff/{handoff}/accept', [HandoffController::class, 'accept'])->whereNumber('handoff');
    Route::post('/handoff/{handoff}/close', [HandoffController::class, 'close'])->whereNumber('handoff');
    Route::post('/handoff/{handoff}/rate', [HandoffController::class, 'rate'])->whereNumber('handoff');
    Route::post('/handoff/{handoff}/transfer', [HandoffController::class, 'transfer'])->whereNumber('handoff');
    Route::get('/handoff/{handoff}/visitor', [HandoffController::class, 'visitorInfo'])->whereNumber('handoff');
    Route::post('/handoff/status', [HandoffController::class, 'updateStatus']);
    Route::get('/handoff/online-agents', [HandoffController::class, 'onlineAgents']);
    Route::get('/handoff/queue-stats', [HandoffController::class, 'queueStats']);
    Route::get('/handoffs/my', [HandoffController::class, 'myHistory']);
    Route::get('/handoffs/queue', [HandoffController::class, 'queue']);
    Route::get('/handoff/conversations', [HandoffController::class, 'myConversations']);
    Route::get('/tickets/my', [TicketController::class, 'myTickets']);

    // Site settings management
    Route::get('/settings', [SiteSettingController::class, 'grouped']);
    Route::get('/settings/all', [SiteSettingController::class, 'index']);
    Route::post('/settings', [SiteSettingController::class, 'update']);
    Route::post('/settings/create', [SiteSettingController::class, 'store']);
    Route::post('/settings/upload-image', [SiteSettingController::class, 'uploadImage']);

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

    // 🆕 AI 知识库自增长
    Route::prefix('kb-auto-grow')->group(function () {
        Route::get('/stats', [KbAutoGrowController::class, 'stats']);
        Route::get('/pending', [KbAutoGrowController::class, 'pending']);
        Route::post('/{id}/approve', [KbAutoGrowController::class, 'approve'])->whereNumber('id');
        Route::post('/{id}/reject', [KbAutoGrowController::class, 'reject'])->whereNumber('id');
        Route::post('/run', [KbAutoGrowController::class, 'run']);
    });

    // 🆕 AI 深度研究模式
    Route::prefix('deep-research')->group(function () {
        Route::post('/start', [DeepResearchController::class, 'start']);
        Route::get('/history', [DeepResearchController::class, 'history']);
        Route::get('/{id}', [DeepResearchController::class, 'show'])->whereNumber('id');
        Route::delete('/{id}', [DeepResearchController::class, 'destroy'])->whereNumber('id');
    });

    // 🆕 AI 搜索增强
    Route::prefix('vector-search')->group(function () {
        Route::post('/search', [VectorSearchController::class, 'search']);
        Route::post('/rebuild', [VectorSearchController::class, 'rebuild']);
        Route::get('/stats', [VectorSearchController::class, 'stats']);
    });

    // 🆕 Meilisearch 全文搜索
    Route::prefix('meilisearch')->group(function () {
        Route::get('/health', [MeilisearchController::class, 'health']);
        Route::get('/indexes', [MeilisearchController::class, 'indexes']);
        Route::post('/indexes/setup', [MeilisearchController::class, 'setupIndex']);
        Route::delete('/indexes', [MeilisearchController::class, 'deleteIndex']);
        Route::post('/sync', [MeilisearchController::class, 'sync']);
        Route::get('/search', [MeilisearchController::class, 'search']);
        Route::post('/clear', [MeilisearchController::class, 'clear']);
        Route::get('/stats', [MeilisearchController::class, 'stats']);
    });

    // 🆕 AI 幻觉检测
    Route::prefix('hallucination')->group(function () {
        Route::post('/inspect', [HallucinationController::class, 'inspect']);
        Route::post('/annotate', [HallucinationController::class, 'annotate']);
        Route::get('/history', [HallucinationController::class, 'history']);
        Route::get('/stats', [HallucinationController::class, 'stats']);
    });

    // 🆕 AI 内容溯源/数字签名
    Route::prefix('content-signatures')->group(function () {
        Route::post('/sign', [ContentSignatureController::class, 'sign']);
        Route::post('/sign-and-mark', [ContentSignatureController::class, 'signAndMark']);
        Route::post('/verify', [ContentSignatureController::class, 'verify']);
        Route::get('/stats', [ContentSignatureController::class, 'stats']);
    });

    // 🆕 AI 自动化运营编排
    Route::prefix('content-quality')->group(function () {
        Route::post('/rate', [ContentQualityController::class, 'rate']);
        Route::post('/run', [ContentQualityController::class, 'run']);
        Route::get('/stats', [ContentQualityController::class, 'stats']);
        Route::get('/history', [ContentQualityController::class, 'history']);
    });

    // 🆕 PRAC-012 电子签名消息
    Route::prefix('electronic-signatures')->group(function () {
        Route::post('/create', [ElectronicSignatureController::class, 'create']);
        Route::post('/{id}/sign', [ElectronicSignatureController::class, 'sign'])->whereNumber('id');
        Route::post('/{id}/reject', [ElectronicSignatureController::class, 'reject'])->whereNumber('id');
        Route::post('/verify', [ElectronicSignatureController::class, 'verify']);
        Route::get('/my-pending', [ElectronicSignatureController::class, 'myPending']);
        Route::get('/history', [ElectronicSignatureController::class, 'history']);
        Route::get('/stats', [ElectronicSignatureController::class, 'stats']);
    });

    // 🆕 AI 自学习引擎
    Route::prefix('self-learning')->group(function () {
        Route::post('/learn', [SelfLearningController::class, 'learn']);
        Route::get('/status', [SelfLearningController::class, 'status']);
        Route::get('/logs', [SelfLearningController::class, 'logs']);
        Route::get('/patterns', [SelfLearningController::class, 'patterns']);
    });

    // 🆕 IM 增强功能
    Route::prefix('im')->group(function () {
        // 快捷回复
        Route::get('/canned-replies', [ImEnhanceController::class, 'cannedIndex']);
        Route::post('/canned-replies', [ImEnhanceController::class, 'cannedStore']);
        Route::put('/canned-replies/{id}', [ImEnhanceController::class, 'cannedUpdate'])->whereNumber('id');
        Route::delete('/canned-replies/{id}', [ImEnhanceController::class, 'cannedDestroy'])->whereNumber('id');
        // 会话标签
        Route::get('/tags', [ImEnhanceController::class, 'tagIndex']);
        Route::post('/tags', [ImEnhanceController::class, 'tagStore']);
        Route::put('/tags/{id}', [ImEnhanceController::class, 'tagUpdate'])->whereNumber('id');
        Route::delete('/tags/{id}', [ImEnhanceController::class, 'tagDestroy'])->whereNumber('id');
        Route::post('/tags/assign', [ImEnhanceController::class, 'tagAssign']);
        Route::post('/tags/get-assigned', [ImEnhanceController::class, 'tagGetAssigned']);
        // 敏感词管理
        Route::get('/sensitive-words', [ImEnhanceController::class, 'sensitiveIndex']);
        Route::post('/sensitive-words', [ImEnhanceController::class, 'sensitiveStore']);
        Route::put('/sensitive-words/{id}', [ImEnhanceController::class, 'sensitiveUpdate'])->whereNumber('id');
        Route::delete('/sensitive-words/{id}', [ImEnhanceController::class, 'sensitiveDestroy'])->whereNumber('id');
        Route::post('/sensitive-words/test', [ImEnhanceController::class, 'sensitiveTest']);
        Route::post('/sensitive-words/import', [ImEnhanceController::class, 'sensitiveImport']);
        Route::get('/sensitive-words/export', [ImEnhanceController::class, 'sensitiveExport']);
        // 客服组
        Route::get('/groups', [ImEnhanceController::class, 'groupIndex']);
        Route::post('/groups', [ImEnhanceController::class, 'groupStore']);
        Route::put('/groups/{id}', [ImEnhanceController::class, 'groupUpdate'])->whereNumber('id');
        Route::delete('/groups/{id}', [ImEnhanceController::class, 'groupDestroy'])->whereNumber('id');
        Route::post('/groups/add-member', [ImEnhanceController::class, 'groupAddMember']);
        Route::delete('/groups/{groupId}/members/{userId}', [ImEnhanceController::class, 'groupRemoveMember'])->whereNumber('groupId')->whereNumber('userId');
        // 自动回复规则
        Route::get('/auto-reply-rules', [ImEnhanceController::class, 'ruleIndex']);
        Route::post('/auto-reply-rules', [ImEnhanceController::class, 'ruleStore']);
        Route::put('/auto-reply-rules/{id}', [ImEnhanceController::class, 'ruleUpdate'])->whereNumber('id');
        Route::delete('/auto-reply-rules/{id}', [ImEnhanceController::class, 'ruleDestroy'])->whereNumber('id');
        // 聊天记录导出 + 文件上传
        Route::get('/conversations/{id}/export', [ImEnhanceController::class, 'exportConversation'])->whereNumber('id');
        Route::post('/upload', [ImEnhanceController::class, 'uploadChatFile']);
        // 🆕 消息已读/置顶/免打扰/搜索等
        Route::post('/messages/mark-read', [ImEnhanceController::class, 'markAsRead']);
        Route::post('/conversations/{id}/pin', [ImEnhanceController::class, 'togglePin'])->whereNumber('id');
        Route::post('/conversations/{id}/mute', [ImEnhanceController::class, 'toggleMute'])->whereNumber('id');
        Route::delete('/conversations/{id}', [ImEnhanceController::class, 'softDeleteConv'])->whereNumber('id');
        Route::post('/conversations/{id}/restore', [ImEnhanceController::class, 'restoreConv'])->whereNumber('id');
        Route::put('/conversations/{id}/draft', [ImEnhanceController::class, 'saveDraft'])->whereNumber('id');
        Route::get('/messages/search', [ImEnhanceController::class, 'searchMessages']);
        Route::get('/conversations/unread', [ImEnhanceController::class, 'unreadConversations']);
        Route::get('/notify-config', [ImEnhanceController::class, 'notifyConfig']);
        // 绩效看板
        Route::get('/agent-performance', [ImEnhanceController::class, 'agentPerformance']);
        Route::get('/dashboard', [ImEnhanceController::class, 'imDashboard']);

        // 🆕 IM 通知集成（测试连接 + 发送）
        Route::post('/slack/test', [ImIntegrationController::class, 'testSlack']);
        Route::post('/dingtalk/test', [ImIntegrationController::class, 'testDingTalk']);
        Route::post('/wecom/test', [ImIntegrationController::class, 'testWeCom']);
        Route::post('/feishu/test', [ImIntegrationController::class, 'testFeishu']);
        Route::post('/send', [ImIntegrationController::class, 'send']);
    });

    // 🆕 用户 P2P 聊天系统
    Route::prefix('user-chat')->group(function () {
        Route::post('/conversations', [UserChatController::class, 'createConversation']);
        Route::get('/conversations', [UserChatController::class, 'myConversations']);
        Route::post('/conversations/{id}/messages', [UserChatController::class, 'sendMessage'])->whereNumber('id');
        Route::get('/conversations/{id}/messages', [UserChatController::class, 'getMessages'])->whereNumber('id');
        Route::post('/conversations/{id}/read', [UserChatController::class, 'markRead'])->whereNumber('id');
        Route::post('/conversations/{id}/pin', [UserChatController::class, 'togglePin'])->whereNumber('id');
        Route::post('/conversations/{id}/mute', [UserChatController::class, 'toggleMute'])->whereNumber('id');
        Route::delete('/conversations/{id}', [UserChatController::class, 'deleteConversation'])->whereNumber('id');
        // 会话归档
        Route::post('/conversations/{id}/archive', [UserChatController::class, 'archiveConversation'])->whereNumber('id');
        Route::post('/conversations/{id}/unarchive', [UserChatController::class, 'unarchiveConversation'])->whereNumber('id');
        Route::post('/conversations/batch-archive', [UserChatController::class, 'batchArchive']);
        Route::get('/conversations/archived', [UserChatController::class, 'archivedConversations']);
        // PRAC-002: 隐藏会话/私密空间
        Route::post('/conversations/{id}/hide', [UserChatController::class, 'hideConversation'])->whereNumber('id');
        Route::post('/conversations/{id}/unhide', [UserChatController::class, 'unhideConversation'])->whereNumber('id');
        Route::get('/conversations/hidden', [UserChatController::class, 'hiddenConversations']);
        Route::post('/privacy-pin/set', [UserChatController::class, 'setPrivacyPin']);
        Route::post('/privacy-pin/verify', [UserChatController::class, 'verifyPrivacyPin']);
        Route::get('/privacy-pin/status', [UserChatController::class, 'privacyPinStatus']);
        // PRAC-006: 群内投票
        Route::post('/polls', [PollController::class, 'create']);
        Route::get('/polls/{poll}', [PollController::class, 'show'])->whereNumber('poll');
        Route::post('/polls/{poll}/vote', [PollController::class, 'vote'])->whereNumber('poll');
        Route::get('/polls/{poll}/results', [PollController::class, 'results'])->whereNumber('poll');
        Route::post('/polls/{poll}/close', [PollController::class, 'close'])->whereNumber('poll');
        Route::get('/conversations/{conv}/polls', [PollController::class, 'conversationPolls'])->whereNumber('conv');
        Route::get('/users/search', [UserChatController::class, 'searchUsers']);
        // 好友系统
        Route::post('/friends/add', [UserChatController::class, 'addFriend']);
        Route::put('/friends/{id}/handle', [UserChatController::class, 'handleFriendRequest'])->whereNumber('id');
        Route::delete('/friends/{id}', [UserChatController::class, 'removeFriend'])->whereNumber('id');
        Route::get('/friends', [UserChatController::class, 'myFriends']);
        Route::get('/friends/requests', [UserChatController::class, 'pendingRequests']);
        Route::put('/friends/{id}/remark', [UserChatController::class, 'setFriendRemark'])->whereNumber('id');
        // 在线状态
        Route::post('/heartbeat', [UserChatController::class, 'heartbeat']);
        Route::get('/friends/online-status', [UserChatController::class, 'friendsOnlineStatus']);
        // 好友分组
        Route::get('/groups', [UserChatController::class, 'myGroups']);
        Route::post('/groups', [UserChatController::class, 'createGroup']);
        Route::put('/groups/{id}', [UserChatController::class, 'updateGroup'])->whereNumber('id');
        Route::delete('/groups/{id}', [UserChatController::class, 'deleteGroup'])->whereNumber('id');
        Route::put('/friends/{id}/group', [UserChatController::class, 'setFriendGroup'])->whereNumber('id');
        // 增强好友列表（含在线状态+分组）
        Route::get('/friends/enhanced', [UserChatController::class, 'myFriendsEnhanced']);
        // Emoji 表情回复
        Route::get('/messages/{message}/reactions', [UserChatController::class, 'reactions'])->whereNumber('message');
        Route::post('/messages/{message}/reactions', [UserChatController::class, 'addReaction'])->whereNumber('message');
        // 消息撤回
        Route::post('/messages/{message}/recall', [UserChatController::class, 'recallMessage'])->whereNumber('message');
        // 删除消息
        Route::delete('/messages/{message}', [UserChatController::class, 'deleteMessage'])->whereNumber('message');
        // 黑名单
        Route::post('/block/{user}', [UserChatController::class, 'blockUser'])->whereNumber('user');
        Route::post('/unblock/{user}', [UserChatController::class, 'unblockUser'])->whereNumber('user');
        Route::get('/blocked', [UserChatController::class, 'blockedList']);
        // 消息编辑
        Route::put('/messages/{message}/edit', [UserChatController::class, 'editMessage'])->whereNumber('message');
        // 收藏消息
        Route::post('/messages/{message}/favorite', [UserChatController::class, 'toggleFavorite'])->whereNumber('message');
        Route::post('/messages/{message}/pin', [UserChatController::class, 'pinMessage'])->whereNumber('message');
        Route::post('/messages/{message}/unpin', [UserChatController::class, 'unpinMessage'])->whereNumber('message');
        Route::get('/conversations/{conv}/pinned-messages', [UserChatController::class, 'pinnedMessages'])->whereNumber('conv');
        Route::get('/favorites', [UserChatController::class, 'myFavorites']);
        // OPR-008: 消息稍后处理/标记
        Route::post('/messages/{message}/pending', [UserChatController::class, 'togglePending'])->whereNumber('message');
        Route::get('/messages/pending', [UserChatController::class, 'listPending']);
        // 已读详情
        Route::get('/messages/{message}/read-status', [UserChatController::class, 'messageReadStatus'])->whereNumber('message');
        // 群禁言
        Route::post('/conversations/{conv}/mute/{user}', [UserChatController::class, 'muteMember'])->whereNumber('conv')->whereNumber('user');
        Route::post('/conversations/{conv}/unmute/{user}', [UserChatController::class, 'unmuteMember'])->whereNumber('conv')->whereNumber('user');
        // 链接预览
        Route::post('/link-preview', [UserChatController::class, 'linkPreview']);
        // 群公告
        Route::post('/announcements', [UserChatController::class, 'createAnnouncement']);
        Route::get('/announcements/{id}', [UserChatController::class, 'announcementDetail'])->whereNumber('id');
        Route::get('/conversations/{conv}/announcements', [UserChatController::class, 'conversationAnnouncements'])->whereNumber('conv');
        Route::post('/announcements/{id}/read', [UserChatController::class, 'markAnnouncementRead'])->whereNumber('id');
        // PRAC-015: 公告阅读进度
        Route::get('/announcements/{id}/read-progress', [AnnouncementReadController::class, 'readProgress'])->whereNumber('id');
        Route::get('/announcement-stats', [AnnouncementReadController::class, 'announcementStats']);
        // INNOV-013: 快捷指令
        Route::post('/slash-command', [SlashCommandController::class, 'execute']);
        Route::get('/slash-commands', [SlashCommandController::class, 'commands']);
        // PRAC-004: 个人自动回复
        Route::get('/auto-reply', [UserAutoReplyController::class, 'index']);
        Route::post('/auto-reply', [UserAutoReplyController::class, 'store']);
        Route::put('/auto-reply/{id}', [UserAutoReplyController::class, 'update'])->whereNumber('id');
        Route::delete('/auto-reply/{id}', [UserAutoReplyController::class, 'destroy'])->whereNumber('id');
        Route::get('/auto-reply/status', [UserAutoReplyController::class, 'status']);
        // 慢速模式
        Route::post('/conversations/{conv}/slow-mode', [UserChatController::class, 'setSlowMode'])->whereNumber('conv');
        Route::get('/conversations/{conv}/slow-mode', [UserChatController::class, 'getSlowMode'])->whereNumber('conv');
        // 群邀请
        Route::post('/conversations/{conv}/invites', [UserChatController::class, 'createGroupInvite'])->whereNumber('conv');
        Route::get('/conversations/{conv}/invites', [UserChatController::class, 'groupInvites'])->whereNumber('conv');
        Route::get('/conversations/{conv}/join-requests', [UserChatController::class, 'pendingJoinRequests'])->whereNumber('conv');
        Route::post('/conversations/{conv}/toggle-approval', [UserChatController::class, 'toggleJoinApproval'])->whereNumber('conv');
        Route::get('/conversations/{conv}/permissions', [UserChatController::class, 'getGroupPermissions'])->whereNumber('conv');
        Route::put('/conversations/{conv}/permissions', [UserChatController::class, 'updateGroupPermissions'])->whereNumber('conv');
        Route::post('/join-request', [UserChatController::class, 'submitJoinRequest']);
        Route::post('/join-requests/{request}/handle', [UserChatController::class, 'handleJoinRequest'])->whereNumber('request');
        Route::delete('/invites/{invite}', [UserChatController::class, 'revokeInvite'])->whereNumber('invite');
        // 文件传输助手
        Route::post('/self-conversation', [UserChatController::class, 'selfConversation']);
        // 群管理
        Route::post('/conversations/{conv}/kick/{user}', [UserChatController::class, 'kickMember'])->whereNumber('conv')->whereNumber('user');
        Route::post('/conversations/{conv}/leave', [UserChatController::class, 'leaveGroup'])->whereNumber('conv');
        Route::post('/conversations/{conv}/transfer-owner', [UserChatController::class, 'transferOwner'])->whereNumber('conv');
        Route::post('/conversations/{conv}/set-admin', [UserChatController::class, 'setAdmin'])->whereNumber('conv');
        Route::delete('/conversations/{conv}/dismiss', [UserChatController::class, 'dismissGroup'])->whereNumber('conv');
        // 举报
        Route::post('/reports', [UserChatController::class, 'report']);
        Route::get('/reports/my', [UserChatController::class, 'myReports']);
        // PRES-003: 在线状态
        Route::put('/status', [UserChatController::class, 'setStatus']);
        // MSG-005: 消息状态
        Route::post('/messages/delivered', [UserChatController::class, 'markDelivered']);
        Route::post('/messages/read-batch', [UserChatController::class, 'markMessagesRead']);
        // SEC-006: 隐私设置
        Route::get('/privacy-settings', [UserChatController::class, 'getPrivacySettings']);
        Route::put('/privacy-settings', [UserChatController::class, 'savePrivacySettings']);
        // GRP-004: 群资料
        Route::put('/conversations/{conv}/profile', [UserChatController::class, 'updateGroupProfile'])->whereNumber('conv');
        // AI-001: 智能回复
        Route::get('/conversations/{conv}/smart-replies', [UserChatController::class, 'smartReplies'])->whereNumber('conv');
        Route::get('/conversations/{conv}/summarize', [UserChatController::class, 'summarize'])->whereNumber('conv');
        Route::post('/messages/{message}/translate', [UserChatController::class, 'translateMessage'])->whereNumber('message');
        Route::post('/conversations/{conv}/translate-all', [UserChatController::class, 'translateConversation'])->whereNumber('conv');
        // AI-010: 流式 AI 对话 SSE
        Route::post('/conversations/{conv}/chat-stream', [UserChatController::class, 'chatStreamSSE'])->whereNumber('conv');
        // AI-002: 未读消息摘要
        Route::get('/unread-summary', [UserChatController::class, 'unreadSummary']);
        // AI-012: AI 助手单聊
        Route::post('/ai-conversation', [UserChatController::class, 'createAIConversation']);
        // AI-013: 群聊 @AI
        Route::post('/conversations/{conv}/ai-mention', [UserChatController::class, 'aiMention'])->whereNumber('conv');
        // AI-004: 保存 AI 消息
        Route::post('/conversations/{conv}/ai-save', [UserChatController::class, 'saveAIMessage'])->whereNumber('conv');
        // AI-005: 语义搜索
        Route::get('/messages/semantic-search', [UserChatController::class, 'semanticSearch']);
        // AI-015: 提取待办
        Route::get('/conversations/{conv}/extract-tasks', [UserChatController::class, 'extractTasks'])->whereNumber('conv');
        // AI-017: 自动打标签
        Route::post('/conversations/{conv}/auto-tag', [UserChatController::class, 'autoTagConversation'])->whereNumber('conv');
        // AI-018: 智能分类
        Route::get('/classify', [UserChatController::class, 'classifyConversations']);
        // AI-016: 紧急度评估
        Route::post('/evaluate-urgency', [UserChatController::class, 'evaluateUrgency']);
        // AI-044: 发送前 AI 预审
        Route::post('/pre-review', [UserChatController::class, 'preReview']);
        Route::post('/deep-review', [UserChatController::class, 'deepReview']);
        // AI-011: 语义缓存
        Route::get('/cache-stats', [UserChatController::class, 'cacheStats']);
        Route::delete('/cache', [UserChatController::class, 'clearCache']);
        // 产品推荐与卡片消息
        Route::post('/recommend-products', [UserChatController::class, 'recommendProducts']);
        Route::post('/conversations/{conv}/send-product-card', [UserChatController::class, 'sendProductCard'])->whereNumber('conv');
        Route::post('/conversations/{conv}/send-order-card', [UserChatController::class, 'sendOrderCard'])->whereNumber('conv');
        Route::post('/conversations/{conv}/send-custom-card', [UserChatController::class, 'sendCustomCard'])->whereNumber('conv');
        Route::post('/messages/forward', [UserChatController::class, 'forwardMessages']);
        Route::get('/conversations/forwardable', [UserChatController::class, 'forwardableConversations']);
        // 扩展卡片类型
        Route::post('/conversations/{conv}/send-article-card', [UserChatController::class, 'sendArticleCard'])->whereNumber('conv');
        Route::post('/conversations/{conv}/send-approval-card', [UserChatController::class, 'sendApprovalCard'])->whereNumber('conv');
        Route::post('/conversations/{conv}/send-coupon-card', [UserChatController::class, 'sendCouponCard'])->whereNumber('conv');
        Route::post('/conversations/{conv}/send-todo-card', [UserChatController::class, 'sendTodoCard'])->whereNumber('conv');
        // 卡片动作回调
        Route::post('/card-callback', [UserChatController::class, 'cardCallback']);
        // SYNC-004: 消息漫游
        Route::get('/messages/sync', [UserChatController::class, 'syncMessages']);
        // SRCH-001: 全文搜索
        Route::get('/messages/search-fulltext', [UserChatController::class, 'searchMessagesFulltext']);
        // MEDIA-012: 语音转文字
        Route::post('/messages/{message}/transcribe', [UserChatController::class, 'transcribeVoice'])->whereNumber('message');
    });

    // ── 在线客服 Live Chat ──
    Route::prefix('live-chat')->group(function () {
        // 客户端（公开）
        Route::post('/conversations', [LiveChatController::class, 'createConversation']);
        Route::post('/conversations/{conversation}/messages', [LiveChatController::class, 'sendMessage'])->whereNumber('conversation');
        Route::get('/conversations/{conversation}/messages', [LiveChatController::class, 'getMessages'])->whereNumber('conversation');
        Route::post('/conversations/{conversation}/close', [LiveChatController::class, 'closeConversation'])->whereNumber('conversation');
        // 管理端（需认证）
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/admin/dashboard', [LiveChatController::class, 'dashboard']);
            Route::get('/admin/conversations', [LiveChatController::class, 'conversations']);
            Route::post('/admin/handoffs/{handoff}/accept', [LiveChatController::class, 'acceptHandoff'])->whereNumber('handoff');
            Route::get('/admin/pending-handoffs', [LiveChatController::class, 'pendingHandoffs']);
        });
    });

    // ── AI-043 长期记忆 Memory ──
    Route::prefix('memory')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\MemoryController::class, 'dashboard']);
        Route::get('/', [\App\Http\Controllers\Api\MemoryController::class, 'index']);
        Route::get('/options', [\App\Http\Controllers\Api\MemoryController::class, 'options']);
        Route::post('/', [\App\Http\Controllers\Api\MemoryController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\MemoryController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [\App\Http\Controllers\Api\MemoryController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [\App\Http\Controllers\Api\MemoryController::class, 'destroy'])->whereNumber('id');
        Route::post('/batch-delete', [\App\Http\Controllers\Api\MemoryController::class, 'batchDestroy']);
        Route::post('/{id}/confirm', [\App\Http\Controllers\Api\MemoryController::class, 'confirm'])->whereNumber('id');
        Route::put('/{id}/correct', [\App\Http\Controllers\Api\MemoryController::class, 'correct'])->whereNumber('id');
        Route::post('/extract', [\App\Http\Controllers\Api\MemoryController::class, 'extract']);
        Route::delete('/clear-all', [\App\Http\Controllers\Api\MemoryController::class, 'clearAll']);
    });

    // ── AI-045 主动洞察推送 ──
    Route::prefix('insights')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AiProactiveInsightController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\AiProactiveInsightController::class, 'stats']);
        Route::get('/types', [\App\Http\Controllers\Api\AiProactiveInsightController::class, 'types']);
        Route::post('/{id}/read', [\App\Http\Controllers\Api\AiProactiveInsightController::class, 'markRead'])->whereNumber('id');
        Route::post('/{id}/dismiss', [\App\Http\Controllers\Api\AiProactiveInsightController::class, 'dismiss'])->whereNumber('id');
        Route::post('/mark-all-read', [\App\Http\Controllers\Api\AiProactiveInsightController::class, 'markAllRead']);
    });

    // ── PRAC-009 值班轮换 On-Call ──
    Route::prefix('on-call')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\OnCallController::class, 'dashboard']);
        Route::get('/', [\App\Http\Controllers\Api\OnCallController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\OnCallController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\OnCallController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [\App\Http\Controllers\Api\OnCallController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [\App\Http\Controllers\Api\OnCallController::class, 'destroy'])->whereNumber('id');
        Route::post('/{id}/generate', [\App\Http\Controllers\Api\OnCallController::class, 'generate'])->whereNumber('id');
        Route::post('/{scheduleId}/members', [\App\Http\Controllers\Api\OnCallController::class, 'addMember'])->whereNumber('scheduleId');
        Route::delete('/{scheduleId}/members/{memberId}', [\App\Http\Controllers\Api\OnCallController::class, 'removeMember'])->whereNumber('scheduleId')->whereNumber('memberId');
        Route::post('/overrides', [\App\Http\Controllers\Api\OnCallController::class, 'createOverride']);
        Route::get('/logs', [\App\Http\Controllers\Api\OnCallController::class, 'logs']);
    });

    // ── AIF AI 好友系统 ──
    Route::prefix('ai-friends')->middleware(['auth:sanctum'])->group(function () {
        // 管理后台
        Route::get('/admin', [AiFriendController::class, 'adminIndex']);
        Route::post('/admin', [AiFriendController::class, 'adminStore']);
        Route::put('/admin/{id}', [AiFriendController::class, 'adminUpdate'])->whereNumber('id');
        Route::post('/admin/{id}/publish', [AiFriendController::class, 'adminPublish'])->whereNumber('id');
        Route::post('/admin/{id}/test', [AiFriendController::class, 'adminTest'])->whereNumber('id');
        Route::get('/admin/{id}/conversations', [AiFriendController::class, 'adminConversations'])->whereNumber('id');
        // 头像上传
        Route::post('/upload-avatar', [AiFriendController::class, 'uploadAvatar']);
        // 用户端
        Route::get('/my', [AiFriendController::class, 'myAiFriends']);
        Route::post('/personal', [AiFriendController::class, 'createPersonal']);
        Route::delete('/personal/{id}', [AiFriendController::class, 'deletePersonal'])->whereNumber('id');
        Route::post('/{id}/chat', [AiFriendController::class, 'chat'])->whereNumber('id');
        Route::post('/{id}/chat-stream', [AiFriendController::class, 'chatStream'])->whereNumber('id');
        Route::post('/{id}/pin', [AiFriendController::class, 'togglePin'])->whereNumber('id');
        Route::post('/{id}/hide', [AiFriendController::class, 'toggleHide'])->whereNumber('id');
    });

    // ── ADMIN-002~008: IM 管理后台 ──
    Route::prefix('im-admin')->middleware(['auth:sanctum', 'ability:admin,super-admin'])->group(function () {
        // ADMIN-002: IM 用户管理
        Route::get('/users', [ImAdminController::class, 'users']);
        Route::get('/users/{id}', [ImAdminController::class, 'userDetail'])->whereNumber('id');
        // ADMIN-003: 群组管理
        Route::get('/groups', [ImAdminController::class, 'groups']);
        Route::get('/groups/{id}', [ImAdminController::class, 'groupDetail'])->whereNumber('id');
        Route::delete('/groups/{id}', [ImAdminController::class, 'dismissGroup'])->whereNumber('id');
        // ADMIN-004: 消息审计
        Route::get('/messages', [ImAdminController::class, 'messageAudit']);
        Route::delete('/messages/{id}', [ImAdminController::class, 'deleteMessage'])->whereNumber('id');
        // ADMIN-005: 数据看板
        Route::get('/dashboard', [ImAdminController::class, 'dashboard']);
        // ADMIN-007: 举报管理
        Route::get('/reports', [ImAdminController::class, 'reports']);
        Route::post('/reports/{id}/resolve', [ImAdminController::class, 'resolveReport'])->whereNumber('id');
        // ADMIN-008: 会话管理
        Route::get('/conversations', [ImAdminController::class, 'conversations']);
        Route::get('/conversations/{id}', [ImAdminController::class, 'conversationDetail'])->whereNumber('id');
        Route::delete('/conversations/{id}', [ImAdminController::class, 'deleteConversation'])->whereNumber('id');
        // 用户封禁/解封
        Route::post('/users/{id}/ban', [ImAdminController::class, 'banUser'])->whereNumber('id');
        Route::post('/users/{id}/unban', [ImAdminController::class, 'unbanUser'])->whereNumber('id');
    });

    // ── MSG-015: 文件传输增强 ──
    Route::prefix('files')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/upload/simple', [FileUploadController::class, 'simpleUpload']);
        Route::post('/upload/init-chunk', [FileUploadController::class, 'initChunk']);
        Route::post('/upload/chunk', [FileUploadController::class, 'uploadChunk']);
        Route::get('/upload/chunk-status', [FileUploadController::class, 'chunkStatus']);
        Route::get('/preview', [FileUploadController::class, 'filePreview']);
    });

    // ── THREAD-001~004: Thread 话题系统 ──
    Route::prefix('threads')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/messages/{message}/reply', [ThreadController::class, 'reply'])->whereNumber('message');
        Route::get('/messages/{message}/replies', [ThreadController::class, 'replies'])->whereNumber('message');
        Route::get('/messages/{message}/summary', [ThreadController::class, 'threadSummary'])->whereNumber('message');
    });

    // ── RTC-001~004: 音视频通话 ──
    Route::prefix('calls')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/call', [CallController::class, 'call']);
        Route::post('/{id}/respond', [CallController::class, 'respond'])->whereNumber('id');
        Route::post('/{id}/end', [CallController::class, 'end'])->whereNumber('id');
        Route::get('/{id}/status', [CallController::class, 'status'])->whereNumber('id');
        Route::post('/{id}/signal', [CallController::class, 'signal'])->whereNumber('id');
        Route::get('/{id}/signal-poll', [CallController::class, 'signalPoll'])->whereNumber('id');
        Route::get('/history', [CallController::class, 'history']);
    });

    // ── BOT-001~006: Bot 机器人系统 ──
    Route::prefix('bots')->group(function () {
        Route::post('/webhook', [BotController::class, 'webhook'])->withoutMiddleware(['auth:sanctum']);
        Route::post('/execute', [BotController::class, 'executeCommand']);
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('/register', [BotController::class, 'register']);
            Route::get('/my', [BotController::class, 'index']);
            Route::post('/{id}/refresh-token', [BotController::class, 'refreshToken'])->whereNumber('id');
            Route::get('/marketplace', [BotController::class, 'marketplace']);
        });
    });

    // ── E2EE-001~008: 端到端加密 ──
    Route::prefix('e2ee')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/keys/register', [E2eeController::class, 'registerKeys']);
        Route::get('/keys/generate', [E2eeController::class, 'generateKeys']);
        Route::get('/keys/{user}', [E2eeController::class, 'getPrekeyBundle'])->whereNumber('user');
        Route::post('/session/init', [E2eeController::class, 'initSession']);
        Route::post('/encrypt', [E2eeController::class, 'encrypt']);
        Route::post('/decrypt', [E2eeController::class, 'decrypt']);
        Route::get('/status', [E2eeController::class, 'status']);
    });

    // ── 客服 AI 包（§4.14 Phase 4）──
    Route::prefix('cs-ai')->middleware(['auth:sanctum'])->group(function () {
        // AI-019: AI 自动客服
        Route::post('/auto-reply', [AICustomerServiceController::class, 'autoReply']);
        // AI-019s: AI 自动客服（流式 SSE）
        Route::post('/auto-reply-stream', [AICustomerServiceController::class, 'autoReplyStream']);
        // AI-020: 置信度评估
        Route::post('/confidence', [AICustomerServiceController::class, 'evaluateConfidence']);
        // AI-021: 意图识别
        Route::post('/intent', [AICustomerServiceController::class, 'intentClassification']);
        // AI-022: 情感分析
        Route::post('/sentiment', [AICustomerServiceController::class, 'sentimentAnalysis']);
        // AI-023: AI 辅助坐席
        Route::post('/agent-assist/{conv}', [AICustomerServiceController::class, 'agentAssist'])->whereNumber('conv');
        // AI-024: AI 会话质检
        Route::get('/quality-check/{conv}', [AICustomerServiceController::class, 'qualityCheck'])->whereNumber('conv');
        // AI-025: 多轮对话状态机
        Route::post('/dialog-state', [AICustomerServiceController::class, 'dialogStateMachine']);
    });

    // ── MSG-010: 贴纸/GIF 系统 ──
    Route::prefix('stickers')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/packs', [StickerController::class, 'packs']);
        Route::get('/packs/system', [StickerController::class, 'systemPacks']);
        Route::post('/packs', [StickerController::class, 'createPack']);
        Route::post('/packs/{pack}/stickers', [StickerController::class, 'addSticker'])->whereNumber('pack');
        Route::delete('/packs/{id}', [StickerController::class, 'deletePack'])->whereNumber('id');
        Route::post('/send/{conv}', [StickerController::class, 'sendSticker'])->whereNumber('conv');
        Route::get('/search-gif', [StickerController::class, 'searchGif']);
        Route::get('/emojis/frequent', [StickerController::class, 'frequentEmojis']);
    });

    // ── 自定义 Emoji / 企业表情包 ──
    Route::prefix('emoji')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [EmojiController::class, 'all']);
        Route::get('/categories', [EmojiController::class, 'categories']);
        Route::post('/{id}/track', [EmojiController::class, 'trackUsage'])->whereNumber('id');
    });

    // ── 企业与 Agent AI（§4.15 Phase 4）──
    Route::prefix('enterprise-ai')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/knowledge-query', [EnterpriseAIController::class, 'knowledgeQuery']);        // AI-026
        Route::get('/meeting-minutes/{conv}', [EnterpriseAIController::class, 'meetingMinutes'])->whereNumber('conv'); // AI-027
        Route::get('/cross-session-insights', [EnterpriseAIController::class, 'crossSessionInsights']); // AI-028
        Route::get('/onboarding-guide', [EnterpriseAIController::class, 'onboardingGuide']);         // AI-029
        Route::post('/form-suggestions', [EnterpriseAIController::class, 'formSuggestions']);        // AI-030
        Route::post('/agent-tool-call', [EnterpriseAIController::class, 'agentToolCall']);           // AI-031
        Route::post('/bot-builder', [EnterpriseAIController::class, 'botBuilder']);                  // AI-032
        Route::post('/multi-agent-pipeline', [EnterpriseAIController::class, 'multiAgentPipeline']); // AI-033
        Route::post('/open-api', [EnterpriseAIController::class, 'openApi']);                        // AI-034
        Route::post('/finetune-data', [EnterpriseAIController::class, 'finetuneData']);              // AI-036
        // AI-046: 联网搜索状态查询
        Route::get('/web-search/status', function () {
            return \App\Http\ApiResponse::success(\App\Services\WebSearchService::getProviderStatus());
        });
        // AI-053: AI 群主持人
        Route::post('/moderator/agenda/{conv}', [EnterpriseAIController::class, 'moderatorAgenda'])->whereNumber('conv');
        Route::post('/moderator/mediate/{conv}', [EnterpriseAIController::class, 'moderatorMediate'])->whereNumber('conv');
        Route::post('/moderator/summary/{conv}', [EnterpriseAIController::class, 'moderatorSummary'])->whereNumber('conv');
        Route::post('/moderator/focus/{conv}', [EnterpriseAIController::class, 'moderatorFocus'])->whereNumber('conv');
    });

    // ── AI-056: 代码沙箱 ──
    Route::prefix('code-sandbox')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/execute', [CodeSandboxController::class, 'execute']);
        Route::get('/languages', [CodeSandboxController::class, 'languages']);
        Route::get('/templates', [CodeSandboxController::class, 'templates']);
    });

    // ── AI-057: 无障碍 AI ──
    Route::prefix('a11y')->middleware(['auth:sanctum'])->group(function () {
        // AccessibilityController (AI 图片描述/消息摘要)
        Route::post('/image-alt', [AccessibilityController::class, 'imageAlt']);
        Route::post('/describe-image', [AccessibilityController::class, 'describeImage']);
        Route::get('/message-summary/{message}', [AccessibilityController::class, 'messageSummary'])->whereNumber('message');
        Route::get('/conversation-summary/{conv}', [AccessibilityController::class, 'conversationSummary'])->whereNumber('conv');
        Route::get('/default-settings', [AccessibilityController::class, 'defaultSettings']);
        // A11yController (WCAG 合规/对比度/偏好)
        Route::get('/guidelines', [A11yController::class, 'guidelines']);
        Route::get('/stats', [A11yController::class, 'stats']);
        Route::get('/report', [A11yController::class, 'report']);
        Route::get('/limitations', [A11yController::class, 'limitations']);
        Route::get('/declaration', [A11yController::class, 'declaration']);
        Route::post('/contrast-check', [A11yController::class, 'checkContrast']);
        Route::match(['get', 'put'], '/preferences', [A11yController::class, 'preferences']);
    });

    // ── 多媒体与安全 AI（§4.16 Phase 4）+ 合规 ──
    Route::prefix('media-ai')->middleware(['auth:sanctum'])->group(function () {
        Route::post('/image-analysis', [MediaSecurityAIController::class, 'imageAnalysis']);         // AI-037
        Route::post('/video-summary', [MediaSecurityAIController::class, 'videoSummary']);           // AI-038
        Route::post('/phishing-detect', [MediaSecurityAIController::class, 'phishingDetection']);    // AI-039
        Route::post('/pii-detect', [MediaSecurityAIController::class, 'piiDetection']);             // AI-040
        Route::post('/tts', [MediaSecurityAIController::class, 'textToSpeech']);                    // AI-041
        Route::post('/translate', [MediaSecurityAIController::class, 'realtimeTranslation']);        // AI-042
        Route::post('/mark-ai-content', [MediaSecurityAIController::class, 'markAIContent']);        // DOM-004
        Route::post('/algorithm-filing', [MediaSecurityAIController::class, 'algorithmFiling']);     // DOM-005
    });

    // 公开邀请（无需认证）
    Route::get('/invite/{token}', [UserChatController::class, 'inviteInfo']);
    Route::post('/invite/{token}/join', [UserChatController::class, 'joinViaInvite']);

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

    // API 密钥管理 (M2-28: 密钥分级管理)
    Route::get('/api-keys/config/tiers', [ApiKeyController::class, 'tierConfig']);
    Route::get('/api-keys/stats/overview', [ApiKeyController::class, 'myUsageOverview']);
    Route::get('/api-keys/audit-logs/all', [ApiKeyController::class, 'allAuditLogs']);
    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::get('/api-keys/{apiKey}', [ApiKeyController::class, 'show'])->whereNumber('apiKey');
    Route::put('/api-keys/{apiKey}', [ApiKeyController::class, 'update'])->whereNumber('apiKey');
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->whereNumber('apiKey');
    Route::post('/api-keys/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate'])->whereNumber('apiKey');
    Route::post('/api-keys/{apiKey}/toggle', [ApiKeyController::class, 'toggleActive'])->whereNumber('apiKey');
    Route::get('/api-keys/{apiKey}/audit-logs', [ApiKeyController::class, 'auditLogs'])->whereNumber('apiKey');
    Route::get('/api-keys/{apiKey}/usage-stats', [ApiKeyController::class, 'usageStats'])->whereNumber('apiKey');

    // ── 细粒度 API 密钥权限 ──
    Route::get('/api-keys/fine-grained/endpoints', [FineGrainedApiKeyController::class, 'sdkEndpoints']);
    Route::get('/api-keys/{apiKey}/permissions', [FineGrainedApiKeyController::class, 'keyPermissions'])->whereNumber('apiKey');
    Route::put('/api-keys/{apiKey}/permissions', [FineGrainedApiKeyController::class, 'updatePermissions'])->whereNumber('apiKey');
    Route::get('/api-keys/{apiKey}/usage-stats/detailed', [FineGrainedApiKeyController::class, 'keyUsageStats'])->whereNumber('apiKey');

    // ── 客户 API 密钥 ──
    Route::get('/customer-api-keys/dashboard', [CustomerApiKeyController::class, 'dashboard']);
    Route::get('/customer-api-keys', [CustomerApiKeyController::class, 'index']);
    Route::post('/customer-api-keys', [CustomerApiKeyController::class, 'store']);
    Route::put('/customer-api-keys/{id}', [CustomerApiKeyController::class, 'update'])->whereNumber('id');
    Route::delete('/customer-api-keys/{id}', [CustomerApiKeyController::class, 'destroy'])->whereNumber('id');
    Route::post('/customer-api-keys/{id}/toggle', [CustomerApiKeyController::class, 'toggle'])->whereNumber('id');
    Route::get('/customer-api-keys/abilities', [CustomerApiKeyController::class, 'abilities']);

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
    Route::post('/llm/providers', [LlmController::class, 'storeProvider']);
    Route::put('/llm/providers/{llmProvider}', [LlmController::class, 'updateProvider']);
    Route::post('/llm/providers/{llmProvider}/test', [LlmController::class, 'testConnection']);
    Route::post('/llm/chat', [LlmController::class, 'chat']);
    Route::post('/llm/chat-stream', [LlmController::class, 'chatStream']);
    Route::get('/llm/token-stats', [LlmController::class, 'tokenStats']);
    Route::get('/llm/logs', [LlmController::class, 'logs']);

    // LLM Fallback management
    Route::get('/llm/fallback/status', [LlmFallbackController::class, 'status']);
    Route::post('/llm/fallback/reset', [LlmFallbackController::class, 'reset']);

    // ── 用量计量系统 (M2-10) ──
    Route::get('/usage/metrics', [UsageMeterController::class, 'metrics']);
    Route::post('/usage/record', [UsageMeterController::class, 'record']);
    Route::post('/usage/record-batch', [UsageMeterController::class, 'recordBatch']);
    Route::post('/usage/check-quota', [UsageMeterController::class, 'checkQuota']);
    Route::get('/usage/stats', [UsageMeterController::class, 'stats']);
    Route::get('/usage/current', [UsageMeterController::class, 'currentUsage']);
    Route::get('/usage/overview', [UsageMeterController::class, 'overview']);
    Route::get('/usage/quotas', [UsageMeterController::class, 'quotas']);
    Route::post('/usage/quotas', [UsageMeterController::class, 'upsertQuota']);
    Route::delete('/usage/quotas/{id}', [UsageMeterController::class, 'deleteQuota'])->whereNumber('id');

    // ── 多币种定价系统 (M2-30) ──
    Route::get('/currencies', [CurrencyController::class, 'currencies']);
    Route::get('/currency/rates', [CurrencyController::class, 'rates']);
    Route::post('/currency/rates', [CurrencyController::class, 'setRate']);
    Route::delete('/currency/rates/{id}', [CurrencyController::class, 'deleteRate'])->whereNumber('id');
    Route::post('/currency/convert', [CurrencyController::class, 'convert']);
    Route::post('/currency/batch-convert', [CurrencyController::class, 'batchConvert']);
    Route::post('/currency/sync-rates', [CurrencyController::class, 'syncRates']);

    // ── 定价计划 (多币种) ──
    Route::get('/currency/pricing-plans', [CurrencyController::class, 'pricingPlans']);
    Route::post('/currency/pricing-plans', [CurrencyController::class, 'createPricingPlan']);
    Route::put('/currency/pricing-plans/{id}', [CurrencyController::class, 'updatePricingPlan'])->whereNumber('id');
    Route::delete('/currency/pricing-plans/{id}', [CurrencyController::class, 'deletePricingPlan'])->whereNumber('id');

    // ── 客户货币偏好 ──
    Route::get('/currency/customer-preference', [CurrencyController::class, 'customerPreference']);
    Route::put('/currency/customer-preference', [CurrencyController::class, 'updateCustomerPreference']);
    Route::get('/currency/subscription-display/{subscriptionId}', [CurrencyController::class, 'subscriptionDisplayAmount'])->whereNumber('subscriptionId');

    // ── 客户健康度评分 (M2-29) ──
    Route::get('/health-score/dashboard', [HealthScoreController::class, 'dashboard']);
    Route::post('/health-score/calculate', [HealthScoreController::class, 'calculate']);
    Route::post('/health-score/calculate-all', [HealthScoreController::class, 'calculateAll']);
    Route::get('/health-score/customer/{customerId}', [HealthScoreController::class, 'show'])->whereNumber('customerId');
    Route::get('/health-score/customer/{customerId}/trend', [HealthScoreController::class, 'trend'])->whereNumber('customerId');
    Route::get('/health-score/list', [HealthScoreController::class, 'list']);
    Route::get('/health-score/churn-list', [HealthScoreController::class, 'churnList']);

    // ── 批量操作工具 (M2-08) ──
    Route::get('/batch/operation-types', [BatchController::class, 'operationTypes']);
    Route::post('/batch/preview', [BatchController::class, 'preview']);
    Route::post('/batch/execute', [BatchController::class, 'execute']);
    Route::get('/batch/jobs', [BatchController::class, 'index']);
    Route::get('/batch/jobs/{id}', [BatchController::class, 'show'])->whereNumber('id');
    Route::post('/batch/jobs/{id}/undo', [BatchController::class, 'undo'])->whereNumber('id');
    Route::post('/batch/jobs/{id}/export', [BatchController::class, 'export'])->whereNumber('id');

    // ── 客户分级 SLA (M2-31) ──
    Route::post('/sla/initialize', [SlaTierController::class, 'initialize']);
    Route::get('/sla/tiers', [SlaTierController::class, 'tiers']);
    Route::post('/sla/tiers', [SlaTierController::class, 'upsertTier']);
    Route::put('/sla/tiers/{id}', [SlaTierController::class, 'upsertTier'])->whereNumber('id');
    Route::delete('/sla/tiers/{id}', [SlaTierController::class, 'deleteTier'])->whereNumber('id');
    Route::get('/sla/customer/{customerId}/tier', [SlaTierController::class, 'customerTier'])->whereNumber('customerId');
    Route::post('/sla/assign', [SlaTierController::class, 'assignTier']);
    Route::delete('/sla/customer/{customerId}/tier', [SlaTierController::class, 'resetTier'])->whereNumber('customerId');
    Route::get('/sla/audit-log', [SlaTierController::class, 'auditLog']);
    Route::post('/sla/process-expired', [SlaTierController::class, 'processExpired']);

    // ── SLA 合约/指标/违约/补偿 (M3-68) ──
    Route::prefix('sla')->group(function () {
        Route::get('/dashboard', [SlaController::class, 'dashboard']);
        Route::get('/contracts', [SlaController::class, 'index']);
        Route::post('/contracts', [SlaController::class, 'store']);
        Route::get('/contracts/{id}', [SlaController::class, 'show'])->whereNumber('id');
        Route::put('/contracts/{slaContract}', [SlaController::class, 'update']);
        Route::delete('/contracts/{slaContract}', [SlaController::class, 'destroy']);
        Route::post('/contracts/from-template/{templateId}', [SlaController::class, 'createFromTemplate'])->whereNumber('templateId');

        Route::post('/contracts/{slaContract}/metrics', [SlaController::class, 'storeMetric']);
        Route::put('/metrics/{slaMetric}', [SlaController::class, 'updateMetric']);
        Route::delete('/metrics/{slaMetric}', [SlaController::class, 'destroyMetric']);
        Route::post('/contracts/{slaContract}/metrics/{slaMetric}/calculate', [SlaController::class, 'calculateCompliance']);

        Route::get('/contracts/{slaContract}/compliance-report', [SlaController::class, 'complianceReport']);
        Route::get('/metric-keys', [SlaController::class, 'metricKeys']);
        Route::get('/levels', [SlaController::class, 'levels']);

        Route::get('/breaches', [SlaController::class, 'breaches']);
        Route::post('/breaches/{slaBreach}/acknowledge', [SlaController::class, 'acknowledgeBreach']);
        Route::post('/breaches/{slaBreach}/resolve', [SlaController::class, 'resolveBreach']);

        Route::get('/compensations', [SlaController::class, 'compensations']);
        Route::get('/compensations/stats', [SlaController::class, 'compensationStats']);
        Route::post('/compensations/auto-generate', [SlaController::class, 'autoGenerateCompensations']);
        Route::post('/compensations/{slaCompensation}/approve', [SlaController::class, 'approveCompensation']);
        Route::post('/compensations/{slaCompensation}/issue', [SlaController::class, 'issueCompensation']);
        Route::post('/compensations/{slaCompensation}/reject', [SlaController::class, 'rejectCompensation']);
    });

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

    // CORS 跨域配置管理
    Route::get('/cors-configs', [CorsConfigController::class, 'index']);
    Route::post('/cors-configs', [CorsConfigController::class, 'store']);
    Route::get('/cors-configs/{corsConfig}', [CorsConfigController::class, 'show'])->whereNumber('corsConfig');
    Route::put('/cors-configs/{corsConfig}', [CorsConfigController::class, 'update'])->whereNumber('corsConfig');
    Route::delete('/cors-configs/{corsConfig}', [CorsConfigController::class, 'destroy'])->whereNumber('corsConfig');
    Route::post('/cors-configs/test', [CorsConfigController::class, 'test']);

    // CSP 配置管理
    Route::get('/csp-configs', [CspConfigController::class, 'index']);
    Route::post('/csp-configs', [CspConfigController::class, 'store']);
    Route::get('/csp-configs/{cspConfig}', [CspConfigController::class, 'show'])->whereNumber('cspConfig');
    Route::put('/csp-configs/{cspConfig}', [CspConfigController::class, 'update'])->whereNumber('cspConfig');
    Route::delete('/csp-configs/{cspConfig}', [CspConfigController::class, 'destroy'])->whereNumber('cspConfig');
    Route::post('/csp-configs/preview', [CspConfigController::class, 'preview']);

    // 维护模式管理
    Route::get('/maintenance/history', [MaintenanceModeController::class, 'history']);
    Route::post('/maintenance/enable', [MaintenanceModeController::class, 'enable']);
    Route::post('/maintenance/disable', [MaintenanceModeController::class, 'disable']);
    Route::put('/maintenance/configs/{maintenanceConfig}', [MaintenanceModeController::class, 'update'])->whereNumber('maintenanceConfig');

    // APM 应用性能监控
    Route::get('/apm/overview', [ApmController::class, 'overview']);
    Route::get('/apm/slow-requests', [ApmController::class, 'slowRequests']);
    Route::get('/apm/slowest-routes', [ApmController::class, 'slowestRoutes']);
    Route::get('/apm/records/{id}', [ApmController::class, 'show'])->whereNumber('id');
    Route::post('/apm/prune', [ApmController::class, 'prune']);
    Route::get('/apm/otel-status', [ApmController::class, 'otelStatus']);
    Route::get('/apm/config', [ApmController::class, 'config']);

    // CSP 违规报告
    Route::get('/csp-violations', [CspViolationController::class, 'index']);
    Route::get('/csp-violations/{cspViolation}', [CspViolationController::class, 'show'])->whereNumber('cspViolation');
    Route::get('/csp-violations/stats', [CspViolationController::class, 'stats']);

    // 系统公告横幅
    Route::get('/announce-banners', [AnnounceBannerController::class, 'index']);
    Route::post('/announce-banners', [AnnounceBannerController::class, 'store']);
    Route::get('/announce-banners/{announceBanner}', [AnnounceBannerController::class, 'show'])->whereNumber('announceBanner');
    Route::put('/announce-banners/{announceBanner}', [AnnounceBannerController::class, 'update'])->whereNumber('announceBanner');
    Route::delete('/announce-banners/{announceBanner}', [AnnounceBannerController::class, 'destroy'])->whereNumber('announceBanner');

    // Cookie Consent 管理
    Route::get('/cookie-consent/admin-config', [CookieConsentController::class, 'showConfig']);
    Route::put('/cookie-consent/admin-config', [CookieConsentController::class, 'updateConfig']);
    Route::get('/cookie-consent/logs', [CookieConsentController::class, 'logs']);
    Route::get('/cookie-consent/stats', [CookieConsentController::class, 'stats']);

    // 断路器监控面板
    Route::get('/circuit-breaker/status', [CircuitBreakerController::class, 'index']);
    Route::post('/circuit-breaker/reset', [CircuitBreakerController::class, 'reset']);
    Route::get('/circuit-breaker/logs', [CircuitBreakerController::class, 'logs']);

    // 模拟登录管理（仅超管可用）
    Route::post('/impersonate/start', [ImpersonateController::class, 'start'])->middleware('ability:super-admin');
    Route::post('/impersonate/stop', [ImpersonateController::class, 'stop'])->middleware('ability:super-admin');
    Route::get('/impersonate/session', [ImpersonateController::class, 'session']);
    Route::get('/impersonate/history', [ImpersonateController::class, 'history'])->middleware('ability:super-admin');
    Route::get('/impersonate/candidates', [ImpersonateController::class, 'candidates'])->middleware('ability:super-admin');

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

    }); // end api.masked
}); // end auth:sanctum group

// Knowledge Base (public)
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

// ── 商品评论管理 ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->group(function () {
    Route::get('/reviews', [\App\Http\Controllers\Api\ProductReviewController::class, 'index']);
    Route::get('/reviews/stats', [\App\Http\Controllers\Api\ProductReviewController::class, 'stats']);
    Route::get('/reviews/{id}', [\App\Http\Controllers\Api\ProductReviewController::class, 'show'])->whereNumber('id');
    Route::post('/reviews/{id}/moderate', [\App\Http\Controllers\Api\ProductReviewController::class, 'moderate'])->whereNumber('id');
    Route::post('/reviews/{id}/reply', [\App\Http\Controllers\Api\ProductReviewController::class, 'reply'])->whereNumber('id');
    Route::delete('/reviews/{id}', [\App\Http\Controllers\Api\ProductReviewController::class, 'destroy'])->whereNumber('id');
});

// ── API 版本管理 (M2-33) ──

Route::get('/api-version', [ApiVersionController::class, 'defaultInfo']);

Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('api-versions')->group(function () {
    Route::get('/', [ApiVersionController::class, 'index']);
    Route::post('/', [ApiVersionController::class, 'store']);
    Route::get('/{version}', [ApiVersionController::class, 'show']);
    Route::put('/{version}', [ApiVersionController::class, 'update']);
    Route::delete('/{version}', [ApiVersionController::class, 'destroy']);

    // 版本生命周期管理
    Route::post('/{version}/deprecate', [ApiVersionController::class, 'deprecate']);
    Route::post('/{version}/sunset', [ApiVersionController::class, 'sunset']);
    Route::post('/{version}/retire', [ApiVersionController::class, 'retire']);

    // 版本路由管理
    Route::get('/{version}/routes', [ApiVersionController::class, 'routes']);
    Route::post('/{version}/routes', [ApiVersionController::class, 'registerRoute']);
    Route::post('/{version}/routes/import', [ApiVersionController::class, 'importRoutes']);
    Route::delete('/{version}/routes/{routeId}', [ApiVersionController::class, 'deleteRoute'])->whereNumber('routeId');

    // 版本分析
    Route::get('/{version}/call-stats', [ApiVersionController::class, 'callStats']);
    Route::get('/{version}/impact-analysis', [ApiVersionController::class, 'impactAnalysis']);

    // 全局趋势
    Route::get('/usage-trend', [ApiVersionController::class, 'usageTrend']);
});

// ── SDK Telemetry 管理 (M2-32) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('telemetry')->group(function () {
    Route::get('/dashboard', [TelemetryController::class, 'dashboard']);
    Route::get('/heartbeats', [TelemetryController::class, 'heartbeats']);
    Route::get('/versions', [TelemetryController::class, 'versions']);
    Route::get('/events', [TelemetryController::class, 'events']);
    Route::get('/unhealthy', [TelemetryController::class, 'unhealthy']);
    Route::get('/trend', [TelemetryController::class, 'trend']);
});

// ── SDK 管理 (M2-18~20) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('sdk')->group(function () {
    Route::get('/versions', [SdkManagerController::class, 'versions']);
    Route::get('/example', [SdkManagerController::class, 'example']);
    Route::get('/matrix', [SdkManagerController::class, 'matrix']);
});

// ── SDK 完整性自检 (M2-17) ──
Route::prefix('sdk-integrity')->group(function () {
    // 公开端点（供 SDK 调用）
    Route::post('/submit-check', [SdkIntegrityController::class, 'submitCheck']);
    Route::post('/poll-destroy', [SdkIntegrityController::class, 'pollDestroy']);
    Route::post('/confirm-destroy', [SdkIntegrityController::class, 'confirmDestroy']);
    Route::post('/heartbeat', [SdkIntegrityController::class, 'heartbeat']);
    Route::get('/sdk-config', [SdkIntegrityController::class, 'sdkConfig']);

    // 管理端点
    Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->group(function () {
        Route::get('/dashboard', [SdkIntegrityController::class, 'dashboard']);
        Route::get('/checks', [SdkIntegrityController::class, 'checks']);
        Route::post('/issue-destroy', [SdkIntegrityController::class, 'issueDestroy']);
        Route::get('/protected-files', [SdkIntegrityController::class, 'protectedFiles']);
        Route::get('/commands', [SdkIntegrityController::class, 'commands']);
        Route::post('/commands/{id}/cancel', [SdkIntegrityController::class, 'cancelCommand'])->whereNumber('id');
        Route::post('/batch-destroy', [SdkIntegrityController::class, 'batchDestroy']);
        Route::post('/process-expired', [SdkIntegrityController::class, 'processExpired']);
    });
});

// ── SDK 版本兼容策略 (M2-16) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('sdk-version')->group(function () {
    Route::get('/dashboard', [SdkVersionController::class, 'dashboard']);
    Route::get('/', [SdkVersionController::class, 'index']);
    Route::get('/language/{language}', [SdkVersionController::class, 'languageVersions']);
    Route::get('/{id}', [SdkVersionController::class, 'show'])->whereNumber('id');
    Route::post('/', [SdkVersionController::class, 'store']);
    Route::put('/{id}', [SdkVersionController::class, 'update'])->whereNumber('id');
    Route::post('/check-upgrade', [SdkVersionController::class, 'checkUpgrade']);
    Route::post('/upgrade-path', [SdkVersionController::class, 'upgradePath']);
    Route::post('/migration-guide', [SdkVersionController::class, 'migrationGuide']);
    Route::post('/{id}/deprecate', [SdkVersionController::class, 'markDeprecated'])->whereNumber('id');
    Route::post('/{id}/sunset', [SdkVersionController::class, 'markSunset'])->whereNumber('id');
    Route::post('/seed-defaults', [SdkVersionController::class, 'seedDefaults']);
    Route::post('/process-expired', [SdkVersionController::class, 'processExpired']);
});

// ── SDK 本地缓存 (M2-17b) ──
Route::prefix('sdk-cache')->group(function () {
    // 公开端点（供 SDK 调用）
    Route::post('/report-status', [SdkLocalCacheController::class, 'reportStatus']);
    Route::post('/check-status', [SdkLocalCacheController::class, 'checkStatus']);
    Route::get('/config', [SdkLocalCacheController::class, 'getConfig']);

    // 管理端点
    Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->group(function () {
        Route::get('/dashboard', [SdkLocalCacheController::class, 'dashboard']);
        Route::get('/records', [SdkLocalCacheController::class, 'records']);
        Route::post('/invalidate-by-license', [SdkLocalCacheController::class, 'invalidateByLicense']);
        Route::post('/invalidate-by-instance', [SdkLocalCacheController::class, 'invalidateByInstance']);
        Route::get('/invalidation-logs', [SdkLocalCacheController::class, 'invalidationLogs']);
        Route::post('/batch-invalidate', [SdkLocalCacheController::class, 'batchInvalidate']);
        Route::post('/process-expired', [SdkLocalCacheController::class, 'processExpired']);
        Route::post('/records/{id}/mark-tampered', [SdkLocalCacheController::class, 'markTampered'])->whereNumber('id');
    });
});

// ── Blog / Changelog / 发布文章 (M2-18) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('blog')->group(function () {
    Route::get('/', [BlogController::class, 'index']);
    Route::get('/stats', [BlogController::class, 'stats']);
    Route::get('/{id}', [BlogController::class, 'show'])->whereNumber('id');
    Route::post('/', [BlogController::class, 'store']);
    Route::put('/{id}', [BlogController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [BlogController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/toggle-publish', [BlogController::class, 'togglePublish'])->whereNumber('id');
    Route::post('/{id}/toggle-featured', [BlogController::class, 'toggleFeatured'])->whereNumber('id');
    Route::get('/categories', [BlogController::class, 'categories']);
    Route::post('/categories', [BlogController::class, 'storeCategory']);
    Route::put('/categories/{id}', [BlogController::class, 'updateCategory'])->whereNumber('id');
    Route::delete('/categories/{id}', [BlogController::class, 'destroyCategory'])->whereNumber('id');
    Route::post('/batch/delete', [BlogController::class, 'batchDelete']);
    Route::post('/batch/publish', [BlogController::class, 'batchPublish']);
    Route::post('/batch/category', [BlogController::class, 'batchCategory']);
    Route::get('/export/csv', [BlogController::class, 'exportCsv']);
    Route::get('/subscriptions/stats', [BlogController::class, 'subscriptionStats']);
    Route::get('/subscriptions', [BlogController::class, 'subscriptionList']);
    // 博客评论
    Route::get('/{blogId}/comments', [BlogCommentController::class, 'index']);
    Route::post('/{blogId}/comments', [BlogCommentController::class, 'store']);
    Route::delete('/{blogId}/comments/{id}', [BlogCommentController::class, 'destroy']);
    Route::post('/{blogId}/comments/{id}/like', [BlogCommentController::class, 'toggleLike']);
    // 相关文章
    Route::get('/{id}/related', [BlogController::class, 'relatedPosts']);
});

// ── Prompt 模板管理 (AI-008) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('prompt-templates')->group(function () {
    Route::get('/dashboard', [PromptTemplateController::class, 'dashboard']);
    Route::get('/', [PromptTemplateController::class, 'index']);
    Route::get('/active', [PromptTemplateController::class, 'activeTemplates']);
    Route::get('/{id}', [PromptTemplateController::class, 'show'])->whereNumber('id');
    Route::post('/', [PromptTemplateController::class, 'store']);
    Route::put('/{id}', [PromptTemplateController::class, 'update'])->whereNumber('id');
    Route::post('/{id}/version', [PromptTemplateController::class, 'createVersion'])->whereNumber('id');
    Route::post('/{id}/set-active', [PromptTemplateController::class, 'setActive'])->whereNumber('id');
    Route::post('/{id}/render-test', [PromptTemplateController::class, 'renderTest'])->whereNumber('id');
    Route::delete('/{id}', [PromptTemplateController::class, 'destroy'])->whereNumber('id');
});

// ── 公开产品列表 ──
Route::prefix('public')->group(function () {
    Route::get('/products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
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

// ── 用户互动中心 (统一收藏/点赞/关注) ──
Route::middleware('auth:sanctum')->prefix('user/interactions')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\UserInteractionController::class, 'index']);
    Route::get('/reading-stats', [\App\Http\Controllers\Api\UserInteractionController::class, 'readingStats']);
    Route::get('/following-feed', [\App\Http\Controllers\Api\UserInteractionController::class, 'followingFeed']);
    Route::get('/favorites/collections', [\App\Http\Controllers\Api\UserInteractionController::class, 'favoriteCollections']);
    Route::get('/security-score', [\App\Http\Controllers\Api\UserInteractionController::class, 'securityScore']);
    Route::post('/reading-goal', [\App\Http\Controllers\Api\UserInteractionController::class, 'saveReadingGoal']);
    Route::get('/export', [\App\Http\Controllers\Api\UserInteractionController::class, 'export']);
    Route::get('/preferences', [\App\Http\Controllers\Api\UserInteractionController::class, 'getPreferences']);
    Route::post('/preferences', [\App\Http\Controllers\Api\UserInteractionController::class, 'savePreferences']);
    Route::get('/heatmap', [\App\Http\Controllers\Api\UserInteractionController::class, 'heatmap']);
    Route::get('/reading-report', [\App\Http\Controllers\Api\UserInteractionController::class, 'readingReport']);
    Route::get('/recommendations', [\App\Http\Controllers\Api\UserInteractionController::class, 'recommendations']);
    Route::post('/favorites', [\App\Http\Controllers\Api\UserInteractionController::class, 'addFavorite']);
    Route::delete('/favorites', [\App\Http\Controllers\Api\UserInteractionController::class, 'removeFavorite']);
    Route::post('/likes', [\App\Http\Controllers\Api\UserInteractionController::class, 'addLike']);
    Route::delete('/likes', [\App\Http\Controllers\Api\UserInteractionController::class, 'removeLike']);
    Route::get('/reading-queue', [\App\Http\Controllers\Api\UserInteractionController::class, 'getReadingQueue']);
    Route::post('/reading-queue', [\App\Http\Controllers\Api\UserInteractionController::class, 'addToReadingQueue']);
    Route::get('/reading-queue/check', [\App\Http\Controllers\Api\UserInteractionController::class, 'checkReadingQueueItem']);
    Route::delete('/reading-queue/{id}', [\App\Http\Controllers\Api\UserInteractionController::class, 'removeFromReadingQueue'])->whereNumber('id');
    Route::put('/reading-queue/{id}/toggle', [\App\Http\Controllers\Api\UserInteractionController::class, 'toggleReadingQueueItem'])->whereNumber('id');
    Route::put('/reading-queue/sort', [\App\Http\Controllers\Api\UserInteractionController::class, 'sortReadingQueue']);
    Route::post('/favorites', [\App\Http\Controllers\Api\UserInteractionController::class, 'addFavorite']);
    Route::delete('/favorites', [\App\Http\Controllers\Api\UserInteractionController::class, 'removeFavorite']);
    Route::post('/likes', [\App\Http\Controllers\Api\UserInteractionController::class, 'addLike']);
    Route::delete('/likes', [\App\Http\Controllers\Api\UserInteractionController::class, 'removeLike']);
    Route::get('/status', [\App\Http\Controllers\Api\UserInteractionController::class, 'status']);
});

// ── RSS 订阅 ──
Route::get('/rss/{feedType?}', [BlogController::class, 'rss']);

// ── SDK 错误码标准化文档 (M2-34) ──

// 公开端点 — SDK 可查询错误码定义
Route::get('/error-codes', [ErrorCodeController::class, 'index']);
Route::get('/error-codes/by-domain', [ErrorCodeController::class, 'byDomain']);
Route::get('/error-codes/search', [ErrorCodeController::class, 'search']);

// ─── 管理后台 API（需认证 + admin/super-admin 权限） ───
Route::middleware(['auth:sanctum', 'ability:admin,super-admin', 'tenant'])->prefix('admin')->group(function () {
    // 用户管理
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/stats', [AdminUserController::class, 'stats']);
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->whereNumber('user');
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->whereNumber('user');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->whereNumber('user');
    Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->whereNumber('user');
    Route::post('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->whereNumber('user');
    Route::post('/users/{user}/ban', [AdminAppealController::class, 'banUser'])->whereNumber('user');
    Route::post('/users/{user}/unban', [AdminAppealController::class, 'unbanUser'])->whereNumber('user');
    // 账号申诉管理
    Route::get('/appeals', [AdminAppealController::class, 'index']);
    Route::get('/appeals/stats', [AdminAppealController::class, 'stats']);
    Route::get('/appeals/{id}', [AdminAppealController::class, 'show'])->whereNumber('id');
    Route::post('/appeals/{id}/review', [AdminAppealController::class, 'review'])->whereNumber('id');
    // 自定义 Emoji 管理
    Route::get('/emoji', [EmojiController::class, 'index']);
    Route::get('/emoji/stats', [EmojiController::class, 'stats']);
    Route::get('/emoji/categories', [EmojiController::class, 'categories']);
    Route::post('/emoji', [EmojiController::class, 'store']);
    Route::post('/emoji/import', [EmojiController::class, 'import']);
    Route::put('/emoji/{id}', [EmojiController::class, 'update'])->whereNumber('id');
    Route::delete('/emoji/{id}', [EmojiController::class, 'destroy'])->whereNumber('id');
    // 租户管理
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::get('/tenants/stats', [TenantController::class, 'stats']);
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->whereNumber('tenant');
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->whereNumber('tenant');
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->whereNumber('tenant');
    Route::post('/tenants/{tenant}/toggle-status', [TenantController::class, 'toggleStatus'])->whereNumber('tenant');
    // 聊天 FAQ 管理
    Route::get('/chat-faqs', [\App\Http\Controllers\Api\ChatFaqController::class, 'index']);
    Route::post('/chat-faqs', [\App\Http\Controllers\Api\ChatFaqController::class, 'store']);
    Route::get('/chat-faqs/{faq}', [\App\Http\Controllers\Api\ChatFaqController::class, 'show']);
    Route::put('/chat-faqs/{faq}', [\App\Http\Controllers\Api\ChatFaqController::class, 'update']);
    Route::delete('/chat-faqs/{faq}', [\App\Http\Controllers\Api\ChatFaqController::class, 'destroy']);
});
Route::get('/error-codes/{code}', [ErrorCodeController::class, 'show']);

// ── CHANNEL-001~006: 频道/社区系统 ──
Route::prefix('channels')->middleware(['auth:sanctum'])->group(function () {
    // 频道 CRUD
    Route::get('/', [\App\Http\Controllers\Api\ChannelController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\ChannelController::class, 'store']);
    Route::get('/browse', [\App\Http\Controllers\Api\ChannelController::class, 'browse']);
    Route::get('/categories', [\App\Http\Controllers\Api\ChannelController::class, 'categories']);
    Route::post('/categories', [\App\Http\Controllers\Api\ChannelController::class, 'storeCategory']);
    Route::put('/categories/{id}', [\App\Http\Controllers\Api\ChannelController::class, 'updateCategory'])->whereNumber('id');
    Route::delete('/categories/{id}', [\App\Http\Controllers\Api\ChannelController::class, 'destroyCategory'])->whereNumber('id');
    Route::get('/{id}', [\App\Http\Controllers\Api\ChannelController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [\App\Http\Controllers\Api\ChannelController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [\App\Http\Controllers\Api\ChannelController::class, 'destroy'])->whereNumber('id');
    // 加入/离开
    Route::post('/{id}/join', [\App\Http\Controllers\Api\ChannelController::class, 'join'])->whereNumber('id');
    Route::post('/{id}/leave', [\App\Http\Controllers\Api\ChannelController::class, 'leave'])->whereNumber('id');
    // 成员
    Route::get('/{id}/members', [\App\Http\Controllers\Api\ChannelController::class, 'members'])->whereNumber('id');
    // 消息
    Route::get('/{id}/messages', [\App\Http\Controllers\Api\ChannelController::class, 'messages'])->whereNumber('id');
    Route::post('/{id}/messages', [\App\Http\Controllers\Api\ChannelController::class, 'sendMessage'])->whereNumber('id');
    Route::delete('/{id}/messages/{messageId}', [\App\Http\Controllers\Api\ChannelController::class, 'deleteMessage'])->whereNumber('id')->whereNumber('messageId');
    Route::post('/{id}/messages/{messageId}/recall', [\App\Http\Controllers\Api\ChannelController::class, 'recallMessage'])->whereNumber('id')->whereNumber('messageId');
    // 头像上传
    Route::post('/upload-avatar', [\App\Http\Controllers\Api\ChannelController::class, 'uploadAvatar']);
    // 置顶消息
    Route::post('/{id}/messages/{messageId}/pin', [\App\Http\Controllers\Api\ChannelController::class, 'pinMessage'])->whereNumber('id')->whereNumber('messageId');
    Route::post('/{id}/messages/{messageId}/unpin', [\App\Http\Controllers\Api\ChannelController::class, 'unpinMessage'])->whereNumber('id')->whereNumber('messageId');
    Route::get('/{id}/pinned-messages', [\App\Http\Controllers\Api\ChannelController::class, 'pinnedMessages'])->whereNumber('id');
    // 通知设置
    Route::post('/{id}/toggle-mute', [\App\Http\Controllers\Api\ChannelController::class, 'toggleMute'])->whereNumber('id');
    // 成员管理
    Route::put('/{id}/members/{memberId}/role', [\App\Http\Controllers\Api\ChannelController::class, 'updateMemberRole'])->whereNumber('id')->whereNumber('memberId');
    Route::delete('/{id}/members/{memberId}', [\App\Http\Controllers\Api\ChannelController::class, 'kickMember'])->whereNumber('id')->whereNumber('memberId');
    Route::post('/{id}/transfer', [\App\Http\Controllers\Api\ChannelController::class, 'transferOwnership'])->whereNumber('id');
    // 消息搜索
    Route::get('/{id}/messages/search', [\App\Http\Controllers\Api\ChannelController::class, 'searchMessages'])->whereNumber('id');
});

// ── 文件上传 ──
Route::prefix('admin/cloud-upload')->middleware(['auth:sanctum', 'ability:admin,super-admin', 'tenant'])->group(function () {
    Route::post('/', [\App\Http\Controllers\Api\CloudUploadController::class, 'upload']);
    Route::get('/', [\App\Http\Controllers\Api\CloudUploadController::class, 'index']);
    Route::get('/dashboard', [\App\Http\Controllers\Api\CloudUploadController::class, 'dashboard']);
    Route::get('/{cloudUpload}/url', [\App\Http\Controllers\Api\CloudUploadController::class, 'url']);
    Route::delete('/{cloudUpload}', [\App\Http\Controllers\Api\CloudUploadController::class, 'destroy']);
});

// ── 论坛系统 ──
Route::prefix('forum')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ForumController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\ForumController::class, 'store']);
    Route::get('/categories', [\App\Http\Controllers\Api\ForumController::class, 'categories']);
    Route::get('/{id}', [\App\Http\Controllers\Api\ForumController::class, 'show'])->whereNumber('id');
    Route::delete('/{id}', [\App\Http\Controllers\Api\ForumController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/reply', [\App\Http\Controllers\Api\ForumController::class, 'reply'])->whereNumber('id');
    Route::post('/{id}/like', [\App\Http\Controllers\Api\ForumController::class, 'toggleLike'])->whereNumber('id');
});

// ── 广场系统（全系统公开·小红书信息流） ──
// 公开路由 - 无需登录可查看帖子详情
Route::prefix('moments')->group(function () {
    Route::get('/public/{id}', [\App\Http\Controllers\Api\MomentController::class, 'showPublic'])->whereNumber('id');
    Route::get('/public/{id}/comments', [\App\Http\Controllers\Api\MomentController::class, 'commentsPublic'])->whereNumber('id');
});

Route::prefix('moments')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\MomentController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\MomentController::class, 'store']);
    Route::get('/my', [\App\Http\Controllers\Api\MomentController::class, 'myPosts']);
    Route::get('/tags', [\App\Http\Controllers\Api\MomentController::class, 'trendingTags']);
    Route::get('/tag-suggestions', [\App\Http\Controllers\Api\MomentController::class, 'tagSuggestions']);
    Route::get('/my-stats', [\App\Http\Controllers\Api\MomentController::class, 'myStats']);
    Route::get('/drafts', [\App\Http\Controllers\Api\MomentController::class, 'drafts']);
    Route::get('/scheduled', [\App\Http\Controllers\Api\MomentController::class, 'scheduled']);
    Route::get('/categories', [\App\Http\Controllers\Api\MomentController::class, 'categories']);
    Route::get('/following', [\App\Http\Controllers\Api\MomentController::class, 'followingFeed']);
    Route::post('/users/{user}/follow', [\App\Http\Controllers\Api\MomentController::class, 'followUser'])->whereNumber('user');
    Route::post('/users/{user}/unfollow', [\App\Http\Controllers\Api\MomentController::class, 'unfollowUser'])->whereNumber('user');
    Route::get('/users/{user}/follow-status', [\App\Http\Controllers\Api\MomentController::class, 'followStatus'])->whereNumber('user');
    Route::get('/{id}', [\App\Http\Controllers\Api\MomentController::class, 'show'])->whereNumber('id');
    Route::post('/upload', [\App\Http\Controllers\Api\MomentController::class, 'uploadImage']);
    Route::post('/upload-video', [\App\Http\Controllers\Api\MomentController::class, 'uploadVideo']);
    Route::get('/{id}/comments', [\App\Http\Controllers\Api\MomentController::class, 'comments'])->whereNumber('id');
    Route::post('/{id}/like', [\App\Http\Controllers\Api\MomentController::class, 'toggleLike'])->whereNumber('id');
    Route::post('/{id}/vote', [\App\Http\Controllers\Api\MomentController::class, 'vote'])->whereNumber('id');
    Route::post('/{id}/favorite', [\App\Http\Controllers\Api\MomentController::class, 'toggleFavorite'])->whereNumber('id');
    Route::post('/{id}/comment', [\App\Http\Controllers\Api\MomentController::class, 'comment'])->whereNumber('id');
    Route::post('/{id}/forward', [\App\Http\Controllers\Api\MomentController::class, 'forward'])->whereNumber('id');
    Route::post('/{id}/report', [\App\Http\Controllers\Api\MomentController::class, 'reportPost'])->whereNumber('id');
    Route::put('/{id}', [\App\Http\Controllers\Api\MomentController::class, 'update'])->whereNumber('id');
    Route::post('/comments/{commentId}/reply', [\App\Http\Controllers\Api\MomentController::class, 'replyComment'])->whereNumber('commentId');
    Route::delete('/comments/{commentId}', [\App\Http\Controllers\Api\MomentController::class, 'deleteComment'])->whereNumber('commentId');
    Route::delete('/{id}', [\App\Http\Controllers\Api\MomentController::class, 'destroy'])->whereNumber('id');

    // 收藏夹
    Route::get('/favorites', [\App\Http\Controllers\Api\MomentController::class, 'myFavorites']);
    Route::get('/favorites/collections', [\App\Http\Controllers\Api\MomentController::class, 'favoriteCollections']);
    Route::post('/favorites/collections', [\App\Http\Controllers\Api\MomentController::class, 'createCollection']);
    Route::put('/favorites/collections/{id}', [\App\Http\Controllers\Api\MomentController::class, 'updateCollection'])->whereNumber('id');
    Route::delete('/favorites/collections/{id}', [\App\Http\Controllers\Api\MomentController::class, 'deleteCollection'])->whereNumber('id');
    Route::post('/favorites/move', [\App\Http\Controllers\Api\MomentController::class, 'moveFavorite']);
});

// ── 公众号系统（公开路由 - 无需登录查看文章和评论） ──
Route::prefix('official-accounts')->group(function () {
    Route::get('/articles/{articleId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'articleDetail'])->whereNumber('articleId');
    Route::get('/articles/{articleId}/comments', [\App\Http\Controllers\Api\OfficialAccountController::class, 'articleCommentsPublic'])->whereNumber('articleId');
});

Route::prefix('official-accounts')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\OfficialAccountController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\OfficialAccountController::class, 'store']);
    Route::get('/categories', [\App\Http\Controllers\Api\OfficialAccountController::class, 'categories']);
    Route::post('/upload-avatar', [\App\Http\Controllers\Api\OfficialAccountController::class, 'uploadAvatar']);
    Route::get('/search', [\App\Http\Controllers\Api\OfficialAccountController::class, 'search']);
    Route::get('/my', [\App\Http\Controllers\Api\OfficialAccountController::class, 'myAccounts']);
    Route::get('/my-owned', [\App\Http\Controllers\Api\OfficialAccountController::class, 'myOwnedAccounts']);
    Route::get('/my-favorite-articles', [\App\Http\Controllers\Api\OfficialAccountController::class, 'myFavoriteArticles']);
    Route::get('/my-liked-articles', [\App\Http\Controllers\Api\OfficialAccountController::class, 'myLikedArticles']);
    Route::get('/recommendations', [\App\Http\Controllers\Api\OfficialAccountController::class, 'recommendations']);
    Route::get('/ranking', [\App\Http\Controllers\Api\OfficialAccountController::class, 'ranking']);
    Route::get('/{id}/collections', [\App\Http\Controllers\Api\OfficialAccountController::class, 'collections'])->whereNumber('id');
    Route::post('/{id}/collections', [\App\Http\Controllers\Api\OfficialAccountController::class, 'createCollection'])->whereNumber('id');
    Route::put('/collections/{id}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'updateCollection'])->whereNumber('id');
    Route::delete('/collections/{id}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'deleteCollection'])->whereNumber('id');
    Route::post('/articles/{articleId}/set-collection', [\App\Http\Controllers\Api\OfficialAccountController::class, 'setArticleCollection'])->whereNumber('articleId');
    Route::get('/reading-list', [\App\Http\Controllers\Api\OfficialAccountController::class, 'myReadingList']);
    Route::post('/reading-list', [\App\Http\Controllers\Api\OfficialAccountController::class, 'addToReadingList']);
    Route::delete('/reading-list/{articleId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'removeFromReadingList'])->whereNumber('articleId');
    Route::get('/reading-list/{articleId}/status', [\App\Http\Controllers\Api\OfficialAccountController::class, 'readingListStatus'])->whereNumber('articleId');
    Route::put('/reading-list/item/{id}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'updateReadingListItem'])->whereNumber('id');
    Route::get('/{id}/followers', [\App\Http\Controllers\Api\OfficialAccountController::class, 'followers'])->whereNumber('id');
    Route::put('/{id}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'update'])->whereNumber('id');
    Route::get('/{id}/edit-info', [\App\Http\Controllers\Api\OfficialAccountController::class, 'editInfo'])->whereNumber('id');
    Route::get('/{id}/dashboard', [\App\Http\Controllers\Api\OfficialAccountController::class, 'dashboard'])->whereNumber('id');
    Route::get('/{id}/comments', [\App\Http\Controllers\Api\OfficialAccountController::class, 'comments'])->whereNumber('id');
    Route::post('/comments/{commentId}/reply', [\App\Http\Controllers\Api\OfficialAccountController::class, 'replyComment'])->whereNumber('commentId');
    Route::delete('/comments/{commentId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'deleteComment'])->whereNumber('commentId');
    Route::post('/{id}/follow', [\App\Http\Controllers\Api\OfficialAccountController::class, 'follow'])->whereNumber('id');
    Route::post('/{id}/unfollow', [\App\Http\Controllers\Api\OfficialAccountController::class, 'unfollow'])->whereNumber('id');
    // 卖家关注（前端官网商品详情页）
    Route::post('/sellers/{id}/follow', [\App\Http\Controllers\Api\SellerFollowController::class, 'follow'])->whereNumber('id');
    Route::post('/sellers/{id}/unfollow', [\App\Http\Controllers\Api\SellerFollowController::class, 'unfollow'])->whereNumber('id');
    Route::get('/sellers/{id}/follow-status', [\App\Http\Controllers\Api\SellerFollowController::class, 'status'])->whereNumber('id');
    // 文章
    Route::get('/{id}/articles', [\App\Http\Controllers\Api\OfficialAccountController::class, 'articles'])->whereNumber('id');
    Route::post('/{id}/articles', [\App\Http\Controllers\Api\OfficialAccountController::class, 'createArticle'])->whereNumber('id');
    Route::post('/articles/{articleId}/like', [\App\Http\Controllers\Api\OfficialAccountController::class, 'toggleLike'])->whereNumber('articleId');
    Route::post('/articles/{articleId}/share', [\App\Http\Controllers\Api\OfficialAccountController::class, 'share'])->whereNumber('articleId');
    Route::post('/articles/{articleId}/comment', [\App\Http\Controllers\Api\OfficialAccountController::class, 'addComment'])->whereNumber('articleId');
    Route::post('/articles/{articleId}/favorite', [\App\Http\Controllers\Api\OfficialAccountController::class, 'toggleFavorite'])->whereNumber('articleId');
    Route::put('/articles/{articleId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'updateArticle'])->whereNumber('articleId');
    Route::delete('/articles/{articleId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'deleteArticle'])->whereNumber('articleId');
    Route::post('/articles/{articleId}/pin', [\App\Http\Controllers\Api\OfficialAccountController::class, 'togglePinArticle'])->whereNumber('articleId');
    Route::get('/articles/{articleId}/stats', [\App\Http\Controllers\Api\OfficialAccountController::class, 'articleStats'])->whereNumber('articleId');
    // 评论互动
    Route::post('/comments/{commentId}/like', [\App\Http\Controllers\Api\OfficialAccountController::class, 'toggleCommentLike'])->whereNumber('commentId');
    Route::post('/comments/{commentId}/pin', [\App\Http\Controllers\Api\OfficialAccountController::class, 'togglePinComment'])->whereNumber('commentId');
    Route::post('/comments/{commentId}/approve', [\App\Http\Controllers\Api\OfficialAccountController::class, 'approveComment'])->whereNumber('commentId');
    Route::post('/comments/{commentId}/reject', [\App\Http\Controllers\Api\OfficialAccountController::class, 'rejectComment'])->whereNumber('commentId');
    // 投稿
    Route::post('/submit', [\App\Http\Controllers\Api\OfficialAccountController::class, 'submitArticle']);
    Route::get('/my-submissions', [\App\Http\Controllers\Api\OfficialAccountController::class, 'mySubmissions']);
    Route::get('/{id}/submissions/pending', [\App\Http\Controllers\Api\OfficialAccountController::class, 'pendingSubmissions'])->whereNumber('id');
    Route::post('/submissions/{subId}/review', [\App\Http\Controllers\Api\OfficialAccountController::class, 'reviewSubmission'])->whereNumber('subId');
    // 自动回复管理
    // 自动回复管理
    Route::get('/{id}/auto-replies', [\App\Http\Controllers\Api\OfficialAccountController::class, 'autoReplies'])->whereNumber('id');
    Route::post('/{id}/auto-replies', [\App\Http\Controllers\Api\OfficialAccountController::class, 'createAutoReply'])->whereNumber('id');
    Route::put('/auto-replies/{replyId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'updateAutoReply'])->whereNumber('replyId');
    Route::delete('/auto-replies/{replyId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'deleteAutoReply'])->whereNumber('replyId');
    Route::post('/{id}/trigger-auto-reply', [\App\Http\Controllers\Api\OfficialAccountController::class, 'triggerAutoReply'])->whereNumber('id');
    // 自定义菜单管理
    Route::get('/{id}/menus', [\App\Http\Controllers\Api\OfficialAccountController::class, 'menus'])->whereNumber('id');
    Route::post('/{id}/menus', [\App\Http\Controllers\Api\OfficialAccountController::class, 'storeMenu'])->whereNumber('id');
    Route::put('/menus/{menuId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'updateMenu'])->whereNumber('menuId');
    Route::delete('/menus/{menuId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'deleteMenu'])->whereNumber('menuId');
    // 素材管理
    Route::get('/{id}/materials', [\App\Http\Controllers\Api\OfficialAccountController::class, 'materials'])->whereNumber('id');
    Route::post('/{id}/materials', [\App\Http\Controllers\Api\OfficialAccountController::class, 'storeMaterial'])->whereNumber('id');
    Route::post('/{id}/materials/upload', [\App\Http\Controllers\Api\OfficialAccountController::class, 'uploadMaterial'])->whereNumber('id');
    Route::put('/materials/{materialId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'updateMaterial'])->whereNumber('materialId');
    Route::delete('/materials/{materialId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'deleteMaterial'])->whereNumber('materialId');
    // 关注者消息
    Route::get('/{id}/messages/conversations', [\App\Http\Controllers\Api\OfficialAccountController::class, 'conversations'])->whereNumber('id');
    Route::get('/{id}/messages/{userId}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'conversationMessages'])->whereNumber('id')->whereNumber('userId');
    Route::post('/{id}/messages/{userId}/reply', [\App\Http\Controllers\Api\OfficialAccountController::class, 'replyConversation'])->whereNumber('id')->whereNumber('userId');
    Route::get('/{id}/messages/unread-count', [\App\Http\Controllers\Api\OfficialAccountController::class, 'unreadMessageCount'])->whereNumber('id');
    Route::post('/{id}/messages/send', [\App\Http\Controllers\Api\OfficialAccountController::class, 'sendMessage'])->whereNumber('id');
    // 关注二维码
    Route::get('/{id}/qr-code', [\App\Http\Controllers\Api\OfficialAccountController::class, 'qrCode'])->whereNumber('id');
    // 分类管理（管理员）
    Route::get('/admin/categories', [\App\Http\Controllers\Api\OfficialAccountController::class, 'allCategories']);
    Route::post('/admin/categories', [\App\Http\Controllers\Api\OfficialAccountController::class, 'createCategory']);
    Route::put('/admin/categories/{id}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'updateCategory'])->whereNumber('id');
    Route::delete('/admin/categories/{id}', [\App\Http\Controllers\Api\OfficialAccountController::class, 'deleteCategory'])->whereNumber('id');
});

// ── 🧠 本地大模型部署 (M3-49) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('local-llm')->group(function () {
    Route::get('/status', [LocalLLMController::class, 'status']);
    Route::get('/gpu', [LocalLLMController::class, 'gpuInfo']);
    Route::get('/hardware', [LocalLLMController::class, 'hardwareInfo']);
    Route::post('/models/pull', [LocalLLMController::class, 'pullModel']);
    Route::delete('/models/{modelName}', [LocalLLMController::class, 'deleteModel']);
    Route::get('/deployment-guide', [LocalLLMController::class, 'deploymentGuide']);
    Route::get('/instances/{providerId}/check', [LocalLLMController::class, 'checkInstance'])->whereNumber('providerId');
});

// ── 📊 AI 风控 & 行为风控 (M3-01, M3-02) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('fraud-risk')->group(function () {
    Route::post('/evaluate/{license}', [FraudRiskController::class, 'evaluateLicense'])->whereNumber('license');
    Route::post('/batch-evaluate', [FraudRiskController::class, 'batchEvaluate']);
    Route::get('/stats', [FraudRiskController::class, 'fraudStats']);
    Route::get('/anomalies', [FraudRiskController::class, 'anomalies']);
    Route::post('/analyze', [FraudRiskController::class, 'analyze']);
    Route::post('/check-ban', [FraudRiskController::class, 'checkBan']);
    Route::post('/unban', [FraudRiskController::class, 'unban']);
    Route::get('/behavior-stats', [FraudRiskController::class, 'behaviorStats']);
});

// ── 📈 客户用量看板 (M2-97) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('usage')->group(function () {
    Route::get('/overview', [UsageDashboardController::class, 'overview']);
    Route::get('/api-calls', [UsageDashboardController::class, 'apiCalls']);
    Route::get('/endpoint-stats', [UsageDashboardController::class, 'endpointStats']);
    Route::get('/features', [UsageDashboardController::class, 'features']);
});

// 受保护端点 — 管理后台使用
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('error-codes')->group(function () {
    Route::get('/stats', [ErrorCodeController::class, 'stats']);
    // 支持 locale 参数 + grouped 参数
    Route::get('/reference', [ErrorCodeController::class, 'index']);
});

// ── 蜜罐防御 (M2-03) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('honeypot')->group(function () {
    Route::get('/dashboard', [HoneypotController::class, 'dashboard']);
    Route::get('/', [HoneypotController::class, 'index']);
    Route::post('/', [HoneypotController::class, 'store']);
    Route::get('/{honeypotLicense}', [HoneypotController::class, 'show']);
    Route::post('/{honeypotLicense}/disable', [HoneypotController::class, 'disable']);
    Route::post('/{honeypotLicense}/reactivate', [HoneypotController::class, 'reactivate']);
    Route::delete('/{honeypotLicense}', [HoneypotController::class, 'destroy']);
});

// ── 水印管理 (M3-10) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('watermark')->group(function () {
    Route::get('/dashboard', [WatermarkController::class, 'dashboard']);
    Route::get('/watermarks', [WatermarkController::class, 'watermarks']);
    Route::get('/watermarks/{watermark}', [WatermarkController::class, 'showWatermark']);
    Route::post('/embed', [WatermarkController::class, 'embedWatermark']);
    Route::post('/extract', [WatermarkController::class, 'extractWatermark']);
    Route::get('/watermarks/{watermark}/trace', [WatermarkController::class, 'traceWatermark']);
    Route::get('/search', [WatermarkController::class, 'searchWatermarks']);
    Route::post('/watermarks/{watermark}/revoke', [WatermarkController::class, 'revokeWatermark']);
    Route::get('/traces', [WatermarkController::class, 'traces']);
    Route::post('/traces', [WatermarkController::class, 'storeTrace']);
    Route::get('/tamper-events', [WatermarkController::class, 'tamperEvents']);
    Route::post('/tamper-events/{event}/resolve', [WatermarkController::class, 'resolveTamperEvent']);
    Route::get('/policies', [WatermarkController::class, 'policies']);
    Route::put('/policies/{policy}', [WatermarkController::class, 'updatePolicy']);
    Route::get('/verification-stats', [WatermarkController::class, 'verificationStats']);
    Route::post('/batch-embed', [WatermarkController::class, 'batchEmbed']);
});

// ── 自动渗透测试 (M2-112) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('auto-pentest')->group(function () {
    Route::get('/status', [AutoPenTestController::class, 'status']);
    Route::post('/run-scan', [AutoPenTestController::class, 'runScan']);
    Route::post('/test-connection', [AutoPenTestController::class, 'testConnection']);
    Route::get('/ci-config', [AutoPenTestController::class, 'ciConfig']);
});

// ── 合成监控 (M2-120) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('synthetic-monitor')->group(function () {
    Route::get('/dashboard', [SyntheticMonitorController::class, 'dashboard']);
    Route::get('/regions', [SyntheticMonitorController::class, 'listRegions']);
    Route::post('/regions/seed', [SyntheticMonitorController::class, 'seedRegions']);
    Route::post('/probes', [SyntheticMonitorController::class, 'createProbe']);
    Route::get('/probes', [SyntheticMonitorController::class, 'listProbes']);
    Route::get('/regions/{regionCode}/stats', [SyntheticMonitorController::class, 'regionStats']);
    Route::get('/regions/compare', [SyntheticMonitorController::class, 'allRegionComparison']);
    Route::get('/sla-report', [SyntheticMonitorController::class, 'slaReport']);
    Route::post('/sync-status-page', [SyntheticMonitorController::class, 'syncToStatusPage']);
    Route::post('/prune', [SyntheticMonitorController::class, 'pruneResults']);
});

// ── 慢查询监控 (M2-118) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('slow-query')->group(function () {
    Route::get('/dashboard', [SlowQueryMonitorController::class, 'dashboard']);
    Route::get('/top', [SlowQueryMonitorController::class, 'topSlowQueries']);
    Route::get('/list', [SlowQueryMonitorController::class, 'list']);
    Route::get('/by-route', [SlowQueryMonitorController::class, 'byRoute']);
    Route::get('/check-alert', [SlowQueryMonitorController::class, 'checkAlert']);
    Route::post('/batch-resolve', [SlowQueryMonitorController::class, 'batchResolve']);
    Route::post('/prune', [SlowQueryMonitorController::class, 'prune']);
    Route::get('/{id}', [SlowQueryMonitorController::class, 'show'])->whereNumber('id');
    Route::get('/{id}/explain', [SlowQueryMonitorController::class, 'explain'])->whereNumber('id');
    Route::post('/{id}/resolve', [SlowQueryMonitorController::class, 'resolve'])->whereNumber('id');
});

// ── SIEM 日志导出 (M2-52) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('siem-export')->group(function () {
    Route::get('/dashboard', [SiemExportController::class, 'dashboard']);
    Route::get('/', [SiemExportController::class, 'index']);
    Route::post('/', [SiemExportController::class, 'store']);
    Route::get('/formats', [SiemExportController::class, 'formats']);
    Route::get('/format-preview/{format}', [SiemExportController::class, 'formatPreview']);
    Route::put('/{id}', [SiemExportController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [SiemExportController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/test', [SiemExportController::class, 'test'])->whereNumber('id');
    Route::post('/{id}/push', [SiemExportController::class, 'push'])->whereNumber('id');
    Route::get('/{id}/logs', [SiemExportController::class, 'logs'])->whereNumber('id');
    Route::get('/{id}/stats', [SiemExportController::class, 'stats'])->whereNumber('id');
});

// ── UTM 渠道归因 (M2-104) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('utm-tracker')->group(function () {
    Route::get('/dashboard', [UtmTrackerController::class, 'dashboard']);
    Route::get('/attribution-report', [UtmTrackerController::class, 'attributionReport']);
    Route::get('/source-detail', [UtmTrackerController::class, 'sourceDetail']);
    Route::get('/user/{userId}/history', [UtmTrackerController::class, 'userHistory']);
    Route::get('/options', [UtmTrackerController::class, 'options']);
    Route::get('/', [UtmTrackerController::class, 'index']);
});

// ── 混沌工程 (M3-80) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('chaos-engineering')->group(function () {
    Route::get('/dashboard', [ChaosEngineeringController::class, 'dashboard']);
    Route::get('/', [ChaosEngineeringController::class, 'index']);
    Route::post('/', [ChaosEngineeringController::class, 'store']);
    Route::get('/scorecard', [ChaosEngineeringController::class, 'scorecard']);
    Route::get('/gameday', [ChaosEngineeringController::class, 'gameday']);
    Route::get('/improvements', [ChaosEngineeringController::class, 'improvements']);
    Route::get('/types', [ChaosEngineeringController::class, 'types']);
    Route::get('/{id}', [ChaosEngineeringController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/execute', [ChaosEngineeringController::class, 'execute'])->whereNumber('id');
    Route::post('/{id}/rollback', [ChaosEngineeringController::class, 'rollback'])->whereNumber('id');
    Route::delete('/{id}', [ChaosEngineeringController::class, 'destroy'])->whereNumber('id');
});

// ── 兼容性测试矩阵 (M3-31) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('compat-test')->group(function () {
    Route::get('/platforms', [CompatTestController::class, 'getPlatforms']);
    Route::get('/platform-templates', [CompatTestController::class, 'getPlatformTemplates']);
    Route::post('/platforms/initialize', [CompatTestController::class, 'initializePlatforms']);
    Route::get('/suites', [CompatTestController::class, 'getSuites']);
    Route::post('/suites', [CompatTestController::class, 'createSuite']);
    Route::get('/suites/{id}', [CompatTestController::class, 'getSuiteDetail']);
    Route::post('/suites/{suiteId}/test-cases', [CompatTestController::class, 'addTestCase']);
    Route::post('/suites/{suiteId}/test-cases/bulk', [CompatTestController::class, 'bulkAddTestCases']);
    Route::post('/test-runs', [CompatTestController::class, 'createTestRun']);
    Route::post('/test-runs/{id}/start', [CompatTestController::class, 'startTestRun']);
    Route::post('/test-runs/{id}/result', [CompatTestController::class, 'recordResult']);
    Route::post('/test-runs/{id}/batch-results', [CompatTestController::class, 'recordBatchResults']);
    Route::post('/test-runs/{id}/complete', [CompatTestController::class, 'completeTestRun']);
    Route::get('/test-runs/{id}', [CompatTestController::class, 'getTestRunDetail']);
    Route::get('/test-runs', [CompatTestController::class, 'getTestRunHistory']);
    Route::get('/stats', [CompatTestController::class, 'getStats']);
});

// ── 客户合并 (M3-66) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('customer-merge')->group(function () {
    Route::post('/preview', [CustomerMergeController::class, 'preview']);
    Route::post('/execute', [CustomerMergeController::class, 'merge']);
    Route::get('/history', [CustomerMergeController::class, 'history']);
    Route::get('/history/{logId}', [CustomerMergeController::class, 'detail']);
    Route::get('/search-customers', [CustomerMergeController::class, 'searchCustomers']);
});

// ── 所有权转移 (M3-65) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('ownership-transfer')->group(function () {
    Route::get('/', [OwnershipTransferController::class, 'index']);
    Route::get('/{id}', [OwnershipTransferController::class, 'show'])->whereNumber('id');
    Route::post('/', [OwnershipTransferController::class, 'store']);
    Route::get('/stats', [OwnershipTransferController::class, 'stats']);
    Route::get('/transferables/{type}', [OwnershipTransferController::class, 'getTransferables']);
    Route::get('/search-customers', [OwnershipTransferController::class, 'searchCustomers']);
    Route::post('/{ownershipTransferRequest}/confirm-source', [OwnershipTransferController::class, 'confirmBySource'])->whereNumber('ownershipTransferRequest');
    Route::post('/{ownershipTransferRequest}/confirm-target', [OwnershipTransferController::class, 'confirmByTarget'])->whereNumber('ownershipTransferRequest');
    Route::post('/{ownershipTransferRequest}/approve', [OwnershipTransferController::class, 'approve'])->whereNumber('ownershipTransferRequest');
    Route::post('/{ownershipTransferRequest}/reject', [OwnershipTransferController::class, 'reject'])->whereNumber('ownershipTransferRequest');
    Route::post('/{ownershipTransferRequest}/cancel', [OwnershipTransferController::class, 'cancel'])->whereNumber('ownershipTransferRequest');
});

// ── License 分析引擎 (M3-09) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('license-analytics')->group(function () {
    Route::get('/dashboard', [LicenseAnalyticsController::class, 'dashboard']);
    Route::get('/geo-distribution', [LicenseAnalyticsController::class, 'geoDistribution']);
    Route::get('/activation-trend', [LicenseAnalyticsController::class, 'activationTrend']);
    Route::get('/violation-trend', [LicenseAnalyticsController::class, 'violationTrend']);
    Route::get('/utilization', [LicenseAnalyticsController::class, 'utilization']);
    Route::get('/sdk-stats', [LicenseAnalyticsController::class, 'sdkStats']);
    Route::get('/product-stats', [LicenseAnalyticsController::class, 'productStats']);
    Route::get('/heatmap', [LicenseAnalyticsController::class, 'heatmap']);
    Route::get('/violations', [LicenseAnalyticsController::class, 'violations']);
    Route::post('/detect-violations', [LicenseAnalyticsController::class, 'detectViolations']);
    Route::post('/backfill', [LicenseAnalyticsController::class, 'backfill']);
    Route::get('/violation-types', [LicenseAnalyticsController::class, 'violationTypes']);
    Route::get('/summary', [LicenseAnalyticsController::class, 'summary']);
    Route::get('/type-distribution', [LicenseAnalyticsController::class, 'typeDistribution']);
    Route::get('/status-distribution', [LicenseAnalyticsController::class, 'statusDistribution']);
    Route::get('/platform-distribution', [LicenseAnalyticsController::class, 'platformDistribution']);
    Route::get('/creation-trend', [LicenseAnalyticsController::class, 'creationTrend']);
    Route::get('/license-dashboard', [LicenseAnalyticsController::class, 'licenseDashboard']);
    Route::get('/geo-detail/{countryCode}', [LicenseAnalyticsController::class, 'geoDetail']);
});

// ── SOC2/ISO27001 合规包 (M3-69) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('compliance-pack')->group(function () {
    Route::get('/dashboard', [CompliancePackController::class, 'dashboard']);
    Route::get('/questionnaire-templates', [CompliancePackController::class, 'questionnaireTemplates']);
    Route::get('/questionnaire-responses/{reportId}', [CompliancePackController::class, 'questionnaireResponses']);
    Route::post('/questionnaire-responses/{reportId}', [CompliancePackController::class, 'submitQuestionnaire']);
    Route::get('/evidence-checklist', [CompliancePackController::class, 'evidenceChecklist']);
    Route::get('/evidence-list', [CompliancePackController::class, 'evidenceList']);
    Route::post('/collect-evidence', [CompliancePackController::class, 'collectEvidence']);
    Route::post('/batch-collect-evidence', [CompliancePackController::class, 'batchCollectEvidence']);
    Route::post('/validate-evidence/{evidenceId}', [CompliancePackController::class, 'validateEvidence']);
    Route::post('/run-gap-analysis', [CompliancePackController::class, 'runGapAnalysis']);
    Route::get('/gap-analysis-list', [CompliancePackController::class, 'gapAnalysisList']);
    Route::put('/update-remediation/{gapId}', [CompliancePackController::class, 'updateRemediation']);
    Route::get('/policy-documents', [CompliancePackController::class, 'policyDocuments']);
    Route::post('/generate-policy-document/{docId}', [CompliancePackController::class, 'generatePolicyDocument']);
    Route::get('/download-policy/{fileName}', [CompliancePackController::class, 'downloadPolicyDocument']);
    Route::get('/export-report/{reportId}', [CompliancePackController::class, 'exportReport']);
});

// ── PWA 移动端 (M3-51) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('pwa')->group(function () {
    Route::get('/dashboard', [PwaController::class, 'dashboard']);
    Route::get('/subscriptions', [PwaController::class, 'subscriptions']);
    Route::post('/send-notification', [PwaController::class, 'sendNotification']);
    Route::post('/clear-cache', [PwaController::class, 'clearCache']);
    Route::post('/update-worker', [PwaController::class, 'updateWorker']);
});

// ── 个性化推荐引擎 (Personalization & CrossSell) ──

Route::middleware(['auth:sanctum'])->prefix('personalization')->group(function () {
    Route::post('/behavior', [PersonalizationController::class, 'recordBehavior']);
    Route::get('/behavior/stats', [PersonalizationController::class, 'behaviorStats']);
    Route::get('/preference/{key}', [PersonalizationController::class, 'getPreference'])->where('key', '.*');
    Route::post('/preference', [PersonalizationController::class, 'setPreference']);
    Route::get('/preferences', [PersonalizationController::class, 'getAllPreferences']);
    Route::post('/generate', [PersonalizationController::class, 'generateRecommendations']);
    Route::get('/recommendations', [PersonalizationController::class, 'getRecommendations']);
    Route::post('/recommendations/{id}/dismiss', [PersonalizationController::class, 'dismissRecommendation']);
    Route::post('/recommendations/{id}/click', [PersonalizationController::class, 'clickRecommendation']);
    Route::post('/refresh', [PersonalizationController::class, 'refreshAllRecommendations']);
    Route::get('/homepage', [PersonalizationController::class, 'personalizedHomepage']);
    Route::get('/admin/dashboard', [PersonalizationController::class, 'adminDashboard']);
    Route::get('/event-types', [PersonalizationController::class, 'eventTypes']);
});

Route::middleware(['auth:sanctum'])->prefix('cross-sell')->group(function () {
    Route::post('/generate', [CrossSellController::class, 'generate']);
    Route::get('/recommendations', [CrossSellController::class, 'recommendations']);
    Route::get('/recommendations/{recommendation}', [CrossSellController::class, 'show']);
    Route::post('/recommendations/{recommendation}/event', [CrossSellController::class, 'recordEvent']);
    Route::get('/dashboard', [CrossSellController::class, 'dashboard']);
});

// 前端错误报告
Route::post('/errors/report', function (Illuminate\Http\Request $request) {
    try {
        Illuminate\Support\Facades\Log::warning('Frontend error report', $request->all());
    } catch (\Throwable $e) {}
    return response()->json(['success' => true]);
});

// ═══════════════════════════════════════════════════════════════
// 以下为管理员功能路由 — 控制器已存在但之前未注册路由
// ═══════════════════════════════════════════════════════════════

// ── 告警疲劳管理 (AlertManager) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('alert-manager')->group(function () {
    Route::get('/dashboard', [AlertManagerController::class, 'dashboard']);
    Route::get('/aggregate', [AlertManagerController::class, 'aggregate']);
    Route::get('/aggregation-groups', [AlertManagerController::class, 'aggregationGroups']);
    Route::get('/aggregation-groups/{groupKey}', [AlertManagerController::class, 'aggregationDetail']);
    Route::get('/silence-rules', [AlertManagerController::class, 'listSilenceRules']);
    Route::post('/silence-rules', [AlertManagerController::class, 'storeSilenceRule']);
    Route::put('/silence-rules/{id}', [AlertManagerController::class, 'updateSilenceRule'])->whereNumber('id');
    Route::delete('/silence-rules/{id}', [AlertManagerController::class, 'deleteSilenceRule'])->whereNumber('id');
    Route::post('/silence-rules/{id}/toggle', [AlertManagerController::class, 'toggleSilenceRule'])->whereNumber('id');
    Route::get('/fatigue/{ruleId}', [AlertManagerController::class, 'checkFatigue'])->whereNumber('ruleId');
    Route::post('/auto-downgrade', [AlertManagerController::class, 'autoDowngrade']);
    Route::get('/fatigue-settings', [AlertManagerController::class, 'listFatigueSettings']);
    Route::post('/fatigue-settings', [AlertManagerController::class, 'storeFatigueSetting']);
    Route::put('/fatigue-settings/{id}', [AlertManagerController::class, 'updateFatigueSetting'])->whereNumber('id');
    Route::delete('/fatigue-settings/{id}', [AlertManagerController::class, 'deleteFatigueSetting'])->whereNumber('id');
    Route::post('/digest', [AlertManagerController::class, 'generateDigest']);
    Route::get('/noise-analysis', [AlertManagerController::class, 'noiseAnalysis']);
    Route::get('/notification-stats', [AlertManagerController::class, 'notificationStats']);
});

// ── 智能告警 (Alerting) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('alerting')->group(function () {
    Route::get('/dashboard', [AlertingController::class, 'dashboard']);
    Route::get('/rules', [AlertingController::class, 'rules']);
    Route::get('/rules/{id}', [AlertingController::class, 'ruleShow'])->whereNumber('id');
    Route::post('/rules', [AlertingController::class, 'ruleStore']);
    Route::put('/rules/{alertRule}', [AlertingController::class, 'ruleUpdate']);
    Route::delete('/rules/{alertRule}', [AlertingController::class, 'ruleDestroy']);
    Route::get('/channels', [AlertingController::class, 'channels']);
    Route::post('/channels', [AlertingController::class, 'channelStore']);
    Route::put('/channels/{alertChannel}', [AlertingController::class, 'channelUpdate']);
    Route::delete('/channels/{alertChannel}', [AlertingController::class, 'channelDestroy']);
    Route::post('/channels/{alertChannel}/test', [AlertingController::class, 'testChannel']);
    Route::get('/escalations', [AlertingController::class, 'escalations']);
    Route::post('/escalations', [AlertingController::class, 'escalationStore']);
    Route::put('/escalations/{alertEscalation}', [AlertingController::class, 'escalationUpdate']);
    Route::delete('/escalations/{id}', [AlertingController::class, 'escalationDestroy'])->whereNumber('id');
    Route::get('/events', [AlertingController::class, 'events']);
    Route::get('/events/{id}', [AlertingController::class, 'eventShow'])->whereNumber('id');
    Route::post('/events/{alertEvent}/acknowledge', [AlertingController::class, 'acknowledgeEvent']);
    Route::post('/events/{alertEvent}/resolve', [AlertingController::class, 'resolveEvent']);
    Route::get('/event-stats', [AlertingController::class, 'eventStats']);
    Route::get('/metric-types', [AlertingController::class, 'metricTypes']);
    Route::get('/severities', [AlertingController::class, 'severities']);
});

// ── 异常检测 (AnomalyDetection) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('anomaly-detection')->group(function () {
    Route::get('/dashboard', [AnomalyDetectionController::class, 'dashboard']);
    Route::get('/', [AnomalyDetectionController::class, 'index']);
    Route::post('/detect', [AnomalyDetectionController::class, 'detect']);
    Route::post('/{id}/resolve', [AnomalyDetectionController::class, 'resolve'])->whereNumber('id');
    Route::get('/rules', [AnomalyDetectionController::class, 'rules']);
});

// ── API 文档门户 (ApiDocs) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('admin/api-docs')->group(function () {
    Route::get('/dashboard', [ApiDocsController::class, 'dashboard']);
    Route::get('/endpoints', [ApiDocsController::class, 'endpoints']);
    Route::get('/endpoints/{id}', [ApiDocsController::class, 'showEndpoint'])->whereNumber('id');
    Route::post('/endpoints', [ApiDocsController::class, 'createEndpoint']);
    Route::put('/endpoints/{id}', [ApiDocsController::class, 'updateEndpoint'])->whereNumber('id');
    Route::delete('/endpoints/{id}', [ApiDocsController::class, 'deleteEndpoint'])->whereNumber('id');
    Route::post('/endpoints/batch-update', [ApiDocsController::class, 'batchUpdateEndpoints']);
    Route::get('/endpoints/{id}/stats', [ApiDocsController::class, 'endpointStats'])->whereNumber('id');
    Route::get('/tags', [ApiDocsController::class, 'tags']);
    Route::get('/schemas', [ApiDocsController::class, 'schemas']);
    Route::get('/groups', [ApiDocsController::class, 'groups']);
    Route::post('/snippets', [ApiDocsController::class, 'addSnippet']);
    Route::delete('/snippets/{id}', [ApiDocsController::class, 'deleteSnippet'])->whereNumber('id');
    Route::post('/snippets/auto-generate', [ApiDocsController::class, 'autoGenerateSnippets']);
    Route::post('/test', [ApiDocsController::class, 'sendTestRequest']);
    Route::get('/test-history', [ApiDocsController::class, 'testHistory']);
    Route::get('/sdks', [ApiDocsController::class, 'sdks']);
    Route::post('/sdks/generate/{language}', [ApiDocsController::class, 'generateSdk']);
    Route::get('/changelogs', [ApiDocsController::class, 'changelogs']);
    Route::post('/changelogs', [ApiDocsController::class, 'createChangelog']);
    Route::post('/version-diff', [ApiDocsController::class, 'versionDiff']);
    Route::post('/favorites/toggle', [ApiDocsController::class, 'toggleFavorite']);
    Route::get('/favorites', [ApiDocsController::class, 'favorites']);
    Route::get('/export/openapi', [ApiDocsController::class, 'exportOpenApi']);
    Route::post('/auto-detect-changes', [ApiDocsController::class, 'autoDetectChanges']);
    Route::post('/create-snapshot', [ApiDocsController::class, 'createSnapshot']);
    Route::get('/auto-detect-history', [ApiDocsController::class, 'autoDetectHistory']);
    Route::get('/export/localized-openapi', [ApiDocsController::class, 'exportLocalizedOpenApi']);
    Route::get('/locales', [ApiDocsController::class, 'supportedLocales']);
});

// ── 审计导出中心 (AuditExport) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('audit-export')->group(function () {
    Route::get('/dashboard', [AuditExportController::class, 'dashboard']);
    Route::get('/tasks', [AuditExportController::class, 'exportTasks']);
    Route::post('/tasks', [AuditExportController::class, 'createExportTask']);
    Route::get('/tasks/{id}', [AuditExportController::class, 'showExportTask'])->whereNumber('id');
    Route::delete('/tasks/{auditExportTask}', [AuditExportController::class, 'deleteExportTask']);
    Route::get('/tasks/{auditExportTask}/download', [AuditExportController::class, 'downloadExportFile']);
    Route::get('/stream', [AuditExportController::class, 'streamExport']);
    Route::get('/schedules', [AuditExportController::class, 'schedules']);
    Route::post('/schedules', [AuditExportController::class, 'storeSchedule']);
    Route::put('/schedules/{auditExportSchedule}', [AuditExportController::class, 'updateSchedule']);
    Route::delete('/schedules/{auditExportSchedule}', [AuditExportController::class, 'destroySchedule']);
    Route::post('/schedules/{auditExportSchedule}/toggle', [AuditExportController::class, 'toggleSchedule']);
    Route::get('/archive-policies', [AuditExportController::class, 'archivePolicies']);
    Route::post('/archive-policies', [AuditExportController::class, 'upsertArchivePolicy']);
    Route::put('/archive-policies/{auditArchivePolicy}', [AuditExportController::class, 'updateArchivePolicy']);
    Route::delete('/archive-policies/{auditArchivePolicy}', [AuditExportController::class, 'destroyArchivePolicy']);
    Route::get('/archive-records', [AuditExportController::class, 'archiveRecords']);
});

// ── 审计治理中心 (AuditGovernance) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('audit-governance')->group(function () {
    Route::get('/dashboard', [AuditGovernanceController::class, 'governanceDashboard']);
    Route::get('/frameworks', [AuditGovernanceController::class, 'frameworks']);
    Route::post('/frameworks/seed', [AuditGovernanceController::class, 'seedFrameworks']);
    Route::post('/reports/generate', [AuditGovernanceController::class, 'generateReport']);
    Route::get('/reports', [AuditGovernanceController::class, 'reports']);
    Route::get('/reports/{id}', [AuditGovernanceController::class, 'showReport'])->whereNumber('id');
    Route::delete('/reports/{id}', [AuditGovernanceController::class, 'deleteReport'])->whereNumber('id');
    Route::get('/reports/{reportId}/exports', [AuditGovernanceController::class, 'reportExports'])->whereNumber('reportId');
    Route::post('/reports/{reportId}/export', [AuditGovernanceController::class, 'exportReport'])->whereNumber('reportId');
    Route::get('/tags', [AuditGovernanceController::class, 'tags']);
    Route::post('/tags', [AuditGovernanceController::class, 'createTag']);
    Route::put('/tags/{id}', [AuditGovernanceController::class, 'updateTag'])->whereNumber('id');
    Route::delete('/tags/{id}', [AuditGovernanceController::class, 'deleteTag'])->whereNumber('id');
    Route::post('/audit-logs/batch-tag', [AuditGovernanceController::class, 'batchTag']);
    Route::post('/audit-logs/{logId}/annotations', [AuditGovernanceController::class, 'addAnnotation'])->whereNumber('logId');
    Route::get('/audit-logs/{logId}/annotations', [AuditGovernanceController::class, 'annotations'])->whereNumber('logId');
    Route::delete('/audit-logs/annotations/{id}', [AuditGovernanceController::class, 'deleteAnnotation'])->whereNumber('id');
    Route::get('/batch-operations', [AuditGovernanceController::class, 'batchOperations']);
    // 数据保留
    Route::get('/retention-dashboard', [AuditGovernanceController::class, 'retentionDashboard']);
    Route::post('/cleanup', [AuditGovernanceController::class, 'executeCleanup']);
    Route::get('/cleanup-history', [AuditGovernanceController::class, 'cleanupHistory']);
    Route::get('/retention-policies', [AuditGovernanceController::class, 'retentionPolicies']);
    Route::post('/retention-policies', [AuditGovernanceController::class, 'saveRetentionPolicy']);
    Route::post('/retention-policies/{id}/toggle', [AuditGovernanceController::class, 'toggleRetentionPolicy'])->whereNumber('id');
    Route::delete('/retention-policies/{id}', [AuditGovernanceController::class, 'deleteRetentionPolicy'])->whereNumber('id');
    // 增强
    Route::get('/extended-dashboard', [AuditGovernanceController::class, 'extendedRetentionDashboard']);
    Route::post('/extended-cleanup', [AuditGovernanceController::class, 'executeExtendedCleanup']);
    Route::get('/cleanup-schedules', [AuditGovernanceController::class, 'cleanupSchedules']);
    Route::post('/cleanup-schedules', [AuditGovernanceController::class, 'saveCleanupSchedule']);
});

// ── 审计可视化分析 (AuditVisualization) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('audit-visualization')->group(function () {
    Route::get('/dashboard', [AuditVisualizationController::class, 'dashboard']);
    Route::get('/trend', [AuditVisualizationController::class, 'trend']);
    Route::get('/top-actions', [AuditVisualizationController::class, 'topActions']);
    Route::get('/top-users', [AuditVisualizationController::class, 'topUsers']);
    Route::get('/top-ips', [AuditVisualizationController::class, 'topIps']);
    Route::get('/hourly-distribution', [AuditVisualizationController::class, 'hourlyDistribution']);
    Route::get('/type-distribution', [AuditVisualizationController::class, 'typeDistribution']);
});

// ── AI 合规管理 (AiCompliance) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('ai-compliance')->group(function () {
    Route::get('/dashboard', [AiComplianceController::class, 'dashboard']);
    Route::get('/gap-analysis', [AiComplianceController::class, 'gapAnalysis']);
    Route::get('/compliance-report', [AiComplianceController::class, 'complianceReport']);
    Route::get('/systems', [AiComplianceController::class, 'listSystems']);
    Route::get('/systems/{id}', [AiComplianceController::class, 'showSystem'])->whereNumber('id');
    Route::post('/systems', [AiComplianceController::class, 'storeSystem']);
    Route::put('/systems/{id}', [AiComplianceController::class, 'updateSystem'])->whereNumber('id');
    Route::delete('/systems/{id}', [AiComplianceController::class, 'destroySystem'])->whereNumber('id');
    Route::get('/systems/{systemId}/assessments', [AiComplianceController::class, 'listAssessments'])->whereNumber('systemId');
    Route::post('/systems/{systemId}/assessments', [AiComplianceController::class, 'storeAssessment'])->whereNumber('systemId');
    Route::get('/bias-detections', [AiComplianceController::class, 'listBiasDetections']);
    Route::post('/bias-detections', [AiComplianceController::class, 'storeBiasDetection']);
    Route::post('/bias-detections/{id}/mitigate', [AiComplianceController::class, 'mitigateBias'])->whereNumber('id');
    Route::post('/bias-detections/{id}/resolve', [AiComplianceController::class, 'resolveBias'])->whereNumber('id');
    Route::get('/systems/{systemId}/training-data', [AiComplianceController::class, 'listTrainingData'])->whereNumber('systemId');
    Route::post('/systems/{systemId}/training-data', [AiComplianceController::class, 'storeTrainingData'])->whereNumber('systemId');
    Route::delete('/training-data/{id}', [AiComplianceController::class, 'destroyTrainingData'])->whereNumber('id');
    Route::get('/systems/{systemId}/disclosures', [AiComplianceController::class, 'listDisclosures'])->whereNumber('systemId');
    Route::post('/systems/{systemId}/disclosures', [AiComplianceController::class, 'storeDisclosure'])->whereNumber('systemId');
    Route::get('/decision-logs', [AiComplianceController::class, 'listDecisionLogs']);
    Route::get('/decision-logs/{id}', [AiComplianceController::class, 'showDecisionLog'])->whereNumber('id');
    Route::post('/decision-logs', [AiComplianceController::class, 'storeDecisionLog']);
    Route::get('/overrides', [AiComplianceController::class, 'overrides']);
    Route::post('/overrides', [AiComplianceController::class, 'storeOverride']);
    Route::post('/overrides/{id}/process', [AiComplianceController::class, 'processOverride'])->whereNumber('id');
});

// ── 高级搜索 (AdvancedSearch) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('advanced-search')->group(function () {
    Route::get('/filters', [AdvancedSearchController::class, 'allFilterDefinitions']);
    Route::post('/search/{page}', [AdvancedSearchController::class, 'search'])->whereNumber('page');
    Route::get('/saved', [AdvancedSearchController::class, 'savedSearches']);
    Route::post('/saved', [AdvancedSearchController::class, 'saveSearch']);
    Route::put('/saved/{id}', [AdvancedSearchController::class, 'updateSavedSearch'])->whereNumber('id');
    Route::delete('/saved/{id}', [AdvancedSearchController::class, 'deleteSavedSearch'])->whereNumber('id');
    Route::post('/saved/{id}/apply', [AdvancedSearchController::class, 'applySavedSearch'])->whereNumber('id');
    Route::get('/saved/shared', [AdvancedSearchController::class, 'sharedSearches']);
    Route::get('/saved/frequent', [AdvancedSearchController::class, 'frequentSearches']);
});

// ── 域名管理总览 (主站点域名管理) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('domain-overview')->group(function () {
    Route::get('/', [DomainOverviewController::class, 'overview']);
    Route::put('/platform', [DomainOverviewController::class, 'updatePlatform']);
    Route::put('/tenants/{tenantId}/domain', [DomainOverviewController::class, 'updateTenantDomain'])->whereNumber('tenantId');
    Route::get('/dns-status', [DomainOverviewController::class, 'dnsStatus']);
    Route::get('/domains', [DomainOverviewController::class, 'domainList']);
    Route::post('/domains/{domainId}/renew-ssl', [DomainOverviewController::class, 'renewSsl'])->whereNumber('domainId');
    Route::post('/domains/batch-renew-ssl', [DomainOverviewController::class, 'batchRenewSsl']);
});

// ── License 席位池管理 (M3-45) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('seat-pool')->group(function () {
    Route::get('/dashboard', [SeatPoolController::class, 'dashboard']);
    Route::get('/licenses', [SeatPoolController::class, 'licenses']);
    Route::get('/licenses/{id}', [SeatPoolController::class, 'licenseDetail'])->whereNumber('id');
    Route::put('/licenses/{id}/config', [SeatPoolController::class, 'updateConfig'])->whereNumber('id');
    Route::post('/batch-release-expired', [SeatPoolController::class, 'batchReleaseExpired']);
    Route::get('/assignment-history', [SeatPoolController::class, 'assignmentHistory']);
});

// ── 租户隔离管理 ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('tenant-isolation')->group(function () {
    Route::get('/dashboard', [TenantIsolationController::class, 'dashboard']);
    Route::get('/quota-plans', [TenantIsolationController::class, 'quotaPlans']);
    Route::post('/quota-plans', [TenantIsolationController::class, 'createQuotaPlan']);
    Route::put('/quota-plans/{id}', [TenantIsolationController::class, 'updateQuotaPlan'])->whereNumber('id');
    Route::delete('/quota-plans/{id}', [TenantIsolationController::class, 'deleteQuotaPlan'])->whereNumber('id');
    Route::get('/tenants/{tenantId}/quota', [TenantIsolationController::class, 'tenantQuota'])->whereNumber('tenantId');
    Route::put('/tenants/{tenantId}/quota', [TenantIsolationController::class, 'updateTenantQuota'])->whereNumber('tenantId');
    Route::post('/tenants/{tenantId}/refresh-usage', [TenantIsolationController::class, 'refreshTenantUsage'])->whereNumber('tenantId');
    Route::get('/tenants/{tenantId}/audit-logs', [TenantIsolationController::class, 'auditLogs'])->whereNumber('tenantId');
    Route::post('/audit-logs/{id}/resolve', [TenantIsolationController::class, 'resolveAuditLog'])->whereNumber('id');
    Route::get('/tenants/{tenantId}/shares', [TenantIsolationController::class, 'shares'])->whereNumber('tenantId');
    Route::post('/shares', [TenantIsolationController::class, 'createShare']);
    Route::post('/shares/{id}/revoke', [TenantIsolationController::class, 'revokeShare'])->whereNumber('id');
    Route::put('/tenants/{tenantId}/isolation-level', [TenantIsolationController::class, 'updateIsolationLevel'])->whereNumber('tenantId');
    Route::post('/batch-refresh', [TenantIsolationController::class, 'batchRefresh']);
});

// ═══════════════════════════════════════════════════════════════
// 以下为新增路由 — 控制器已存在但之前未注册路由（根据 404 解决方案）
// ═══════════════════════════════════════════════════════════════

// ── License 模板管理 (LicenseTemplate) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('license-templates')->group(function () {
    Route::get('/', [LicenseTemplateController::class, 'index']);
    Route::post('/', [LicenseTemplateController::class, 'store']);
    Route::get('/{licenseTemplate}', [LicenseTemplateController::class, 'show']);
    Route::put('/{licenseTemplate}', [LicenseTemplateController::class, 'update']);
    Route::delete('/{licenseTemplate}', [LicenseTemplateController::class, 'destroy']);
    Route::post('/{licenseTemplate}/toggle-active', [LicenseTemplateController::class, 'toggleActive']);
    // 扩展功能
    Route::get('/{templateId}/variables', [LicenseTemplateExtController::class, 'variables'])->whereNumber('templateId');
    Route::post('/{templateId}/variables', [LicenseTemplateExtController::class, 'saveVariables'])->whereNumber('templateId');
    Route::post('/{templateId}/field-mappings', [LicenseTemplateExtController::class, 'saveFieldMappings'])->whereNumber('templateId');
    Route::get('/{templateId}/with-extras', [LicenseTemplateExtController::class, 'showWithExtras'])->whereNumber('templateId');
    Route::post('/{templateId}/preview', [LicenseTemplateExtController::class, 'preview'])->whereNumber('templateId');
    Route::post('/{templateId}/batch-generate', [LicenseTemplateExtController::class, 'batchGenerate'])->whereNumber('templateId');
});
// 批量生成任务
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('batch-tasks')->group(function () {
    Route::get('/', [LicenseTemplateExtController::class, 'batchTasks']);
    Route::get('/{id}', [LicenseTemplateExtController::class, 'batchTaskShow'])->whereNumber('id');
    Route::delete('/{id}', [LicenseTemplateExtController::class, 'batchTaskDestroy'])->whereNumber('id');
});

// ── License 快照管理 (LicenseSnapshot) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('license-snapshots')->group(function () {
    Route::get('/', [LicenseSnapshotController::class, 'index']);
    Route::get('/dashboard', [LicenseSnapshotController::class, 'dashboard']);
    Route::post('/', [LicenseSnapshotController::class, 'store']);
    Route::get('/{id}', [LicenseSnapshotController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/rollback', [LicenseSnapshotController::class, 'rollback'])->whereNumber('id');
});

// ── License 回收站管理 (LicenseTrash) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('license-trash')->group(function () {
    Route::get('/', [LicenseTrashController::class, 'index']);
    Route::get('/stats', [LicenseTrashController::class, 'stats']);
    Route::post('/{id}/restore', [LicenseTrashController::class, 'restore'])->whereNumber('id');
    Route::post('/batch-restore', [LicenseTrashController::class, 'batchRestore']);
    Route::delete('/{id}/force', [LicenseTrashController::class, 'forceDelete'])->whereNumber('id');
    Route::delete('/clear', [LicenseTrashController::class, 'clear']);
});

// ── 消费预警与预算上限 (BudgetGuard) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('budget-guard')->group(function () {
    Route::get('/', [BudgetGuardController::class, 'index']);
    Route::post('/', [BudgetGuardController::class, 'store']);
    Route::get('/{id}', [BudgetGuardController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [BudgetGuardController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [BudgetGuardController::class, 'destroy'])->whereNumber('id');
    Route::get('/dashboard/data', [BudgetGuardController::class, 'dashboard']);
    Route::post('/check-spend', [BudgetGuardController::class, 'checkSpend']);
    Route::post('/request-override', [BudgetGuardController::class, 'requestOverride']);
    Route::post('/overrides/{id}/approve', [BudgetGuardController::class, 'approveOverride'])->whereNumber('id');
    Route::post('/overrides/{id}/reject', [BudgetGuardController::class, 'rejectOverride'])->whereNumber('id');
    Route::get('/overrides/pending', [BudgetGuardController::class, 'pendingOverrides']);
    Route::get('/{id}/alerts', [BudgetGuardController::class, 'alertHistory'])->whereNumber('id');
});

// ── 密钥泄露扫描 (SecretScan) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('secret-scan')->group(function () {
    Route::get('/dashboard', [SecretScanController::class, 'dashboard']);
    Route::get('/entries', [SecretScanController::class, 'entries']);
    Route::post('/scan', [SecretScanController::class, 'scan']);
    Route::post('/quick-scan', [SecretScanController::class, 'quickScan']);
    Route::post('/{id}/resolve', [SecretScanController::class, 'resolve'])->whereNumber('id');
});

// ── 告警集成 (IncidentAlerting - PagerDuty/OpsGenie) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('incident-alerting')->group(function () {
    Route::get('/status', [IncidentAlertingController::class, 'status']);
    Route::post('/test-pagerduty', [IncidentAlertingController::class, 'testPagerDuty']);
    Route::post('/test-opsgenie', [IncidentAlertingController::class, 'testOpsGenie']);
    Route::post('/send-test', [IncidentAlertingController::class, 'sendTestAlert']);
    Route::post('/push', [IncidentAlertingController::class, 'pushAlert']);
    Route::get('/pagerduty/events', [IncidentAlertingController::class, 'pagerDutyEvents']);
    Route::get('/opsgenie/alerts', [IncidentAlertingController::class, 'opsGenieAlerts']);
});

// ── 退款管理 (Refund) ──
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('refunds')->group(function () {
    Route::get('/', [RefundController::class, 'index']);
    Route::post('/', [RefundController::class, 'store']);
    Route::get('/stats', [RefundController::class, 'stats']);
    Route::get('/{refund}', [RefundController::class, 'show']);
});
// 退款风控
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('refund-risk')->group(function () {
    Route::get('/stats', [RefundController::class, 'riskStats']);
    Route::get('/rules', [RefundController::class, 'riskRules']);
    Route::put('/rules/{rule}', [RefundController::class, 'updateRiskRule']);
});
// 带风控的退款申请（普通用户可用）
Route::middleware(['auth:sanctum'])->prefix('refunds')->group(function () {
    Route::post('/with-risk', [RefundController::class, 'storeWithRisk']);
    Route::post('/{refund}/assess-risk', [RefundController::class, 'assessRisk']);
    Route::post('/{refund}/execute-decision', [RefundController::class, 'executeDecision']);
    Route::post('/{refund}/review', [RefundController::class, 'reviewRefund']);
});

// ── 🪙 积分系统 ──
Route::middleware(['auth:sanctum'])->prefix('points')->group(function () {
    Route::get('/balance', [\App\Http\Controllers\Api\PointsController::class, 'balance']);
    Route::get('/transactions', [\App\Http\Controllers\Api\PointsController::class, 'transactions']);
    Route::post('/tip', [\App\Http\Controllers\Api\PointsController::class, 'tip']);
    Route::get('/content-tips', [\App\Http\Controllers\Api\PointsController::class, 'contentTips']);
    Route::get('/content-tip-stats', [\App\Http\Controllers\Api\PointsController::class, 'contentTipStats']);
    Route::post('/share-reward', [\App\Http\Controllers\Api\PointsController::class, 'rewardShare']);
    Route::get('/share-reward-status', [\App\Http\Controllers\Api\PointsController::class, 'shareRewardStatus']);
});

// 管理员积分管理
Route::middleware(['auth:sanctum', 'ability:admin,super-admin'])->prefix('points')->group(function () {
    Route::post('/grant', [\App\Http\Controllers\Api\PointsController::class, 'adminGrant']);
    Route::get('/admin/users', [\App\Http\Controllers\Api\PointsController::class, 'adminUserList']);
    Route::get('/admin/transactions', [\App\Http\Controllers\Api\PointsController::class, 'adminTransactions']);
    Route::get('/admin/stats', [\App\Http\Controllers\Api\PointsController::class, 'adminStats']);
});

// ── 公开搜索 - 互物库 ──
Route::get('/meilisearch/unified-search', [\App\Http\Controllers\Api\MeilisearchController::class, 'unifiedSearch']);

// ── 公开定价方案 ──
Route::get('/public/pricing-plans', [\App\Http\Controllers\Public\PublicPageController::class, 'pricingPlans']);

// ── 新增页面数据接口（占位数据） ──
Route::middleware(['auth:sanctum'])->group(function () {
    AdminPageDataController::registerRoutes();
});
