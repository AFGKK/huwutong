<?php

use App\Http\Controllers\Api\CrlController;
use App\Http\Controllers\Api\FilePreviewController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\HeatmapController;
use App\Http\Controllers\Api\HsmController;
use App\Http\Controllers\Api\IntrusionDetectionController;
use App\Http\Controllers\Api\InviteCodeController;
use App\Http\Controllers\Api\IstioController;
use App\Http\Controllers\Api\LarkController;
use App\Http\Controllers\Api\LicenseMarketplaceController;
use App\Http\Controllers\Api\LifecycleController;
use App\Http\Controllers\Api\LocalProxyController;
use App\Http\Controllers\Api\LogArchiverController;
use App\Http\Controllers\Api\MeteredBillingController;
use App\Http\Controllers\Api\MigrationEnhancementController;
use App\Http\Controllers\Api\MlopsController;
use App\Http\Controllers\Api\MockServerController;
use App\Http\Controllers\Api\MultiCurrencyPricingController;
use App\Http\Controllers\Api\MultiRegionController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OrderAfterSalesController;
use App\Http\Controllers\Api\PiracyTraceController;
use App\Http\Controllers\Api\PreSaleController;
use App\Http\Controllers\Api\ProductAnalyticsController;
use App\Http\Controllers\Api\ProductComparisonController;
use App\Http\Controllers\Api\ProductLocalizationController;
use App\Http\Controllers\Api\ProductSearchAdminController;
use App\Http\Controllers\Api\ProductSkuController;
use App\Http\Controllers\Api\PublicKeyVersionController;
use App\Http\Controllers\Api\QuotaAlertController;
use App\Http\Controllers\Api\RateLimitController;
use App\Http\Controllers\Api\RedisHaController;
use App\Http\Controllers\Api\RegionalComplianceController;
use App\Http\Controllers\Api\RenewalDashboardController;
use App\Http\Controllers\Api\RevenueRecognitionController;
use App\Http\Controllers\Api\ScimController;
use App\Http\Controllers\Api\SecretController;
use App\Http\Controllers\Api\SecurityHeadersController;
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SlaProbeController;
use App\Http\Controllers\Api\StaticAssetCdnController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TeamsNotifierController;
use App\Http\Controllers\Api\TextToSqlController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TokenMeterController;
use App\Http\Controllers\Api\TpmBindingController;
use App\Http\Controllers\Api\TwoPhaseCommitController;
use App\Http\Controllers\Api\UpdateCdnController;
use App\Http\Controllers\Api\UpdateSignerController;
use App\Http\Controllers\Api\VMDetectionController;
use App\Http\Controllers\Api\WatermarkTamperController;
use App\Http\Controllers\Api\WebhookFilterController;

// ── 全局搜索 ──
Route::prefix('admin/search')->group(function () {
    Route::get('/', [GlobalSearchController::class, 'search']);
    Route::get('/suggestions', [GlobalSearchController::class, 'suggestions']);
    Route::get('/engine-status', [GlobalSearchController::class, 'engineStatus']);
    Route::post('/rebuild', [GlobalSearchController::class, 'rebuild']);
    Route::get('/index-status', [GlobalSearchController::class, 'indexStatus']);
    Route::get('/recent', [GlobalSearchController::class, 'recent']);
    Route::delete('/recent', [GlobalSearchController::class, 'clearRecent']);
    Route::delete('/recent/{id}', [GlobalSearchController::class, 'deleteRecent'])->whereNumber('id');
    Route::get('/bookmarks', [GlobalSearchController::class, 'bookmarks']);
    Route::post('/bookmarks/toggle', [GlobalSearchController::class, 'toggleBookmark']);
    Route::delete('/bookmarks/{id}', [GlobalSearchController::class, 'deleteBookmark'])->whereNumber('id');
    Route::get('/preferences', [GlobalSearchController::class, 'preferences']);
    Route::put('/preferences', [GlobalSearchController::class, 'updatePreferences']);
    Route::get('/dashboard', [GlobalSearchController::class, 'dashboard']);
});

// ── 热力图 ──
Route::prefix('admin/heatmap')->group(function () {
    Route::get('/dashboard', [HeatmapController::class, 'dashboard']);
    Route::get('/data', [HeatmapController::class, 'data']);
    Route::get('/country/{countryCode}', [HeatmapController::class, 'countryDetail']);
    Route::get('/layers', [HeatmapController::class, 'layers']);
    Route::post('/layers', [HeatmapController::class, 'storeLayer']);
    Route::put('/layers/{heatmapLayer}', [HeatmapController::class, 'updateLayer']);
    Route::delete('/layers/{heatmapLayer}', [HeatmapController::class, 'deleteLayer']);
});

// ── 邀请码与渠道 ──
Route::prefix('invite-codes')->group(function () {
    Route::get('/', [InviteCodeController::class, 'index']);
    Route::post('/generate', [InviteCodeController::class, 'store']);
    Route::get('/stats', [InviteCodeController::class, 'stats']);
    Route::delete('/{inviteCode}', [InviteCodeController::class, 'destroy']);
});
Route::prefix('invite-channels')->group(function () {
    Route::get('/', [InviteCodeController::class, 'channels']);
    Route::post('/', [InviteCodeController::class, 'storeChannel']);
    Route::get('/{id}', [InviteCodeController::class, 'showChannel'])->whereNumber('id');
    Route::put('/{channel}', [InviteCodeController::class, 'updateChannel']);
    Route::delete('/{channel}', [InviteCodeController::class, 'destroyChannel']);
    Route::get('/{channelId}/dashboard', [InviteCodeController::class, 'channelDashboard'])->whereNumber('channelId');
});
Route::get('/registration-tracking', [InviteCodeController::class, 'registrations']);
Route::get('/registration-portal/config', [InviteCodeController::class, 'portalConfig']);
Route::put('/registration-portal/config', [InviteCodeController::class, 'updatePortalConfig']);
Route::post('/invite-code/validate', [InviteCodeController::class, 'validateCode']);
Route::get('/invite-overview/dashboard', [InviteCodeController::class, 'overallDashboard']);

// ── Istio 服务网格 ──
Route::prefix('admin/istio')->group(function () {
    Route::get('/dashboard', [IstioController::class, 'dashboard']);
    Route::get('/topology', [IstioController::class, 'serviceTopology']);
    Route::get('/traffic-rules', [IstioController::class, 'trafficRules']);
    Route::get('/security', [IstioController::class, 'securityPolicies']);
    Route::get('/observability', [IstioController::class, 'observability']);
    Route::get('/canary', [IstioController::class, 'canaryDeployments']);
    Route::post('/canary/deploy', [IstioController::class, 'canaryDeploy']);
    Route::post('/canary/{service}/promote', [IstioController::class, 'promoteCanary']);
    Route::post('/canary/{service}/rollback', [IstioController::class, 'rollbackCanary']);
    Route::get('/deployment-guide', [IstioController::class, 'deploymentGuide']);
});

// ── 飞书集成 ──
Route::prefix('admin/lark')->group(function () {
    Route::get('/config', [LarkController::class, 'config']);
    Route::post('/config', [LarkController::class, 'saveConfig']);
    Route::post('/test', [LarkController::class, 'testConnection']);
    Route::post('/test-message', [LarkController::class, 'sendTestMessage']);
    Route::get('/reference', [LarkController::class, 'reference']);
});

// ── License 市场 ──
Route::prefix('admin/license-marketplace')->group(function () {
    Route::get('/dashboard', [LicenseMarketplaceController::class, 'dashboard']);
    Route::get('/listings', [LicenseMarketplaceController::class, 'listings']);
    Route::post('/listings', [LicenseMarketplaceController::class, 'storeListing']);
    Route::post('/listings/{listing}/approve', [LicenseMarketplaceController::class, 'approveListing']);
    Route::post('/listings/{listing}/reject', [LicenseMarketplaceController::class, 'rejectListing']);
    Route::post('/listings/{listing}/cancel', [LicenseMarketplaceController::class, 'cancelListing']);
    Route::post('/listings/{listing}/purchase', [LicenseMarketplaceController::class, 'purchase']);
    Route::get('/transactions', [LicenseMarketplaceController::class, 'transactions']);
    Route::get('/disputes', [LicenseMarketplaceController::class, 'disputes']);
    Route::post('/disputes/{dispute}/resolve', [LicenseMarketplaceController::class, 'resolveDispute']);
    Route::get('/seller-score/{customerId}', [LicenseMarketplaceController::class, 'sellerScore'])->whereNumber('customerId');
});

// ── 客户生命周期 ──
Route::prefix('admin/lifecycle')->group(function () {
    Route::get('/dashboard', [LifecycleController::class, 'dashboard']);
    Route::get('/transitions', [LifecycleController::class, 'transitions']);
    Route::post('/transition', [LifecycleController::class, 'transition']);
    Route::post('/auto-evaluate', [LifecycleController::class, 'autoEvaluate']);
    Route::get('/customer/{customer}/score', [LifecycleController::class, 'customerScore']);
    Route::get('/customer/{customer}/suggest', [LifecycleController::class, 'suggest']);
});

// ── 本地代理节点 ──
Route::prefix('local-proxy')->group(function () {
    Route::get('/dashboard', [LocalProxyController::class, 'dashboard']);
    Route::get('/nodes', [LocalProxyController::class, 'index']);
    Route::post('/nodes/register', [LocalProxyController::class, 'register']);
    Route::post('/nodes/activate', [LocalProxyController::class, 'activate']);
    Route::get('/nodes/{id}', [LocalProxyController::class, 'show'])->whereNumber('id');
    Route::put('/nodes/{id}/status', [LocalProxyController::class, 'updateStatus'])->whereNumber('id');
    Route::put('/nodes/{id}/config', [LocalProxyController::class, 'updateConfig'])->whereNumber('id');
});

// ── 日志归档 ──
Route::prefix('log-archiver')->group(function () {
    Route::get('/dashboard', [LogArchiverController::class, 'dashboard']);
    Route::get('/tiers', [LogArchiverController::class, 'tiers']);
    Route::get('/stats', [LogArchiverController::class, 'stats']);
    Route::get('/policies', [LogArchiverController::class, 'policies']);
    Route::post('/policies', [LogArchiverController::class, 'upsertPolicy']);
    Route::post('/policies/{id}/archive', [LogArchiverController::class, 'archive'])->whereNumber('id');
    Route::get('/records', [LogArchiverController::class, 'records']);
    Route::post('/records/{id}/restore', [LogArchiverController::class, 'requestRestore'])->whereNumber('id');
    Route::post('/restore-requests/{id}/execute', [LogArchiverController::class, 'executeRestore'])->whereNumber('id');
    Route::get('/restore-requests', [LogArchiverController::class, 'restoreRequests']);
    Route::post('/restore-requests/{id}/cancel', [LogArchiverController::class, 'cancelRestore'])->whereNumber('id');
    Route::post('/process-expired', [LogArchiverController::class, 'processExpired']);
});

// ── 按量计费 ──
Route::prefix('billing/metered')->group(function () {
    Route::get('/overview', [MeteredBillingController::class, 'overview']);
    Route::get('/prices', [MeteredBillingController::class, 'prices']);
    Route::post('/prices', [MeteredBillingController::class, 'upsertPrice']);
    Route::delete('/prices/{id}', [MeteredBillingController::class, 'deletePrice'])->whereNumber('id');
    Route::get('/available-metrics', [MeteredBillingController::class, 'availableMetrics']);
    Route::get('/subscriptions', [MeteredBillingController::class, 'meteredSubscriptions']);
    Route::post('/subscriptions/{subscription}/generate-invoice', [MeteredBillingController::class, 'generateInvoice']);
    Route::put('/subscriptions/{subscription}/config', [MeteredBillingController::class, 'updateSubscriptionConfig']);
    Route::post('/batch-generate-invoices', [MeteredBillingController::class, 'batchGenerateInvoices']);
});

// ── 迁移增强 ──
Route::prefix('admin/migration-enhancement')->group(function () {
    Route::get('/dashboard', [MigrationEnhancementController::class, 'dashboard']);
    Route::get('/imports', [MigrationEnhancementController::class, 'index']);
    Route::post('/imports/api', [MigrationEnhancementController::class, 'createApiImport']);
    Route::post('/imports/file', [MigrationEnhancementController::class, 'createFileImport']);
    Route::post('/imports/{migrationImport}/run', [MigrationEnhancementController::class, 'run']);
    Route::get('/imports/{migrationImport}', [MigrationEnhancementController::class, 'show']);
    Route::get('/sources', [MigrationEnhancementController::class, 'sources']);
});

// ── MLOps ──
Route::prefix('admin/mlops')->group(function () {
    Route::get('/dashboard', [MlopsController::class, 'dashboard']);
    Route::get('/models', [MlopsController::class, 'models']);
    Route::post('/models', [MlopsController::class, 'storeModel']);
    Route::get('/models/{model}', [MlopsController::class, 'showModel']);
    Route::put('/models/{model}', [MlopsController::class, 'updateModel']);
    Route::delete('/models/{model}', [MlopsController::class, 'destroyModel']);
    Route::get('/models/{model}/versions', [MlopsController::class, 'versions']);
    Route::post('/models/{model}/versions', [MlopsController::class, 'storeVersion']);
    Route::post('/models/{model}/versions/{version}/deploy', [MlopsController::class, 'deployVersion']);
    Route::post('/models/{model}/rollback/{version}', [MlopsController::class, 'rollbackVersion']);
    Route::get('/training-jobs', [MlopsController::class, 'trainingJobs']);
    Route::post('/models/{model}/train', [MlopsController::class, 'submitTraining']);
    Route::get('/drift-events', [MlopsController::class, 'driftEvents']);
    Route::get('/drift-summary', [MlopsController::class, 'driftSummary']);
    Route::post('/models/{model}/detect-drift', [MlopsController::class, 'detectDrift']);
});

// ── Mock 服务器 ──
Route::prefix('admin/mock-server')->group(function () {
    Route::get('/rules', [MockServerController::class, 'rules']);
    Route::post('/rules', [MockServerController::class, 'store']);
    Route::put('/rules/{mockRule}', [MockServerController::class, 'update']);
    Route::delete('/rules/{mockRule}', [MockServerController::class, 'destroy']);
    Route::post('/import', [MockServerController::class, 'import']);
    Route::get('/templates', [MockServerController::class, 'templates']);
    Route::get('/config', [MockServerController::class, 'config']);
});

// ── 多币种定价 ──
Route::prefix('admin/multi-currency-pricing')->group(function () {
    Route::get('/dashboard', [MultiCurrencyPricingController::class, 'dashboard']);
    Route::get('/skus', [MultiCurrencyPricingController::class, 'enabledSkus']);
    Route::get('/skus/{skuId}/prices', [MultiCurrencyPricingController::class, 'skuPrices'])->whereNumber('skuId');
    Route::put('/skus/{skuId}/prices', [MultiCurrencyPricingController::class, 'updateSkuPrices'])->whereNumber('skuId');
    Route::post('/batch-update', [MultiCurrencyPricingController::class, 'batchUpdatePrices']);
    Route::post('/skus/{skuId}/disable', [MultiCurrencyPricingController::class, 'disableMultiCurrency'])->whereNumber('skuId');
});
Route::get('/products/{productId}/currency-prices', [MultiCurrencyPricingController::class, 'productPrices'])->whereNumber('productId');
Route::get('/skus/{skuId}/display-price', [MultiCurrencyPricingController::class, 'displayPrice'])->whereNumber('skuId');

// ── 多区域 ──
Route::prefix('multi-region')->group(function () {
    Route::get('/dashboard', [MultiRegionController::class, 'dashboard']);
    Route::get('/data-centers', [MultiRegionController::class, 'listDataCenters']);
    Route::post('/data-centers', [MultiRegionController::class, 'storeDataCenter']);
    Route::post('/data-centers/seed', [MultiRegionController::class, 'seedDataCenters']);
    Route::get('/data-centers/{id}', [MultiRegionController::class, 'showDataCenter'])->whereNumber('id');
    Route::put('/data-centers/{id}', [MultiRegionController::class, 'updateDataCenter'])->whereNumber('id');
    Route::delete('/data-centers/{id}', [MultiRegionController::class, 'destroyDataCenter'])->whereNumber('id');
    Route::post('/data-centers/{id}/health-check', [MultiRegionController::class, 'healthCheck'])->whereNumber('id');
    Route::get('/data-centers/{id}/health-trend', [MultiRegionController::class, 'healthTrend'])->whereNumber('id');
    Route::post('/health-check-all', [MultiRegionController::class, 'healthCheckAll']);
    Route::get('/failover-rules', [MultiRegionController::class, 'listFailoverRules']);
    Route::post('/failover-rules', [MultiRegionController::class, 'storeFailoverRule']);
    Route::get('/failover-rules/{failoverRule}', [MultiRegionController::class, 'showFailoverRule']);
    Route::put('/failover-rules/{failoverRule}', [MultiRegionController::class, 'updateFailoverRule']);
    Route::delete('/failover-rules/{failoverRule}', [MultiRegionController::class, 'destroyFailoverRule']);
    Route::post('/failover-rules/{failoverRule}/execute', [MultiRegionController::class, 'executeFailover']);
    Route::post('/failover-rules/{failoverRule}/restore', [MultiRegionController::class, 'executeRestore']);
    Route::post('/auto-failover-check', [MultiRegionController::class, 'autoFailoverCheck']);
    Route::get('/failover-logs', [MultiRegionController::class, 'listFailoverLogs']);
    Route::get('/region-deployments', [MultiRegionController::class, 'listRegionDeployments']);
    Route::post('/region-deployments', [MultiRegionController::class, 'storeRegionDeployment']);
    Route::post('/region-deployments/seed', [MultiRegionController::class, 'seedRegionDeployments']);
    Route::get('/region-deployments/{id}', [MultiRegionController::class, 'showRegionDeployment'])->whereNumber('id');
    Route::put('/region-deployments/{id}', [MultiRegionController::class, 'updateRegionDeployment'])->whereNumber('id');
    Route::delete('/region-deployments/{id}', [MultiRegionController::class, 'destroyRegionDeployment'])->whereNumber('id');
    Route::post('/data-sync', [MultiRegionController::class, 'startDataSync']);
    Route::get('/data-sync/logs', [MultiRegionController::class, 'listSyncLogs']);
    Route::post('/region-health/check-all', [MultiRegionController::class, 'checkAllRegionHealth']);
    Route::get('/region-health/trend/{regionKey}', [MultiRegionController::class, 'regionHealthTrend']);
    Route::post('/region-health/cross-check', [MultiRegionController::class, 'crossRegionHealthCheck']);
    Route::get('/optimal-region', [MultiRegionController::class, 'getOptimalRegion']);
});

// ── 通知偏好（管理端） ──
Route::prefix('admin/notification-preferences')->group(function () {
    Route::get('/', [NotificationPreferenceController::class, 'adminIndex']);
    Route::get('/stats', [NotificationPreferenceController::class, 'adminStats']);
    Route::get('/users/{userId}', [NotificationPreferenceController::class, 'adminShow'])->whereNumber('userId');
    Route::post('/batch-update', [NotificationPreferenceController::class, 'adminBatchUpdate']);
    Route::patch('/users/{userId}/general', [NotificationPreferenceController::class, 'adminUpdateUserGeneral'])->whereNumber('userId');
    Route::post('/users/{userId}/initialize', [NotificationPreferenceController::class, 'adminInitializeForUser'])->whereNumber('userId');
});

// ── 新手引导 ──
Route::prefix('onboarding')->group(function () {
    Route::get('/dashboard', [OnboardingController::class, 'dashboard']);
    Route::get('/step', [OnboardingController::class, 'currentStep']);
    Route::post('/step/{step}', [OnboardingController::class, 'completeStep']);
    Route::post('/skip', [OnboardingController::class, 'skip']);
    Route::post('/reset', [OnboardingController::class, 'resetOnboarding']);
});
Route::get('/quick-start', [OnboardingController::class, 'quickStartItems']);
Route::post('/quick-start/{itemKey}/complete', [OnboardingController::class, 'completeQuickStartItem']);
Route::get('/tutorials', [OnboardingController::class, 'tutorials']);
Route::get('/tutorials/{slug}', [OnboardingController::class, 'showTutorial']);
Route::post('/tutorials/{tutorialId}/progress', [OnboardingController::class, 'updateTutorialProgress'])->whereNumber('tutorialId');

// ── 订单售后 ──
Route::prefix('admin/order-after-sales')->group(function () {
    Route::get('/tickets', [OrderAfterSalesController::class, 'index']);
    Route::post('/tickets', [OrderAfterSalesController::class, 'createTicket']);
    Route::get('/tickets/{ticket}', [OrderAfterSalesController::class, 'show']);
    Route::get('/orders/{order}/tickets', [OrderAfterSalesController::class, 'orderTickets']);
    Route::post('/tickets/{ticket}/reply', [OrderAfterSalesController::class, 'reply']);
    Route::post('/tickets/{ticket}/resolve', [OrderAfterSalesController::class, 'resolve']);
    Route::post('/tickets/{ticket}/close', [OrderAfterSalesController::class, 'close']);
    Route::post('/tickets/{ticket}/assign', [OrderAfterSalesController::class, 'assign']);
    Route::post('/tickets/{ticket}/satisfaction', [OrderAfterSalesController::class, 'satisfaction']);
    Route::get('/reasons', [OrderAfterSalesController::class, 'reasons']);
    Route::get('/stats', [OrderAfterSalesController::class, 'stats']);
});

// ── 盗版追踪 ──
Route::prefix('admin/piracy-trace')->group(function () {
    Route::get('/dashboard', [PiracyTraceController::class, 'dashboard']);
    Route::get('/scan-tasks', [PiracyTraceController::class, 'scanTasks']);
    Route::post('/scan-tasks', [PiracyTraceController::class, 'createScan']);
    Route::post('/scan-tasks/{scanTask}/run', [PiracyTraceController::class, 'runScan']);
    Route::get('/evidence', [PiracyTraceController::class, 'evidence']);
    Route::get('/evidence/{evidence}', [PiracyTraceController::class, 'showEvidence']);
    Route::put('/evidence/{evidence}', [PiracyTraceController::class, 'updateEvidence']);
    Route::post('/evidence/{evidence}/remediate', [PiracyTraceController::class, 'autoRemediate']);
    Route::post('/evidence/{evidence}/report', [PiracyTraceController::class, 'generateReport']);
    Route::get('/reports', [PiracyTraceController::class, 'forensicReports']);
    Route::get('/reports/{report}', [PiracyTraceController::class, 'showReport']);
});

// ── 预售 ──
Route::prefix('admin/pre-sale')->group(function () {
    Route::get('/stats', [PreSaleController::class, 'stats']);
    Route::get('/orders', [PreSaleController::class, 'orders']);
    Route::post('/orders', [PreSaleController::class, 'placeOrder']);
    Route::post('/orders/{orderId}/pay-deposit', [PreSaleController::class, 'payDeposit'])->whereNumber('orderId');
    Route::post('/orders/{orderId}/pay-final', [PreSaleController::class, 'payFinal'])->whereNumber('orderId');
    Route::put('/orders/{orderId}/fulfillment', [PreSaleController::class, 'updateFulfillment'])->whereNumber('orderId');
    Route::delete('/updates/{updateId}', [PreSaleController::class, 'deleteUpdate'])->whereNumber('updateId');
    Route::get('/', [PreSaleController::class, 'index']);
    Route::post('/', [PreSaleController::class, 'store']);
    Route::get('/{id}', [PreSaleController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [PreSaleController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [PreSaleController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/publish', [PreSaleController::class, 'publish'])->whereNumber('id');
    Route::post('/{id}/cancel', [PreSaleController::class, 'cancel'])->whereNumber('id');
    Route::post('/{id}/check-status', [PreSaleController::class, 'checkStatus'])->whereNumber('id');
    Route::post('/{id}/complete', [PreSaleController::class, 'complete'])->whereNumber('id');
    Route::get('/{campaignId}/updates', [PreSaleController::class, 'updates'])->whereNumber('campaignId');
    Route::post('/{campaignId}/updates', [PreSaleController::class, 'postUpdate'])->whereNumber('campaignId');
});

// ── 产品分析 ──
Route::prefix('product-analytics')->group(function () {
    Route::get('/dashboard', [ProductAnalyticsController::class, 'dashboard']);
    Route::get('/product-ranking', [ProductAnalyticsController::class, 'productRanking']);
    Route::get('/module-usage', [ProductAnalyticsController::class, 'moduleUsage']);
    Route::get('/regional-growth', [ProductAnalyticsController::class, 'regionalGrowth']);
    Route::get('/license-trend', [ProductAnalyticsController::class, 'licenseTrend']);
    Route::get('/activation-trend', [ProductAnalyticsController::class, 'activationTrend']);
    Route::get('/heatmap', [ProductAnalyticsController::class, 'heatmap']);
    Route::get('/product-monthly-trend', [ProductAnalyticsController::class, 'productMonthlyTrend']);
    Route::get('/regional-trend', [ProductAnalyticsController::class, 'regionalTrend']);
    Route::get('/summary', [ProductAnalyticsController::class, 'summary']);
});

// ── 产品对比 ──
Route::prefix('admin/comparison')->group(function () {
    Route::get('/specs', [ProductComparisonController::class, 'adminSpecList']);
    Route::get('/products/{productId}/specs', [ProductComparisonController::class, 'productSpecs'])->whereNumber('productId');
    Route::post('/products/{productId}/spec-groups', [ProductComparisonController::class, 'createSpecGroup'])->whereNumber('productId');
    Route::put('/spec-groups/{groupId}', [ProductComparisonController::class, 'updateSpecGroup'])->whereNumber('groupId');
    Route::delete('/spec-groups/{groupId}', [ProductComparisonController::class, 'deleteSpecGroup'])->whereNumber('groupId');
    Route::post('/spec-groups/{groupId}/specs', [ProductComparisonController::class, 'createSpec'])->whereNumber('groupId');
    Route::put('/specs/{specId}', [ProductComparisonController::class, 'updateSpec'])->whereNumber('specId');
    Route::delete('/specs/{specId}', [ProductComparisonController::class, 'deleteSpec'])->whereNumber('specId');
    Route::post('/products/{productId}/specs/{specId}/value', [ProductComparisonController::class, 'setSpecValue'])->whereNumber(['productId', 'specId']);
});
Route::post('/compare', [ProductComparisonController::class, 'compare']);
Route::get('/compare/{id}', [ProductComparisonController::class, 'showComparison'])->whereNumber('id');

// ── 产品本地化 ──
Route::prefix('admin/localization')->group(function () {
    Route::get('/languages', [ProductLocalizationController::class, 'languages']);
    Route::get('/stats', [ProductLocalizationController::class, 'stats']);
    Route::get('/products/{productId}/translations', [ProductLocalizationController::class, 'productTranslations'])->whereNumber('productId');
    Route::post('/products/{productId}/translations', [ProductLocalizationController::class, 'saveProductTranslations'])->whereNumber('productId');
    Route::delete('/products/{productId}/translations', [ProductLocalizationController::class, 'deleteProductTranslation'])->whereNumber('productId');
    Route::get('/plans/{planId}/translations', [ProductLocalizationController::class, 'planTranslations'])->whereNumber('planId');
    Route::post('/plans/{planId}/translations', [ProductLocalizationController::class, 'savePlanTranslations'])->whereNumber('planId');
    Route::delete('/plans/{planId}/translations', [ProductLocalizationController::class, 'deletePlanTranslation'])->whereNumber('planId');
});

// ── 产品搜索（管理） ──
Route::prefix('admin/product-search')->group(function () {
    Route::get('/stats', [ProductSearchAdminController::class, 'stats']);
    Route::get('/hot-terms', [ProductSearchAdminController::class, 'hotTerms']);
    Route::get('/zero-result-terms', [ProductSearchAdminController::class, 'zeroResultTerms']);
    Route::get('/config', [ProductSearchAdminController::class, 'config']);
    Route::get('/logs', [ProductSearchAdminController::class, 'logs']);
});

// ── 产品 SKU ──
Route::prefix('admin/product-skus')->group(function () {
    Route::get('/dashboard', [ProductSkuController::class, 'dashboard']);
    Route::post('/batch-stock', [ProductSkuController::class, 'batchStock']);
    Route::post('/upload-deliverable', [ProductSkuController::class, 'uploadDeliverable']);
    Route::get('/', [ProductSkuController::class, 'index']);
    Route::post('/', [ProductSkuController::class, 'store']);
    Route::get('/{productSku}', [ProductSkuController::class, 'show']);
    Route::put('/{productSku}', [ProductSkuController::class, 'update']);
    Route::delete('/{productSku}', [ProductSkuController::class, 'destroy']);
    Route::post('/{productSku}/toggle', [ProductSkuController::class, 'toggle']);
});

// ── 配额告警 ──
Route::prefix('admin/quota-alert')->group(function () {
    Route::get('/dashboard', [QuotaAlertController::class, 'dashboard']);
    Route::get('/logs/list', [QuotaAlertController::class, 'logs']);
    Route::get('/config/options', [QuotaAlertController::class, 'config']);
    Route::post('/check-all', [QuotaAlertController::class, 'checkAll']);
    Route::get('/', [QuotaAlertController::class, 'index']);
    Route::get('/{id}', [QuotaAlertController::class, 'show'])->whereNumber('id');
    Route::put('/{id}/limit', [QuotaAlertController::class, 'updateLimit'])->whereNumber('id');
    Route::post('/{id}/toggle-notifications', [QuotaAlertController::class, 'toggleNotifications'])->whereNumber('id');
});

// ── 速率限制 ──
Route::prefix('rate-limits')->group(function () {
    Route::get('/rules', [RateLimitController::class, 'index']);
    Route::post('/rules', [RateLimitController::class, 'store']);
    Route::put('/rules/{rateLimitRule}', [RateLimitController::class, 'update']);
    Route::delete('/rules/{rateLimitRule}', [RateLimitController::class, 'destroy']);
    Route::get('/stats', [RateLimitController::class, 'stats']);
    Route::get('/key-types', [RateLimitController::class, 'keyTypes']);
});

// ── Redis 高可用 ──
Route::prefix('admin/redis-ha')->group(function () {
    Route::get('/status', [RedisHaController::class, 'status']);
    Route::get('/health', [RedisHaController::class, 'health']);
    Route::get('/sentinel', [RedisHaController::class, 'sentinel']);
    Route::get('/stats', [RedisHaController::class, 'stats']);
    Route::post('/failover', [RedisHaController::class, 'failover']);
    Route::post('/flush', [RedisHaController::class, 'flush']);
    Route::post('/reset-circuit-breaker', [RedisHaController::class, 'resetCircuitBreaker']);
});

// ── 区域合规 ──
Route::prefix('admin/regional-compliance')->group(function () {
    Route::get('/dashboard', [RegionalComplianceController::class, 'dashboard']);
    Route::post('/initialize', [RegionalComplianceController::class, 'initialize']);
    Route::get('/configs', [RegionalComplianceController::class, 'configs']);
    Route::put('/configs/{regionKey}', [RegionalComplianceController::class, 'updateConfig']);
    Route::get('/status/{regionKey}', [RegionalComplianceController::class, 'checkStatus']);
    Route::get('/restrictions', [RegionalComplianceController::class, 'restrictions']);
    Route::post('/restrictions', [RegionalComplianceController::class, 'addRestriction']);
    Route::delete('/restrictions/{id}', [RegionalComplianceController::class, 'removeRestriction'])->whereNumber('id');
    Route::post('/check-eligibility', [RegionalComplianceController::class, 'checkProductEligibility']);
    Route::post('/generate-summary', [RegionalComplianceController::class, 'generateSummary']);
    Route::get('/logs', [RegionalComplianceController::class, 'logs']);
    Route::get('/available-regions', [RegionalComplianceController::class, 'availableRegions']);
});

// ── 续费仪表盘 ──
Route::prefix('admin/renewal-dashboard')->group(function () {
    Route::get('/stats', [RenewalDashboardController::class, 'stats']);
    Route::get('/expiring-licenses', [RenewalDashboardController::class, 'expiringLicenses']);
    Route::get('/expired-licenses', [RenewalDashboardController::class, 'expiredLicenses']);
    Route::post('/batch-renew', [RenewalDashboardController::class, 'batchRenew']);
    Route::post('/renew/{license}', [RenewalDashboardController::class, 'renew']);
    Route::get('/activity-log', [RenewalDashboardController::class, 'activityLog']);
});

// ── 收入确认（双前缀） ──
$revenueRoutes = function () {
    Route::get('/schedules', [RevenueRecognitionController::class, 'schedules']);
    Route::get('/schedules/{id}', [RevenueRecognitionController::class, 'showSchedule'])->whereNumber('id');
    Route::post('/process', [RevenueRecognitionController::class, 'processRecognition']);
    Route::post('/create-schedules', [RevenueRecognitionController::class, 'createSchedules']);
    Route::get('/summary', [RevenueRecognitionController::class, 'summary']);
    Route::get('/asc606-report', [RevenueRecognitionController::class, 'asc606Report']);
    Route::get('/asc606-report/export', [RevenueRecognitionController::class, 'exportReport']);
    Route::get('/monthly-snapshots', [RevenueRecognitionController::class, 'monthlySnapshots']);
    Route::post('/generate-snapshot', [RevenueRecognitionController::class, 'generateSnapshot']);
    Route::post('/schedules/{id}/cancel', [RevenueRecognitionController::class, 'cancelSchedule'])->whereNumber('id');
    Route::post('/schedules/{id}/recompute', [RevenueRecognitionController::class, 'recomputeSchedule'])->whereNumber('id');
    Route::get('/mrr-waterfall', [RevenueRecognitionController::class, 'mrrWaterfall']);
    Route::get('/mrr-drilldown', [RevenueRecognitionController::class, 'mrrDrilldown']);
    Route::get('/mrr-summary', [RevenueRecognitionController::class, 'mrrSummary']);
};
Route::prefix('revenue')->group($revenueRoutes);
Route::prefix('api/revenue')->group($revenueRoutes);

// ── SCIM ──
Route::prefix('scim')->group(function () {
    Route::get('/dashboard', [ScimController::class, 'dashboard']);
    Route::get('/configs', [ScimController::class, 'index']);
    Route::post('/configs', [ScimController::class, 'store']);
    Route::put('/configs/{id}', [ScimController::class, 'update'])->whereNumber('id');
    Route::delete('/configs/{id}', [ScimController::class, 'destroy'])->whereNumber('id');
    Route::post('/configs/{id}/test', [ScimController::class, 'testConnection'])->whereNumber('id');
    Route::post('/configs/{id}/sync', [ScimController::class, 'syncNow'])->whereNumber('id');
    Route::get('/configs/{id}/logs', [ScimController::class, 'syncLogs'])->whereNumber('id');
    Route::get('/provider-options/{provider}', [ScimController::class, 'providerOptions']);
    Route::get('/default-mapping', [ScimController::class, 'defaultMapping']);
});

// ── 密钥管理 ──
Route::prefix('secrets')->group(function () {
    Route::get('/health', [SecretController::class, 'health']);
    Route::get('/types', [SecretController::class, 'types']);
    Route::get('/logs/all', [SecretController::class, 'accessLogs']);
    Route::get('/master-keys', [SecretController::class, 'masterKeys']);
    Route::post('/master-keys/generate', [SecretController::class, 'generateMasterKey']);
    Route::post('/master-keys/rotate', [SecretController::class, 'rotateMasterKey']);
    Route::get('/', [SecretController::class, 'index']);
    Route::post('/', [SecretController::class, 'store']);
    Route::get('/{tenantSecret}', [SecretController::class, 'show']);
    Route::post('/{tenantSecret}/rotate', [SecretController::class, 'rotate']);
    Route::post('/{tenantSecret}/revoke', [SecretController::class, 'revoke']);
    Route::post('/{tenantSecret}/restore', [SecretController::class, 'restore']);
    Route::get('/{tenantSecret}/logs', [SecretController::class, 'accessLogs']);
});

// ── 安全响应头 ──
Route::prefix('security/headers')->group(function () {
    Route::get('/', [SecurityHeadersController::class, 'index']);
    Route::put('/', [SecurityHeadersController::class, 'update']);
    Route::post('/reset', [SecurityHeadersController::class, 'reset']);
    Route::get('/preview', [SecurityHeadersController::class, 'preview']);
});

// ── SEO ──
Route::prefix('seo')->group(function () {
    Route::get('/dashboard', [SeoController::class, 'dashboard']);
    Route::get('/sitemap', [SeoController::class, 'sitemap']);
    Route::get('/metadata', [SeoController::class, 'showMetadata']);
    Route::post('/metadata', [SeoController::class, 'upsertMetadata']);
    Route::delete('/metadata', [SeoController::class, 'destroyMetadata']);
    Route::get('/redirects', [SeoController::class, 'listRedirects']);
    Route::post('/redirects', [SeoController::class, 'storeRedirect']);
    Route::get('/redirects/{redirect}', [SeoController::class, 'showRedirect']);
    Route::put('/redirects/{redirect}', [SeoController::class, 'updateRedirect']);
    Route::delete('/redirects/{redirect}', [SeoController::class, 'destroyRedirect']);
    Route::post('/redirects/bulk-import', [SeoController::class, 'bulkImport']);
});

// ── 会话管理 ──
Route::prefix('admin/sessions')->group(function () {
    Route::get('/dashboard', [SessionController::class, 'dashboard']);
    Route::get('/', [SessionController::class, 'index']);
    Route::post('/batch-terminate', [SessionController::class, 'batchTerminate']);
    Route::post('/terminate-user/{userId}', [SessionController::class, 'terminateUser'])->whereNumber('userId');
    Route::get('/{userSession}', [SessionController::class, 'show']);
    Route::post('/{userSession}/terminate', [SessionController::class, 'terminate']);
});

// ── CRL 证书吊销 ──
Route::prefix('admin/crl')->group(function () {
    Route::get('/dashboard', [CrlController::class, 'dashboard']);
    Route::get('/entries', [CrlController::class, 'entries']);
    Route::post('/revoke', [CrlController::class, 'revoke']);
    Route::post('/batch-revoke', [CrlController::class, 'batchRevoke']);
    Route::post('/restore', [CrlController::class, 'restore']);
    Route::get('/check/{licenseKey}', [CrlController::class, 'check']);
    Route::post('/auto-verify', [CrlController::class, 'autoVerify']);
});

// ── HSM 硬件安全模块 ──
Route::prefix('hsm')->group(function () {
    Route::get('/health', [HsmController::class, 'health']);
    Route::get('/stats', [HsmController::class, 'stats']);
    Route::get('/keys', [HsmController::class, 'keys']);
    Route::post('/init', [HsmController::class, 'init']);
    Route::post('/rotate', [HsmController::class, 'rotate']);
    Route::post('/sign', [HsmController::class, 'sign']);
});

// ── 入侵检测（IDS） ──
Route::prefix('admin/ids')->group(function () {
    Route::get('/dashboard', [IntrusionDetectionController::class, 'dashboard']);
    Route::get('/trends', [IntrusionDetectionController::class, 'trends']);
    Route::get('/rules/detection-types', [IntrusionDetectionController::class, 'detectionTypes']);
    Route::post('/rules/seed', [IntrusionDetectionController::class, 'seedRules']);
    Route::get('/rules', [IntrusionDetectionController::class, 'rules']);
    Route::post('/rules', [IntrusionDetectionController::class, 'storeRule']);
    Route::get('/rules/{rule}', [IntrusionDetectionController::class, 'showRule']);
    Route::put('/rules/{rule}', [IntrusionDetectionController::class, 'updateRule']);
    Route::delete('/rules/{rule}', [IntrusionDetectionController::class, 'destroyRule']);
    Route::get('/alerts/statuses', [IntrusionDetectionController::class, 'alertStatuses']);
    Route::post('/alerts/clear', [IntrusionDetectionController::class, 'clearAlerts']);
    Route::get('/alerts', [IntrusionDetectionController::class, 'alerts']);
    Route::get('/alerts/{id}', [IntrusionDetectionController::class, 'showAlert'])->whereNumber('id');
    Route::put('/alerts/{alert}/status', [IntrusionDetectionController::class, 'updateAlertStatus']);
});

// ── 公钥版本 ──
$publicKeyRoutes = function () {
    Route::get('/versions', [PublicKeyVersionController::class, 'index']);
    Route::post('/versions', [PublicKeyVersionController::class, 'store']);
    Route::get('/versions/{keyVersion}', [PublicKeyVersionController::class, 'show'])->whereNumber('keyVersion');
    Route::post('/versions/{keyVersion}/revoke', [PublicKeyVersionController::class, 'revoke'])->whereNumber('keyVersion');
    Route::post('/test-signing', [PublicKeyVersionController::class, 'testSigning']);
    Route::get('/stats', [PublicKeyVersionController::class, 'stats']);
    Route::get('/rotation-check', [PublicKeyVersionController::class, 'rotationCheck']);
};
Route::prefix('public-keys')->group($publicKeyRoutes);
Route::prefix('api/public-keys')->group($publicKeyRoutes);

// ── 静态资源 CDN ──
Route::prefix('static-assets')->group(function () {
    Route::get('/cdn/stats', [StaticAssetCdnController::class, 'stats']);
    Route::post('/cdn/deploy', [StaticAssetCdnController::class, 'deploy']);
    Route::post('/cdn/activate', [StaticAssetCdnController::class, 'activate']);
    Route::post('/cdn/rollback', [StaticAssetCdnController::class, 'rollback']);
    Route::get('/cdn/versions', [StaticAssetCdnController::class, 'versions']);
    Route::get('/cdn/version/current', [StaticAssetCdnController::class, 'currentVersion']);
    Route::delete('/cdn/versions/{version}', [StaticAssetCdnController::class, 'destroyVersion']);
    Route::get('/build-files', [StaticAssetCdnController::class, 'buildFiles']);
});

// ── 标签 ──
Route::prefix('tags')->group(function () {
    Route::get('/grouped', [TagController::class, 'grouped']);
    Route::post('/sync', [TagController::class, 'sync']);
    Route::post('/attach', [TagController::class, 'attach']);
    Route::post('/detach', [TagController::class, 'detach']);
    Route::get('/', [TagController::class, 'index']);
    Route::post('/', [TagController::class, 'store']);
    Route::get('/{tag}', [TagController::class, 'show']);
    Route::put('/{tag}', [TagController::class, 'update']);
    Route::delete('/{tag}', [TagController::class, 'destroy']);
});

// ── Teams 通知 ──
Route::prefix('admin/teams-notifier')->group(function () {
    Route::get('/dashboard', [TeamsNotifierController::class, 'dashboard']);
    Route::get('/logs/list', [TeamsNotifierController::class, 'logs']);
    Route::get('/config/options', [TeamsNotifierController::class, 'config']);
    Route::post('/send-activation', [TeamsNotifierController::class, 'sendActivation']);
    Route::post('/send-alert', [TeamsNotifierController::class, 'sendAlert']);
    Route::get('/', [TeamsNotifierController::class, 'index']);
    Route::post('/', [TeamsNotifierController::class, 'store']);
    Route::put('/{id}', [TeamsNotifierController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [TeamsNotifierController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/test', [TeamsNotifierController::class, 'test'])->whereNumber('id');
    Route::post('/{id}/send-test', [TeamsNotifierController::class, 'sendTestMessage'])->whereNumber('id');
});

// ── Text-to-SQL（双前缀） ──
$textToSqlRoutes = function () {
    Route::post('/query', [TextToSqlController::class, 'query']);
    Route::post('/execute', [TextToSqlController::class, 'execute']);
    Route::post('/validate', [TextToSqlController::class, 'validateSql']);
    Route::get('/config', [TextToSqlController::class, 'config']);
};
Route::prefix('text-to-sql')->group($textToSqlRoutes);
Route::prefix('api/text-to-sql')->group($textToSqlRoutes);

// ── Token 计量 ──
Route::prefix('admin/token-meter')->group(function () {
    Route::get('/dashboard', [TokenMeterController::class, 'dashboard']);
    Route::get('/models', [TokenMeterController::class, 'models']);
    Route::get('/features', [TokenMeterController::class, 'features']);
    Route::get('/budgets', [TokenMeterController::class, 'budgets']);
    Route::post('/budgets', [TokenMeterController::class, 'upsertBudget']);
    Route::get('/alerts', [TokenMeterController::class, 'alerts']);
    Route::post('/alerts/{id}/resolve', [TokenMeterController::class, 'resolveAlert'])->whereNumber('id');
    Route::post('/check-alerts', [TokenMeterController::class, 'checkAlerts']);
    Route::get('/tenants/{tenantId}/report', [TokenMeterController::class, 'tenantReport'])->whereNumber('tenantId');
    Route::get('/cost-allocation', [TokenMeterController::class, 'costAllocation']);
    Route::get('/allocation-summary', [TokenMeterController::class, 'allocationSummary']);
    Route::get('/export-allocation', [TokenMeterController::class, 'exportAllocation']);
    Route::get('/', [TokenMeterController::class, 'index']);
    Route::post('/', [TokenMeterController::class, 'store']);
});

// ── TPM 绑定 ──
Route::prefix('admin/tpm-binding')->group(function () {
    Route::get('/dashboard', [TpmBindingController::class, 'dashboard']);
    Route::get('/verification-stats', [TpmBindingController::class, 'verificationStats']);
    Route::get('/tpm-devices', [TpmBindingController::class, 'tpmCapableDevices']);
    Route::post('/prune-logs', [TpmBindingController::class, 'pruneLogs']);
    Route::get('/bindings', [TpmBindingController::class, 'listBindings']);
    Route::post('/bindings', [TpmBindingController::class, 'registerBinding']);
    Route::get('/bindings/{id}', [TpmBindingController::class, 'showBinding'])->whereNumber('id');
    Route::post('/bindings/{id}/verify', [TpmBindingController::class, 'verifyBinding'])->whereNumber('id');
    Route::post('/bindings/{id}/revoke', [TpmBindingController::class, 'revokeBinding'])->whereNumber('id');
    Route::post('/bindings/{id}/unlock', [TpmBindingController::class, 'unlockBinding'])->whereNumber('id');
    Route::get('/bindings/{id}/verification-history', [TpmBindingController::class, 'verificationHistory'])->whereNumber('id');
    Route::get('/check-license/{licenseId}', [TpmBindingController::class, 'checkLicense'])->whereNumber('licenseId');
});

// ── 两阶段提交 ──
Route::post('/license/reserve', [TwoPhaseCommitController::class, 'reserve']);
Route::post('/license/commit', [TwoPhaseCommitController::class, 'commit']);
Route::post('/license/cancel-reservation', [TwoPhaseCommitController::class, 'cancel']);
Route::post('/license/reservation-status', [TwoPhaseCommitController::class, 'status']);
Route::get('/admin/licenses/{license}/reservation-stats', [TwoPhaseCommitController::class, 'stats']);
Route::get('/admin/reservations/active', [TwoPhaseCommitController::class, 'activeReservations']);
Route::get('/admin/reservations/history', [TwoPhaseCommitController::class, 'history']);

// ── 更新 CDN ──
Route::prefix('admin/update-cdn')->group(function () {
    Route::get('/dashboard', [UpdateCdnController::class, 'dashboard']);
    Route::get('/config', [UpdateCdnController::class, 'config']);
    Route::get('/bandwidth', [UpdateCdnController::class, 'bandwidth']);
    Route::get('/downloads', [UpdateCdnController::class, 'downloads']);
    Route::post('/purge', [UpdateCdnController::class, 'purge']);
    Route::post('/packages/{updatePackage}/publish-purge', [UpdateCdnController::class, 'publishAndPurge']);
    Route::get('/packages/{updatePackage}/chunks', [UpdateCdnController::class, 'chunkInfo']);
    Route::get('/packages/{updatePackage}/urls', [UpdateCdnController::class, 'packageUrls']);
});

// ── 更新签名 ──
Route::prefix('update-signer')->group(function () {
    Route::get('/dashboard', [UpdateSignerController::class, 'dashboard']);
    Route::get('/verification-logs', [UpdateSignerController::class, 'verificationLogs']);
    Route::get('/public-key', [UpdateSignerController::class, 'publicKey']);
    Route::post('/verify', [UpdateSignerController::class, 'verify']);
    Route::get('/rollbacks', [UpdateSignerController::class, 'rollbacks']);
    Route::get('/gray-releases', [UpdateSignerController::class, 'grayReleases']);
    Route::post('/check-eligibility', [UpdateSignerController::class, 'checkEligibility']);
    Route::post('/packages/{id}/sign', [UpdateSignerController::class, 'sign'])->whereNumber('id');
    Route::post('/packages/{id}/rollback', [UpdateSignerController::class, 'createRollback'])->whereNumber('id');
    Route::post('/packages/{id}/gray-release', [UpdateSignerController::class, 'createGrayRelease'])->whereNumber('id');
    Route::post('/rollbacks/{id}/approve', [UpdateSignerController::class, 'approveRollback'])->whereNumber('id');
    Route::post('/rollbacks/{id}/execute', [UpdateSignerController::class, 'executeRollback'])->whereNumber('id');
    Route::post('/gray-releases/{id}/start', [UpdateSignerController::class, 'startGrayRelease'])->whereNumber('id');
    Route::post('/gray-releases/{id}/advance', [UpdateSignerController::class, 'advanceGrayRelease'])->whereNumber('id');
    Route::post('/gray-releases/{id}/pause', [UpdateSignerController::class, 'pauseGrayRelease'])->whereNumber('id');
});

// ── VM 检测 ──
Route::prefix('admin/vm-detection')->group(function () {
    Route::get('/dashboard', [VMDetectionController::class, 'dashboard']);
    Route::get('/devices', [VMDetectionController::class, 'devices']);
    Route::post('/detect/{device}', [VMDetectionController::class, 'detect']);
    Route::get('/config', [VMDetectionController::class, 'getConfig']);
    Route::put('/config', [VMDetectionController::class, 'updateConfig']);
});

// ── 水印防篡改 ──
Route::prefix('admin')->group(function () {
    Route::get('/watermark-tamper/dashboard', [WatermarkTamperController::class, 'dashboard']);
    Route::get('/watermark-tamper/verification-stats', [WatermarkTamperController::class, 'verificationStats']);
    Route::get('/watermarks', [WatermarkTamperController::class, 'watermarks']);
    Route::get('/watermarks/trace', [WatermarkTamperController::class, 'traceWatermark']);
    Route::get('/watermarks/search', [WatermarkTamperController::class, 'searchWatermarks']);
    Route::delete('/watermarks/{watermarkId}', [WatermarkTamperController::class, 'revokeWatermark'])->whereNumber('watermarkId');
    Route::get('/tamper-events', [WatermarkTamperController::class, 'tamperEvents']);
    Route::post('/tamper-events/{eventId}/resolve', [WatermarkTamperController::class, 'resolveTamperEvent'])->whereNumber('eventId');
    Route::get('/tamper-policies', [WatermarkTamperController::class, 'tamperPolicies']);
    Route::put('/tamper-policies/{policyId}', [WatermarkTamperController::class, 'updateTamperPolicy'])->whereNumber('policyId');
    Route::post('/licenses/{licenseId}/watermark', [WatermarkTamperController::class, 'embedWatermark'])->whereNumber('licenseId');
    Route::get('/licenses/{licenseId}/watermark', [WatermarkTamperController::class, 'extractWatermark'])->whereNumber('licenseId');
    Route::post('/licenses/{licenseId}/verify-integrity', [WatermarkTamperController::class, 'verifyIntegrity'])->whereNumber('licenseId');
    Route::post('/licenses/{licenseId}/refresh-hash', [WatermarkTamperController::class, 'refreshIntegrityHash'])->whereNumber('licenseId');
    Route::get('/licenses/{licenseId}/verification-logs', [WatermarkTamperController::class, 'verificationLogs'])->whereNumber('licenseId');
});

// ── Webhook 过滤器 ──
Route::prefix('admin/webhook-filters')->group(function () {
    Route::get('/options', [WebhookFilterController::class, 'options']);
    Route::post('/test-condition', [WebhookFilterController::class, 'testCondition']);
    Route::get('/endpoints/{endpointId}/filters', [WebhookFilterController::class, 'index'])->whereNumber('endpointId');
    Route::post('/endpoints/{endpointId}/filters', [WebhookFilterController::class, 'store'])->whereNumber('endpointId');
    Route::put('/endpoints/{endpointId}/filters/{filterId}', [WebhookFilterController::class, 'update'])->whereNumber(['endpointId', 'filterId']);
    Route::delete('/endpoints/{endpointId}/filters/{filterId}', [WebhookFilterController::class, 'destroy'])->whereNumber(['endpointId', 'filterId']);
    Route::post('/endpoints/{endpointId}/batch-test', [WebhookFilterController::class, 'batchTest'])->whereNumber('endpointId');
});

// ── SLA 可用性拨测 ──
Route::prefix('admin/sla-probes')->group(function () {
    Route::get('/dashboard', [SlaProbeController::class, 'dashboard']);
    Route::get('/', [SlaProbeController::class, 'index']);
    Route::post('/', [SlaProbeController::class, 'store']);
    Route::get('/{id}', [SlaProbeController::class, 'show'])->whereNumber('id');
    Route::put('/{slaProbe}', [SlaProbeController::class, 'update']);
    Route::delete('/{slaProbe}', [SlaProbeController::class, 'destroy']);
    Route::post('/{slaProbe}/toggle', [SlaProbeController::class, 'toggle']);
    Route::post('/{slaProbe}/run', [SlaProbeController::class, 'runNow']);
    Route::get('/{slaProbe}/results', [SlaProbeController::class, 'results']);
    Route::get('/{slaProbe}/uptime', [SlaProbeController::class, 'uptime']);
});

// ── Token 内省与吊销（管理端） ──
Route::get('/admin/revoked-tokens', [TokenController::class, 'adminRevokedTokens']);
Route::post('/admin/tokens/{tokenId}/revoke', [TokenController::class, 'adminRevokeToken'])->whereNumber('tokenId');
Route::post('/admin/users/{userId}/tokens/revoke-all', [TokenController::class, 'adminRevokeUserTokens'])->whereNumber('userId');

// ── 文件预览 ──
Route::post('/files/preview', [FilePreviewController::class, 'preview']);
