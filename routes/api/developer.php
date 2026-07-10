<?php

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\ApiPlaygroundController;
use App\Http\Controllers\Api\AnnounceBannerController;
use App\Http\Controllers\Api\ApmController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\CircuitBreakerController;
use App\Http\Controllers\Api\CookieConsentController;
use App\Http\Controllers\Api\CorsConfigController;
use App\Http\Controllers\Api\CspConfigController;
use App\Http\Controllers\Api\CspViolationController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\CsmController;
use App\Http\Controllers\Api\CustomerApiKeyController;
use App\Http\Controllers\Api\DependencySecurityController;
use App\Http\Controllers\Api\DynamicPricingController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\EmailTrackingController;
use App\Http\Controllers\Api\FineGrainedApiKeyController;
use App\Http\Controllers\Api\GdprComplianceController;
use App\Http\Controllers\Api\GlobalResourceController;
use App\Http\Controllers\Api\HealthScoreController;
use App\Http\Controllers\Api\ImpersonateController;
use App\Http\Controllers\Api\LicenseFileCdnController;
use App\Http\Controllers\Api\LlmController;
use App\Http\Controllers\Api\LlmFallbackController;
use App\Http\Controllers\Api\MaintenanceModeController;
use App\Http\Controllers\Api\MrrWaterfallController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PiplComplianceController;
use App\Http\Controllers\Api\SandboxController;
use App\Http\Controllers\Api\SlaController;
use App\Http\Controllers\Api\SlaTierController;
use App\Http\Controllers\Api\StagingController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\TenantRouterController;
use App\Http\Controllers\Api\TimeRestrictionController;
use App\Http\Controllers\Api\UpdatePackageController;
use App\Http\Controllers\Api\UsageMeterController;
use App\Http\Controllers\Api\WithdrawalController;

// ── 租户切换（用户侧，非 admin CRUD） ──
Route::get('/tenants/sso-info', [TenantRouterController::class, 'ssoInfo']);
Route::post('/tenants/switch', [TenantRouterController::class, 'switch']);
Route::get('/tenants', [TenantRouterController::class, 'index']);

// ── API Key 管理 ──
Route::prefix('api-keys')->group(function () {
    Route::get('/config/tiers', [ApiKeyController::class, 'tierConfig']);
    Route::get('/stats/overview', [ApiKeyController::class, 'myUsageOverview']);
    Route::get('/audit-logs/all', [ApiKeyController::class, 'allAuditLogs']);
    Route::get('/fine-grained/sdk-endpoints', [FineGrainedApiKeyController::class, 'sdkEndpoints']);
    Route::get('/', [ApiKeyController::class, 'index']);
    Route::post('/', [ApiKeyController::class, 'store']);
    Route::get('/{apiKey}', [ApiKeyController::class, 'show'])->whereNumber('apiKey');
    Route::put('/{apiKey}', [ApiKeyController::class, 'update'])->whereNumber('apiKey');
    Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy'])->whereNumber('apiKey');
    Route::post('/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate'])->whereNumber('apiKey');
    Route::post('/{apiKey}/toggle', [ApiKeyController::class, 'toggleActive'])->whereNumber('apiKey');
    Route::get('/{apiKey}/audit-logs', [ApiKeyController::class, 'auditLogs'])->whereNumber('apiKey');
    Route::get('/{apiKey}/usage-stats', [ApiKeyController::class, 'usageStats'])->whereNumber('apiKey');
    Route::get('/{apiKey}/permissions', [FineGrainedApiKeyController::class, 'keyPermissions'])->whereNumber('apiKey');
    Route::put('/{apiKey}/permissions', [FineGrainedApiKeyController::class, 'updatePermissions'])->whereNumber('apiKey');
    Route::get('/{apiKey}/usage-stats/detail', [FineGrainedApiKeyController::class, 'keyUsageStats'])->whereNumber('apiKey');
});

// ── 客户 API Key ──
Route::prefix('customer-api-keys')->group(function () {
    Route::get('/dashboard', [CustomerApiKeyController::class, 'dashboard']);
    Route::get('/abilities', [CustomerApiKeyController::class, 'abilities']);
    Route::get('/', [CustomerApiKeyController::class, 'index']);
    Route::post('/', [CustomerApiKeyController::class, 'store']);
    Route::put('/{id}', [CustomerApiKeyController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [CustomerApiKeyController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/toggle', [CustomerApiKeyController::class, 'toggle'])->whereNumber('id');
});
Route::middleware(['ability:admin,super-admin'])->prefix('admin/customer-api-keys')->group(function () {
    Route::get('/dashboard', [CustomerApiKeyController::class, 'adminDashboard']);
    Route::get('/', [CustomerApiKeyController::class, 'adminIndex']);
    Route::delete('/{id}', [CustomerApiKeyController::class, 'adminDestroy'])->whereNumber('id');
    Route::post('/{id}/toggle', [CustomerApiKeyController::class, 'adminToggle'])->whereNumber('id');
});

// ── API Playground（受保护操作；endpoints 在 public.php） ──
Route::prefix('playground')->group(function () {
    Route::post('/execute', [ApiPlaygroundController::class, 'execute']);
    Route::post('/generate-code', [ApiPlaygroundController::class, 'generateCode']);
});

// ── 依赖安全扫描 ──
Route::prefix('deps-security')->group(function () {
    Route::get('/stats', [DependencySecurityController::class, 'stats']);
    Route::get('/config', [DependencySecurityController::class, 'config']);
    Route::post('/batch', [DependencySecurityController::class, 'batchUpdate']);
    Route::post('/scan', [DependencySecurityController::class, 'triggerScan']);
    Route::get('/', [DependencySecurityController::class, 'index']);
    Route::put('/{id}', [DependencySecurityController::class, 'updateStatus'])->whereNumber('id');
});

// ── 邮件模板 ──
Route::prefix('email-templates')->group(function () {
    Route::get('/defaults', [EmailTemplateController::class, 'defaults']);
    Route::get('/variables', [EmailTemplateController::class, 'variables']);
    Route::post('/preview', [EmailTemplateController::class, 'preview']);
    Route::post('/init-defaults', [EmailTemplateController::class, 'initDefaults']);
    Route::get('/', [EmailTemplateController::class, 'index']);
    Route::post('/', [EmailTemplateController::class, 'store']);
    Route::get('/{id}', [EmailTemplateController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [EmailTemplateController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [EmailTemplateController::class, 'destroy'])->whereNumber('id');
});

// ── 邮件追踪 ──
Route::prefix('email-tracking')->group(function () {
    Route::get('/overview', [EmailTrackingController::class, 'overview']);
    Route::get('/logs', [EmailTrackingController::class, 'logs']);
    Route::get('/bounces', [EmailTrackingController::class, 'bounceStats']);
    Route::get('/template/{templateCode}', [EmailTrackingController::class, 'templateDetail']);
});

// ── 全局资源权限 ──
Route::prefix('global-resources')->group(function () {
    Route::get('/config', [GlobalResourceController::class, 'config']);
    Route::get('/check-write', [GlobalResourceController::class, 'checkWrite']);
    Route::get('/operations', [GlobalResourceController::class, 'operations']);
    Route::post('/verify-access', [GlobalResourceController::class, 'verifyAccess']);
});

// ── License 文件 CDN（管理端） ──
Route::prefix('license-files')->group(function () {
    Route::get('/stats', [LicenseFileCdnController::class, 'stats']);
    Route::get('/logs', [LicenseFileCdnController::class, 'logs']);
    Route::post('/generate', [LicenseFileCdnController::class, 'generate']);
    Route::post('/batch-generate', [LicenseFileCdnController::class, 'batchGenerate']);
    Route::post('/revoke', [LicenseFileCdnController::class, 'revoke']);
    Route::post('/redistribute', [LicenseFileCdnController::class, 'redistribute']);
    Route::post('/rotate-key', [LicenseFileCdnController::class, 'rotateKey']);
    Route::get('/', [LicenseFileCdnController::class, 'index']);
});

// ── LLM 管理 ──
Route::prefix('llm')->group(function () {
    Route::get('/providers', [LlmController::class, 'providers']);
    Route::post('/providers', [LlmController::class, 'storeProvider']);
    Route::put('/providers/{llmProvider}', [LlmController::class, 'updateProvider'])->whereNumber('llmProvider');
    Route::post('/providers/{llmProvider}/test', [LlmController::class, 'testConnection'])->whereNumber('llmProvider');
    Route::post('/chat', [LlmController::class, 'chat']);
    Route::match(['get', 'post'], '/chat-stream', [LlmController::class, 'chatStream']);
    Route::get('/token-stats', [LlmController::class, 'tokenStats']);
    Route::get('/logs', [LlmController::class, 'logs']);
    Route::get('/health', [LlmController::class, 'healthStatus']);
    Route::post('/health/check', [LlmController::class, 'runHealthCheck']);
    Route::get('/fallback-events', [LlmController::class, 'fallbackEvents']);
    Route::get('/fallback/status', [LlmFallbackController::class, 'status']);
    Route::post('/fallback/reset', [LlmFallbackController::class, 'reset']);
});

// ── CMS 页面（管理端；公开 slug 在 public.php） ──
Route::prefix('pages')->group(function () {
    Route::get('/', [PageController::class, 'index']);
    Route::post('/', [PageController::class, 'store']);
    Route::get('/{id}', [PageController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [PageController::class, 'update'])->whereNumber('id');
    Route::post('/{id}/publish', [PageController::class, 'publish'])->whereNumber('id');
    Route::post('/{id}/draft', [PageController::class, 'draft'])->whereNumber('id');
    Route::delete('/{id}', [PageController::class, 'destroy'])->whereNumber('id');
});

// ── 沙箱环境 ──
Route::prefix('sandbox')->group(function () {
    Route::post('/create', [SandboxController::class, 'create']);
    Route::get('/status', [SandboxController::class, 'status']);
    Route::post('/reset', [SandboxController::class, 'reset']);
    Route::get('/licenses', [SandboxController::class, 'licenses']);
});

// ── CSM 客户成功管理 ──
Route::prefix('csm')->group(function () {
    Route::get('/dashboard', [CsmController::class, 'dashboard']);
    Route::get('/customers', [CsmController::class, 'customers']);
    Route::get('/customers/{id}', [CsmController::class, 'customerDetail'])->whereNumber('id');
    Route::post('/customers/{id}/calculate-health', [CsmController::class, 'calculateHealthScore'])->whereNumber('id');
    Route::post('/batch-calculate-health', [CsmController::class, 'batchCalculateHealth']);
    Route::get('/tasks', [CsmController::class, 'tasks']);
    Route::post('/tasks', [CsmController::class, 'storeTask']);
    Route::put('/tasks/{csmTask}', [CsmController::class, 'updateTask'])->whereNumber('csmTask');
    Route::delete('/tasks/{csmTask}', [CsmController::class, 'deleteTask'])->whereNumber('csmTask');
    Route::get('/communications', [CsmController::class, 'communications']);
    Route::post('/communications', [CsmController::class, 'storeCommunication']);
    Route::post('/create-renewal-reminders', [CsmController::class, 'createRenewalReminders']);
    Route::get('/health-trend', [CsmController::class, 'healthTrend']);
    Route::get('/renewal-calendar', [CsmController::class, 'renewalCalendar']);
    Route::get('/activity-timeline', [CsmController::class, 'activityTimeline']);
});

// ── 更新包（管理端；download 在 public.php） ──
Route::get('/products/{product}/updates/check', [UpdatePackageController::class, 'checkUpdate'])->whereNumber('product');
Route::get('/products/{product}/updates', [UpdatePackageController::class, 'index'])->whereNumber('product');
Route::post('/products/{product}/updates', [UpdatePackageController::class, 'store'])->whereNumber('product');
Route::get('/updates/{updatePackage}', [UpdatePackageController::class, 'show'])->whereNumber('updatePackage');
Route::post('/updates/{updatePackage}/publish', [UpdatePackageController::class, 'publish'])->whereNumber('updatePackage');
Route::post('/updates/{updatePackage}/deprecate', [UpdatePackageController::class, 'deprecate'])->whereNumber('updatePackage');
Route::delete('/updates/{updatePackage}', [UpdatePackageController::class, 'destroy'])->whereNumber('updatePackage');
Route::get('/updates/{updatePackage}/stats', [UpdatePackageController::class, 'downloadStats'])->whereNumber('updatePackage');

// ── GDPR 合规 ──
Route::prefix('gdpr')->group(function () {
    Route::post('/requests', [GdprComplianceController::class, 'submitRequest']);
    Route::get('/my-requests', [GdprComplianceController::class, 'myRequests']);
    Route::get('/requests/{request}/download', [GdprComplianceController::class, 'download'])->whereNumber('request');
    Route::get('/requests', [GdprComplianceController::class, 'index']);
    Route::get('/requests/{request}', [GdprComplianceController::class, 'show'])->whereNumber('request');
    Route::post('/requests/{request}/process', [GdprComplianceController::class, 'process'])->whereNumber('request');
    Route::post('/requests/{request}/review', [GdprComplianceController::class, 'review'])->whereNumber('request');
    Route::get('/stats', [GdprComplianceController::class, 'stats']);
    Route::get('/dpa/my-status', [GdprComplianceController::class, 'myDpaStatus']);
    Route::post('/dpa/{dpa}/sign', [GdprComplianceController::class, 'signDpa'])->whereNumber('dpa');
    Route::get('/dpa', [GdprComplianceController::class, 'dpaIndex']);
    Route::post('/dpa', [GdprComplianceController::class, 'storeDpa']);
    Route::put('/dpa/{dpa}', [GdprComplianceController::class, 'updateDpa'])->whereNumber('dpa');
    Route::post('/dpa/{dpa}/publish', [GdprComplianceController::class, 'publishDpa'])->whereNumber('dpa');
});

// ── 公告横幅（管理端；active 在 public.php） ──
Route::get('/announce-banners', [AnnounceBannerController::class, 'index']);
Route::post('/announce-banners', [AnnounceBannerController::class, 'store']);
Route::get('/announce-banners/{announceBanner}', [AnnounceBannerController::class, 'show'])->whereNumber('announceBanner');
Route::put('/announce-banners/{announceBanner}', [AnnounceBannerController::class, 'update'])->whereNumber('announceBanner');
Route::delete('/announce-banners/{announceBanner}', [AnnounceBannerController::class, 'destroy'])->whereNumber('announceBanner');

// ── APM 监控 ──
Route::get('/apm/overview', [ApmController::class, 'overview']);
Route::get('/apm/dashboard', [ApmController::class, 'dashboard']);
Route::get('/apm/config', [ApmController::class, 'config']);
Route::get('/apm/otel-status', [ApmController::class, 'otelStatus']);
Route::get('/apm/slow-requests', [ApmController::class, 'slowRequests']);
Route::get('/apm/slowest-routes', [ApmController::class, 'slowestRoutes']);
Route::post('/apm/prune', [ApmController::class, 'prune']);
Route::get('/apm/records/{id}', [ApmController::class, 'show'])->whereNumber('id');

// ── 批量操作 ──
Route::get('/batch/operation-types', [BatchController::class, 'operationTypes']);
Route::post('/batch/preview', [BatchController::class, 'preview']);
Route::post('/batch/execute', [BatchController::class, 'execute']);
Route::get('/batch/jobs', [BatchController::class, 'index']);
Route::get('/batch/jobs/{id}', [BatchController::class, 'show'])->whereNumber('id');
Route::post('/batch/jobs/{id}/undo', [BatchController::class, 'undo'])->whereNumber('id');
Route::post('/batch/jobs/{id}/export', [BatchController::class, 'export'])->whereNumber('id');

// ── 熔断器 ──
Route::get('/circuit-breaker/status', [CircuitBreakerController::class, 'index']);
Route::post('/circuit-breaker/reset', [CircuitBreakerController::class, 'reset']);
Route::get('/circuit-breaker/logs', [CircuitBreakerController::class, 'logs']);

// ── Cookie 同意（管理端；config/consent 在 public.php） ──
Route::get('/cookie-consent/admin-config', [CookieConsentController::class, 'showConfig']);
Route::put('/cookie-consent/admin-config', [CookieConsentController::class, 'updateConfig']);
Route::get('/cookie-consent/logs', [CookieConsentController::class, 'logs']);
Route::get('/cookie-consent/stats', [CookieConsentController::class, 'stats']);

// ── CORS 配置 ──
Route::post('/cors-configs/test', [CorsConfigController::class, 'test']);
Route::get('/cors-configs', [CorsConfigController::class, 'index']);
Route::post('/cors-configs', [CorsConfigController::class, 'store']);
Route::get('/cors-configs/{corsConfig}', [CorsConfigController::class, 'show'])->whereNumber('corsConfig');
Route::put('/cors-configs/{corsConfig}', [CorsConfigController::class, 'update'])->whereNumber('corsConfig');
Route::delete('/cors-configs/{corsConfig}', [CorsConfigController::class, 'destroy'])->whereNumber('corsConfig');

// ── CSP 配置 ──
Route::post('/csp-configs/preview', [CspConfigController::class, 'preview']);
Route::get('/csp-configs', [CspConfigController::class, 'index']);
Route::post('/csp-configs', [CspConfigController::class, 'store']);
Route::get('/csp-configs/{cspConfig}', [CspConfigController::class, 'show'])->whereNumber('cspConfig');
Route::put('/csp-configs/{cspConfig}', [CspConfigController::class, 'update'])->whereNumber('cspConfig');
Route::delete('/csp-configs/{cspConfig}', [CspConfigController::class, 'destroy'])->whereNumber('cspConfig');

// ── CSP 违规（管理端；report 在 public.php） ──
Route::get('/csp-violations/stats', [CspViolationController::class, 'stats']);
Route::get('/csp-violations', [CspViolationController::class, 'index']);
Route::get('/csp-violations/{cspViolation}', [CspViolationController::class, 'show'])->whereNumber('cspViolation');

// ── 多币种 ──
Route::get('/currencies', [CurrencyController::class, 'currencies']);
Route::get('/currency/rates', [CurrencyController::class, 'rates']);
Route::post('/currency/rates', [CurrencyController::class, 'setRate']);
Route::delete('/currency/rates/{id}', [CurrencyController::class, 'deleteRate'])->whereNumber('id');
Route::post('/currency/convert', [CurrencyController::class, 'convert']);
Route::post('/currency/batch-convert', [CurrencyController::class, 'batchConvert']);
Route::post('/currency/sync-rates', [CurrencyController::class, 'syncRates']);
Route::get('/currency/pricing-plans', [CurrencyController::class, 'pricingPlans']);
Route::post('/currency/pricing-plans', [CurrencyController::class, 'createPricingPlan']);
Route::put('/currency/pricing-plans/{id}', [CurrencyController::class, 'updatePricingPlan'])->whereNumber('id');
Route::delete('/currency/pricing-plans/{id}', [CurrencyController::class, 'deletePricingPlan'])->whereNumber('id');
Route::get('/currency/customer-preference', [CurrencyController::class, 'customerPreference']);
Route::put('/currency/customer-preference', [CurrencyController::class, 'updateCustomerPreference']);
Route::get('/currency/subscription-display/{subscriptionId}', [CurrencyController::class, 'subscriptionDisplayAmount'])->whereNumber('subscriptionId');

// ── 客户健康分 ──
Route::get('/health-score/dashboard', [HealthScoreController::class, 'dashboard']);
Route::post('/health-score/calculate', [HealthScoreController::class, 'calculate']);
Route::post('/health-score/calculate-all', [HealthScoreController::class, 'calculateAll']);
Route::get('/health-score/list', [HealthScoreController::class, 'list']);
Route::get('/health-score/churn-list', [HealthScoreController::class, 'churnList']);
Route::get('/health-score/customer/{customerId}/trend', [HealthScoreController::class, 'trend'])->whereNumber('customerId');
Route::get('/health-score/customer/{customerId}', [HealthScoreController::class, 'show'])->whereNumber('customerId');

// ── 模拟登录 ──
Route::post('/impersonate/start', [ImpersonateController::class, 'start']);
Route::post('/impersonate/stop', [ImpersonateController::class, 'stop']);
Route::get('/impersonate/session', [ImpersonateController::class, 'session']);
Route::get('/impersonate/history', [ImpersonateController::class, 'history']);
Route::get('/impersonate/candidates', [ImpersonateController::class, 'candidates']);

// ── 维护模式（管理端；status 在 public.php） ──
Route::post('/maintenance/enable', [MaintenanceModeController::class, 'enable']);
Route::post('/maintenance/disable', [MaintenanceModeController::class, 'disable']);
Route::get('/maintenance/history', [MaintenanceModeController::class, 'history']);
Route::put('/maintenance/configs/{maintenanceConfig}', [MaintenanceModeController::class, 'update'])->whereNumber('maintenanceConfig');

// ── SLA 合约管理 ──
Route::get('/sla/dashboard', [SlaController::class, 'dashboard']);
Route::get('/sla/metric-keys', [SlaController::class, 'metricKeys']);
Route::get('/sla/levels', [SlaController::class, 'levels']);
Route::get('/sla/contracts', [SlaController::class, 'index']);
Route::post('/sla/contracts', [SlaController::class, 'store']);
Route::post('/sla/contracts/from-template/{templateId}', [SlaController::class, 'createFromTemplate'])->whereNumber('templateId');
Route::get('/sla/contracts/{id}', [SlaController::class, 'show'])->whereNumber('id');
Route::put('/sla/contracts/{slaContract}', [SlaController::class, 'update'])->whereNumber('slaContract');
Route::delete('/sla/contracts/{slaContract}', [SlaController::class, 'destroy'])->whereNumber('slaContract');
Route::post('/sla/contracts/{slaContract}/metrics', [SlaController::class, 'storeMetric'])->whereNumber('slaContract');
Route::post('/sla/contracts/{slaContract}/metrics/{slaMetric}/calculate', [SlaController::class, 'calculateCompliance'])->whereNumber('slaContract')->whereNumber('slaMetric');
Route::get('/sla/contracts/{slaContract}/compliance-report', [SlaController::class, 'complianceReport'])->whereNumber('slaContract');
Route::put('/sla/metrics/{slaMetric}', [SlaController::class, 'updateMetric'])->whereNumber('slaMetric');
Route::delete('/sla/metrics/{slaMetric}', [SlaController::class, 'destroyMetric'])->whereNumber('slaMetric');
Route::get('/sla/breaches', [SlaController::class, 'breaches']);
Route::post('/sla/breaches/{slaBreach}/acknowledge', [SlaController::class, 'acknowledgeBreach'])->whereNumber('slaBreach');
Route::post('/sla/breaches/{slaBreach}/resolve', [SlaController::class, 'resolveBreach'])->whereNumber('slaBreach');
Route::get('/sla/compensations/stats', [SlaController::class, 'compensationStats']);
Route::post('/sla/compensations/auto-generate', [SlaController::class, 'autoGenerateCompensations']);
Route::get('/sla/compensations', [SlaController::class, 'compensations']);
Route::post('/sla/compensations/{slaCompensation}/approve', [SlaController::class, 'approveCompensation'])->whereNumber('slaCompensation');
Route::post('/sla/compensations/{slaCompensation}/issue', [SlaController::class, 'issueCompensation'])->whereNumber('slaCompensation');
Route::post('/sla/compensations/{slaCompensation}/reject', [SlaController::class, 'rejectCompensation'])->whereNumber('slaCompensation');

// ── SLA 客户分级 ──
Route::post('/sla/tiers/initialize', [SlaTierController::class, 'initialize']);
Route::get('/sla/tiers/audit-log', [SlaTierController::class, 'auditLog']);
Route::post('/sla/tiers/process-expired', [SlaTierController::class, 'processExpired']);
Route::post('/sla/tiers/assign', [SlaTierController::class, 'assignTier']);
Route::get('/sla/tiers', [SlaTierController::class, 'tiers']);
Route::post('/sla/tiers', [SlaTierController::class, 'upsertTier']);
Route::put('/sla/tiers/{id}', [SlaTierController::class, 'upsertTier'])->whereNumber('id');
Route::delete('/sla/tiers/{id}', [SlaTierController::class, 'deleteTier'])->whereNumber('id');
Route::get('/sla/tiers/customer/{customerId}', [SlaTierController::class, 'customerTier'])->whereNumber('customerId');
Route::post('/sla/tiers/customer/{customerId}/reset', [SlaTierController::class, 'resetTier'])->whereNumber('customerId');

// ── Staging 环境 ──
Route::get('/staging', [StagingController::class, 'index']);
Route::post('/staging/create', [StagingController::class, 'create']);
Route::get('/staging/{staging}/licenses', [StagingController::class, 'licenses'])->whereNumber('staging');
Route::post('/staging/{staging}/reset', [StagingController::class, 'reset'])->whereNumber('staging');
Route::put('/staging/{staging}', [StagingController::class, 'update'])->whereNumber('staging');
Route::get('/staging/{staging}', [StagingController::class, 'show'])->whereNumber('staging');

// ── 税务 ──
Route::get('/tax/countries', [TaxController::class, 'countries']);
Route::get('/tax/stats', [TaxController::class, 'stats']);
Route::get('/tax/rates', [TaxController::class, 'rates']);
Route::post('/tax/calculate', [TaxController::class, 'calculate']);
Route::get('/tax/certificates', [TaxController::class, 'certificates']);
Route::post('/tax/certificates', [TaxController::class, 'storeCertificate']);
Route::get('/tax/provider/status', [TaxController::class, 'providerStatus']);
Route::post('/tax/provider/calculate', [TaxController::class, 'providerCalculate']);
Route::get('/tax/e-invoice/status', [TaxController::class, 'eInvoiceStatus']);
Route::get('/tax/region/{countryCode}', [TaxController::class, 'regionTaxes']);
Route::put('/tax/rates/{taxRate}', [TaxController::class, 'updateRate'])->whereNumber('taxRate');
Route::put('/tax/certificates/{certificate}', [TaxController::class, 'approveCertificate'])->whereNumber('certificate');
Route::delete('/tax/certificates/{certificate}', [TaxController::class, 'destroyCertificate'])->whereNumber('certificate');
Route::get('/tax/e-invoice/{invoiceNo}', [TaxController::class, 'queryEInvoice']);
Route::post('/tax/invoices/{invoice}/e-invoice', [TaxController::class, 'issueEInvoice'])->whereNumber('invoice');
Route::post('/tax/invoices/{invoice}/credit-note', [TaxController::class, 'issueCreditNote'])->whereNumber('invoice');

// ── 用量计量（/usage/overview 已由 UsageDashboardController 注册） ──
Route::get('/usage/metrics', [UsageMeterController::class, 'metrics']);
Route::post('/usage/record', [UsageMeterController::class, 'record']);
Route::post('/usage/record-batch', [UsageMeterController::class, 'recordBatch']);
Route::post('/usage/check-quota', [UsageMeterController::class, 'checkQuota']);
Route::get('/usage/stats', [UsageMeterController::class, 'stats']);
Route::get('/usage/current', [UsageMeterController::class, 'currentUsage']);
Route::get('/usage/quotas', [UsageMeterController::class, 'quotas']);
Route::post('/usage/quotas', [UsageMeterController::class, 'upsertQuota']);
Route::delete('/usage/quotas/{id}', [UsageMeterController::class, 'deleteQuota'])->whereNumber('id');

// ── 动态定价 / A/B 实验 ──
Route::prefix('admin/pricing/dynamic')->group(function () {
    Route::get('/metadata', [DynamicPricingController::class, 'metadata']);
    Route::get('/tiers', [DynamicPricingController::class, 'tiers']);
    Route::post('/tiers', [DynamicPricingController::class, 'storeTier']);
    Route::put('/tiers/{id}', [DynamicPricingController::class, 'updateTier'])->whereNumber('id');
    Route::delete('/tiers/{id}', [DynamicPricingController::class, 'destroyTier'])->whereNumber('id');
    Route::get('/rules', [DynamicPricingController::class, 'rules']);
    Route::get('/rules/{id}', [DynamicPricingController::class, 'showRule'])->whereNumber('id');
    Route::post('/rules', [DynamicPricingController::class, 'storeRule']);
    Route::put('/rules/{id}', [DynamicPricingController::class, 'updateRule'])->whereNumber('id');
    Route::delete('/rules/{id}', [DynamicPricingController::class, 'deleteRule'])->whereNumber('id');
    Route::post('/rules/{id}/toggle', [DynamicPricingController::class, 'toggleRule'])->whereNumber('id');
    Route::post('/calculate', [DynamicPricingController::class, 'calculatePrice']);
    Route::post('/simulate', [DynamicPricingController::class, 'simulate']);
    Route::post('/optimize', [DynamicPricingController::class, 'optimize']);
    Route::get('/logs', [DynamicPricingController::class, 'applicationLogs']);
    Route::get('/experiments', [DynamicPricingController::class, 'experiments']);
    Route::post('/experiments', [DynamicPricingController::class, 'storeExperiment']);
    Route::get('/experiments/{experiment}', [DynamicPricingController::class, 'showExperiment'])->whereNumber('experiment');
    Route::put('/experiments/{experiment}', [DynamicPricingController::class, 'updateExperiment'])->whereNumber('experiment');
    Route::delete('/experiments/{experiment}', [DynamicPricingController::class, 'deleteExperiment'])->whereNumber('experiment');
    Route::post('/experiments/{experiment}/start', [DynamicPricingController::class, 'startExperiment'])->whereNumber('experiment');
    Route::post('/experiments/{experiment}/pause', [DynamicPricingController::class, 'pauseExperiment'])->whereNumber('experiment');
    Route::post('/experiments/{experiment}/complete', [DynamicPricingController::class, 'completeExperiment'])->whereNumber('experiment');
    Route::post('/experiments/{experiment}/calculate', [DynamicPricingController::class, 'calculateResults'])->whereNumber('experiment');
    Route::post('/experiments/{experiment}/assign', [DynamicPricingController::class, 'assignToExperiment'])->whereNumber('experiment');
    Route::post('/experiments/{experiment}/events', [DynamicPricingController::class, 'recordEvent'])->whereNumber('experiment');
    Route::get('/experiment-stats', [DynamicPricingController::class, 'experimentStats']);
    Route::post('/experiments/{experiment}/apply-winning', [DynamicPricingController::class, 'applyWinning'])->whereNumber('experiment');
    Route::get('/recommendations', [DynamicPricingController::class, 'recommendations']);
    Route::post('/batch-assign', [DynamicPricingController::class, 'batchAssign']);
});

// ── PIPL 合规 ──
Route::prefix('pipl')->group(function () {
    Route::get('/stats', [PiplComplianceController::class, 'stats']);
    Route::get('/enhanced-stats', [PiplComplianceController::class, 'enhancedStats']);
    Route::get('/sensitive-fields', [PiplComplianceController::class, 'sensitiveFields']);
    Route::post('/scan', [PiplComplianceController::class, 'scan']);
    Route::get('/inventory', [PiplComplianceController::class, 'inventoryIndex']);
    Route::put('/inventory/{inventory}', [PiplComplianceController::class, 'inventoryUpdate'])->whereNumber('inventory');
    Route::post('/inventory/batch-update', [PiplComplianceController::class, 'inventoryBatchUpdate']);
    Route::get('/cross-border-transfers', [PiplComplianceController::class, 'crossBorderIndex']);
    Route::post('/cross-border-transfers', [PiplComplianceController::class, 'storeCrossBorder']);
    Route::put('/cross-border-transfers/{transfer}', [PiplComplianceController::class, 'updateCrossBorder'])->whereNumber('transfer');
    Route::post('/cross-border-transfers/{transfer}/review', [PiplComplianceController::class, 'reviewCrossBorder'])->whereNumber('transfer');
    Route::get('/dpias', [PiplComplianceController::class, 'dpiaIndex']);
    Route::post('/dpias', [PiplComplianceController::class, 'storeDpia']);
    Route::get('/dpias/{dpia}', [PiplComplianceController::class, 'showDpia'])->whereNumber('dpia');
    Route::put('/dpias/{dpia}', [PiplComplianceController::class, 'updateDpia'])->whereNumber('dpia');
    Route::post('/dpias/{dpia}/complete', [PiplComplianceController::class, 'completeDpia'])->whereNumber('dpia');
    Route::get('/dpo', [PiplComplianceController::class, 'getDpo']);
    Route::put('/dpo', [PiplComplianceController::class, 'updateDpo']);
    Route::post('/check-minor', [PiplComplianceController::class, 'checkMinor']);
    Route::post('/breach-notifications', [PiplComplianceController::class, 'storeBreach']);
});

// ── MRR 瀑布图（需 admin 能力） ──
Route::middleware(['ability:admin,super-admin'])->prefix('admin/revenue')->group(function () {
    Route::get('/mrr-waterfall', [MrrWaterfallController::class, 'waterfall']);
    Route::get('/mrr-summary', [MrrWaterfallController::class, 'summary']);
    Route::get('/mrr-drilldown', [MrrWaterfallController::class, 'drilldown']);
    Route::get('/mrr-breakdown/product', [MrrWaterfallController::class, 'breakdownByProduct']);
    Route::get('/mrr-breakdown/region', [MrrWaterfallController::class, 'breakdownByRegion']);
    Route::get('/mrr-breakdown/customer-segment', [MrrWaterfallController::class, 'breakdownByCustomerSegment']);
    Route::post('/mrr-scan', [MrrWaterfallController::class, 'scanChanges']);
    Route::post('/mrr-scan-changes', [MrrWaterfallController::class, 'scanChanges']);
});

// ── 时段限制 ──
Route::prefix('time-restriction')->group(function () {
    Route::get('/metadata', [TimeRestrictionController::class, 'metadata']);
    Route::get('/stats', [TimeRestrictionController::class, 'stats']);
    Route::get('/logs', [TimeRestrictionController::class, 'globalLogs']);
    Route::get('/', [TimeRestrictionController::class, 'index']);
});
Route::prefix('licenses/{license}')->whereNumber('license')->group(function () {
    Route::get('/time-restriction', [TimeRestrictionController::class, 'show']);
    Route::post('/time-restriction', [TimeRestrictionController::class, 'save']);
    Route::delete('/time-restriction', [TimeRestrictionController::class, 'destroy']);
    Route::get('/time-restriction/logs', [TimeRestrictionController::class, 'logs']);
});

// ── 提现（用户端） ──
Route::prefix('withdrawals')->group(function () {
    Route::get('/channels', [WithdrawalController::class, 'channels']);
    Route::post('/', [WithdrawalController::class, 'requestWithdrawal']);
    Route::get('/', [WithdrawalController::class, 'myWithdrawals']);
    Route::get('/stats', [WithdrawalController::class, 'myStats']);
    Route::get('/my', [WithdrawalController::class, 'myWithdrawals']);
    Route::get('/my/stats', [WithdrawalController::class, 'myStats']);
    Route::post('/{withdrawal}/cancel', [WithdrawalController::class, 'cancelWithdrawal'])->whereNumber('withdrawal');
});

// ── 提现（管理端） ──
Route::middleware(['ability:admin,super-admin'])->prefix('admin/withdrawals')->group(function () {
    Route::get('/stats', [WithdrawalController::class, 'stats']);
    Route::get('/channels', [WithdrawalController::class, 'channels']);
    Route::get('/risk-check', [WithdrawalController::class, 'riskCheck']);
    Route::post('/release-pending', [WithdrawalController::class, 'releasePending']);
    Route::post('/batch-retry', [WithdrawalController::class, 'batchRetry']);
    Route::get('/batches', [WithdrawalController::class, 'batches']);
    Route::post('/batches', [WithdrawalController::class, 'createBatch']);
    Route::get('/batches/{payoutBatch}', [WithdrawalController::class, 'showBatch'])->whereNumber('payoutBatch');
    Route::post('/batches/{payoutBatch}/complete', [WithdrawalController::class, 'completeBatch'])->whereNumber('payoutBatch');
    Route::get('/', [WithdrawalController::class, 'index']);
    Route::get('/{withdrawal}', [WithdrawalController::class, 'show'])->whereNumber('withdrawal');
    Route::post('/{withdrawal}/review', [WithdrawalController::class, 'review'])->whereNumber('withdrawal');
    Route::post('/{withdrawal}/completed', [WithdrawalController::class, 'markCompleted'])->whereNumber('withdrawal');
    Route::post('/{withdrawal}/failed', [WithdrawalController::class, 'markFailed'])->whereNumber('withdrawal');
    Route::post('/{withdrawal}/proof', [WithdrawalController::class, 'uploadProof'])->whereNumber('withdrawal');
    Route::post('/{withdrawal}/retry', [WithdrawalController::class, 'retry'])->whereNumber('withdrawal');
});
