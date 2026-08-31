<?php

use App\Http\Controllers\Api\AffiliateEnhancedController;
use App\Http\Controllers\Api\AgentManagerController;
use App\Http\Controllers\Api\AgentTierController;
use App\Http\Controllers\Api\AiIntelligenceController;
use App\Http\Controllers\Api\AIOpsAnalystController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ApiGatewayController;
use App\Http\Controllers\Api\ApiImpactAnalyzerController;
use App\Http\Controllers\Api\AutoDeliveryController;
use App\Http\Controllers\Api\CaseStudiesController;
use App\Http\Controllers\Api\CertificationController;
use App\Http\Controllers\Api\ComparePageController;
use App\Http\Controllers\Api\CrossBorderController;
use App\Http\Controllers\Api\CustomerAuditLogController;
use App\Http\Controllers\Api\CustomerClusteringController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DataResidencyController;
use App\Http\Controllers\Api\DeletionController;
use App\Http\Controllers\Api\DemoBookingController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\EarningNotificationController;
use App\Http\Controllers\Api\EcommerceAnalyticsController;
use App\Http\Controllers\Api\EcommerceAPIController;
use App\Http\Controllers\Api\EcommerceDashboardController;
use App\Http\Controllers\Api\EdgeVerifierController;
use App\Http\Controllers\Api\EmailDashboardController;
use App\Http\Controllers\Api\EmailDripController;
use App\Http\Controllers\Api\EmailWhitelabelController;
use App\Http\Controllers\Api\EndpointUsageAnalyticsController;
use App\Http\Controllers\Api\EnterpriseContractController;
use App\Http\Controllers\Api\FeatureAdoptionController;
use App\Http\Controllers\Api\FeatureStoreController;
use App\Http\Controllers\Api\FileStorageController;
use App\Http\Controllers\Api\FingerprintDriftController;
use App\Http\Controllers\Api\FlashSaleController;
use App\Http\Controllers\Api\FlowDesignerController;
use App\Http\Controllers\Api\FooterNavController;
use App\Http\Controllers\Api\GdprEnhancementController;
use App\Http\Controllers\Api\GeoLocationController;
use App\Http\Controllers\Api\LogAggregationController;
use App\Http\Controllers\Api\OemController;

// ── 数据驻留 ──
Route::prefix('admin/data-residency')->group(function () {
    Route::get('/dashboard', [DataResidencyController::class, 'dashboard']);
    Route::get('/regions', [DataResidencyController::class, 'regions']);
    Route::get('/records', [DataResidencyController::class, 'records']);
    Route::post('/assign-tenant', [DataResidencyController::class, 'assignTenantRegion']);
    Route::post('/create-record', [DataResidencyController::class, 'createRecord']);
    Route::post('/resolve-target', [DataResidencyController::class, 'resolveTarget']);
    Route::post('/start-migration', [DataResidencyController::class, 'startMigration']);
    Route::get('/migrations', [DataResidencyController::class, 'migrations']);
    Route::get('/compliance-audit', [DataResidencyController::class, 'complianceAudit']);
    Route::get('/classifications', [DataResidencyController::class, 'classifications']);
    Route::get('/tenants', [DataResidencyController::class, 'tenants']);
});

// ── API 网关 ──
Route::prefix('admin/api-gateway')->group(function () {
    Route::get('/dashboard', [ApiGatewayController::class, 'dashboard']);
    Route::get('/health', [ApiGatewayController::class, 'health']);
    Route::get('/info', [ApiGatewayController::class, 'info']);
    Route::get('/routes', [ApiGatewayController::class, 'routes']);
    Route::post('/routes/sync', [ApiGatewayController::class, 'syncRoutes']);
    Route::get('/services', [ApiGatewayController::class, 'services']);
    Route::get('/upstreams', [ApiGatewayController::class, 'upstreams']);
    Route::get('/plugins', [ApiGatewayController::class, 'plugins']);
    Route::get('/config', [ApiGatewayController::class, 'config']);
    Route::get('/export', [ApiGatewayController::class, 'export']);
    Route::post('/clear-cache', [ApiGatewayController::class, 'clearCache']);
});

// ── 日志聚合 ──
Route::prefix('admin/log-aggregation')->group(function () {
    Route::get('/dashboard', [LogAggregationController::class, 'dashboard']);
    Route::get('/search', [LogAggregationController::class, 'search']);
    Route::get('/entries/{id}', [LogAggregationController::class, 'show'])->whereNumber('id');
    Route::get('/level-stats', [LogAggregationController::class, 'levelStats']);
    Route::get('/slow-queries', [LogAggregationController::class, 'slowQueries']);
    Route::get('/path-stats', [LogAggregationController::class, 'pathStats']);
    Route::post('/prune', [LogAggregationController::class, 'prune']);
    Route::get('/saved-searches', [LogAggregationController::class, 'listSavedSearches']);
    Route::post('/saved-searches', [LogAggregationController::class, 'saveSearch']);
    Route::delete('/saved-searches/{id}', [LogAggregationController::class, 'deleteSavedSearch'])->whereNumber('id');
});

// ── 企业合同 ──
Route::prefix('admin/enterprise-contracts')->group(function () {
    Route::get('/dashboard', [EnterpriseContractController::class, 'dashboard']);
    Route::get('/expiring', [EnterpriseContractController::class, 'expiring']);
    Route::get('/', [EnterpriseContractController::class, 'index']);
    Route::post('/', [EnterpriseContractController::class, 'store']);
    Route::get('/{id}', [EnterpriseContractController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [EnterpriseContractController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [EnterpriseContractController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/submit', [EnterpriseContractController::class, 'submitForApproval'])->whereNumber('id');
    Route::post('/{id}/approve', [EnterpriseContractController::class, 'approve'])->whereNumber('id');
    Route::post('/{id}/terminate', [EnterpriseContractController::class, 'terminate'])->whereNumber('id');
    Route::post('/{id}/renew', [EnterpriseContractController::class, 'renew'])->whereNumber('id');
});

// ── 特征商店 ──
Route::prefix('admin/feature-store')->group(function () {
    Route::get('/dashboard', [FeatureStoreController::class, 'dashboard']);
    Route::post('/feature-vector', [FeatureStoreController::class, 'getFeatureVector']);
    Route::post('/sync-all-offline', [FeatureStoreController::class, 'syncAllOffline']);
    Route::post('/batch-check-consistency', [FeatureStoreController::class, 'batchCheckConsistency']);
    Route::get('/consistency-history', [FeatureStoreController::class, 'consistencyHistory']);
    Route::get('/groups', [FeatureStoreController::class, 'groups']);
    Route::post('/groups', [FeatureStoreController::class, 'storeGroup']);
    Route::get('/groups/{id}', [FeatureStoreController::class, 'showGroup'])->whereNumber('id');
    Route::put('/groups/{id}', [FeatureStoreController::class, 'updateGroup'])->whereNumber('id');
    Route::delete('/groups/{id}', [FeatureStoreController::class, 'destroyGroup'])->whereNumber('id');
    Route::get('/groups/{groupId}/features', [FeatureStoreController::class, 'features'])->whereNumber('groupId');
    Route::post('/groups/{groupId}/features', [FeatureStoreController::class, 'storeFeature'])->whereNumber('groupId');
    Route::post('/groups/{groupId}/features/batch', [FeatureStoreController::class, 'batchStoreFeatures'])->whereNumber('groupId');
    Route::put('/features/{featureId}', [FeatureStoreController::class, 'updateFeature'])->whereNumber('featureId');
    Route::delete('/features/{featureId}', [FeatureStoreController::class, 'destroyFeature'])->whereNumber('featureId');
    Route::post('/features/{featureId}/values', [FeatureStoreController::class, 'setValue'])->whereNumber('featureId');
    Route::post('/features/{featureId}/values/batch', [FeatureStoreController::class, 'batchSetValues'])->whereNumber('featureId');
    Route::get('/features/{featureId}/values/{entityId}', [FeatureStoreController::class, 'getValue'])->whereNumber('featureId')->whereNumber('entityId');
    Route::post('/features/{featureId}/sync-offline', [FeatureStoreController::class, 'syncOffline'])->whereNumber('featureId');
    Route::get('/features/{featureId}/offline-training', [FeatureStoreController::class, 'offlineTraining'])->whereNumber('featureId');
    Route::post('/features/{featureId}/check-consistency', [FeatureStoreController::class, 'checkConsistency'])->whereNumber('featureId');
});

// ── 邮件 drip / 仪表盘 / 白标 ──
Route::prefix('admin/email-drip')->group(function () {
    Route::get('/dashboard', [EmailDripController::class, 'dashboard']);
    Route::get('/triggers', [EmailDripController::class, 'triggers']);
    Route::get('/campaigns', [EmailDripController::class, 'campaigns']);
    Route::post('/campaigns', [EmailDripController::class, 'storeCampaign']);
    Route::get('/campaigns/{id}', [EmailDripController::class, 'showCampaign'])->whereNumber('id');
    Route::post('/campaigns/{id}/sequences', [EmailDripController::class, 'storeSequence'])->whereNumber('id');
    Route::post('/campaigns/{id}/activate', [EmailDripController::class, 'activate'])->whereNumber('id');
    Route::post('/campaigns/{id}/pause', [EmailDripController::class, 'pause'])->whereNumber('id');
});
Route::prefix('admin/email-dashboard')->group(function () {
    Route::get('/overview', [EmailDashboardController::class, 'overview']);
    Route::get('/logs', [EmailDashboardController::class, 'logs']);
    Route::get('/logs/{id}', [EmailDashboardController::class, 'logDetail'])->whereNumber('id');
    Route::get('/templates/{code}', [EmailDashboardController::class, 'templateDetail']);
});
Route::prefix('email-whitelabel')->group(function () {
    Route::get('/', [EmailWhitelabelController::class, 'index']);
    Route::put('/', [EmailWhitelabelController::class, 'update']);
    Route::get('/dns-guide', [EmailWhitelabelController::class, 'dnsGuide']);
    Route::post('/verify', [EmailWhitelabelController::class, 'verify']);
});

// ── 页脚导航 / 流程设计 / 闪购 / 地理定位 ──
Route::prefix('admin/footer-nav')->group(function () {
    Route::get('/options', [FooterNavController::class, 'options']);
    Route::post('/init-defaults', [FooterNavController::class, 'initDefaults']);
    Route::post('/reorder', [FooterNavController::class, 'reorder']);
    Route::get('/', [FooterNavController::class, 'index']);
    Route::post('/', [FooterNavController::class, 'store']);
    Route::put('/{id}', [FooterNavController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [FooterNavController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/toggle', [FooterNavController::class, 'toggle'])->whereNumber('id');
});
Route::get('/footer-nav/public', [FooterNavController::class, 'publicNav']);

Route::prefix('admin/flow-designer')->group(function () {
    Route::get('/stats', [FlowDesignerController::class, 'stats']);
    Route::get('/node-palette', [FlowDesignerController::class, 'nodePalette']);
    Route::get('/categories', [FlowDesignerController::class, 'categories']);
    Route::get('/', [FlowDesignerController::class, 'index']);
    Route::post('/', [FlowDesignerController::class, 'store']);
    Route::get('/{id}', [FlowDesignerController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [FlowDesignerController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [FlowDesignerController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/nodes', [FlowDesignerController::class, 'addNode'])->whereNumber('id');
    Route::put('/{designId}/nodes/{nodeId}', [FlowDesignerController::class, 'updateNode'])->whereNumber('designId')->whereNumber('nodeId');
    Route::delete('/{designId}/nodes/{nodeId}', [FlowDesignerController::class, 'deleteNode'])->whereNumber('designId')->whereNumber('nodeId');
    Route::post('/{id}/edges', [FlowDesignerController::class, 'addEdge'])->whereNumber('id');
    Route::delete('/{designId}/edges/{edgeId}', [FlowDesignerController::class, 'deleteEdge'])->whereNumber('designId')->whereNumber('edgeId');
    Route::post('/{id}/save-graph', [FlowDesignerController::class, 'saveGraph'])->whereNumber('id');
    Route::post('/{id}/export', [FlowDesignerController::class, 'export'])->whereNumber('id');
});

Route::prefix('admin/flash-sale')->group(function () {
    Route::get('/dashboard', [FlashSaleController::class, 'dashboard']);
    Route::get('/list', [FlashSaleController::class, 'index']);
    Route::post('/create', [FlashSaleController::class, 'store']);
    Route::post('/{id}/status', [FlashSaleController::class, 'updateStatus'])->whereNumber('id');
    Route::post('/{id}/release-expired', [FlashSaleController::class, 'releaseExpired'])->whereNumber('id');
});

Route::prefix('admin/geo-location')->group(function () {
    Route::get('/dashboard', [GeoLocationController::class, 'dashboard']);
    Route::get('/stats', [GeoLocationController::class, 'stats']);
    Route::get('/map-data', [GeoLocationController::class, 'mapData']);
    Route::get('/records', [GeoLocationController::class, 'records']);
    Route::post('/record', [GeoLocationController::class, 'record']);
    Route::get('/blacklist', [GeoLocationController::class, 'blacklist']);
    Route::put('/blacklist', [GeoLocationController::class, 'updateBlacklist']);
});

// ── GDPR 增强 ──
Route::prefix('gdpr/enhancement')->group(function () {
    Route::get('/all-stats', [GdprEnhancementController::class, 'allStats']);
    Route::get('/dpia/stats', [GdprEnhancementController::class, 'dpiaStats']);
    Route::get('/dpia', [GdprEnhancementController::class, 'dpiaIndex']);
    Route::post('/dpia', [GdprEnhancementController::class, 'dpiaStore']);
    Route::get('/dpia/{id}', [GdprEnhancementController::class, 'dpiaShow'])->whereNumber('id');
    Route::put('/dpia/{id}', [GdprEnhancementController::class, 'dpiaUpdate'])->whereNumber('id');
    Route::post('/dpia/{id}/review', [GdprEnhancementController::class, 'dpiaReview'])->whereNumber('id');
    Route::get('/breaches/stats', [GdprEnhancementController::class, 'breachStats']);
    Route::get('/breaches', [GdprEnhancementController::class, 'breachIndex']);
    Route::post('/breaches', [GdprEnhancementController::class, 'breachStore']);
    Route::get('/breaches/{id}', [GdprEnhancementController::class, 'breachShow'])->whereNumber('id');
    Route::put('/breaches/{id}', [GdprEnhancementController::class, 'breachUpdate'])->whereNumber('id');
    Route::get('/ropa/stats', [GdprEnhancementController::class, 'ropaStats']);
    Route::get('/ropa', [GdprEnhancementController::class, 'ropaIndex']);
    Route::post('/ropa', [GdprEnhancementController::class, 'ropaStore']);
    Route::get('/ropa/{id}', [GdprEnhancementController::class, 'ropaShow'])->whereNumber('id');
    Route::put('/ropa/{id}', [GdprEnhancementController::class, 'ropaUpdate'])->whereNumber('id');
    Route::get('/sub-processors', [GdprEnhancementController::class, 'subProcessorIndex']);
    Route::post('/sub-processors', [GdprEnhancementController::class, 'subProcessorStore']);
    Route::get('/sub-processors/{id}', [GdprEnhancementController::class, 'subProcessorShow'])->whereNumber('id');
    Route::put('/sub-processors/{id}', [GdprEnhancementController::class, 'subProcessorUpdate'])->whereNumber('id');
    Route::get('/auto-decisions', [GdprEnhancementController::class, 'autoDecisionIndex']);
    Route::post('/auto-decisions', [GdprEnhancementController::class, 'autoDecisionStore']);
    Route::get('/auto-decisions/{id}', [GdprEnhancementController::class, 'autoDecisionShow'])->whereNumber('id');
    Route::put('/auto-decisions/{id}', [GdprEnhancementController::class, 'autoDecisionUpdate'])->whereNumber('id');
});

// ── 客户聚类 / 跨境 / API 影响分析 ──
Route::prefix('admin/customer-clustering')->group(function () {
    Route::post('/run', [CustomerClusteringController::class, 'runClustering']);
    Route::get('/dashboard', [CustomerClusteringController::class, 'dashboard']);
    Route::get('/history', [CustomerClusteringController::class, 'history']);
    Route::get('/segments/{segmentKey}/customers', [CustomerClusteringController::class, 'segmentCustomers']);
    Route::get('/customers/{id}/cluster', [CustomerClusteringController::class, 'customerCluster'])->whereNumber('id');
});

Route::prefix('admin/cross-border')->group(function () {
    Route::get('/dashboard', [CrossBorderController::class, 'dashboard']);
    Route::get('/conversion-logs', [CrossBorderController::class, 'conversionLogs']);
    Route::get('/payments', [CrossBorderController::class, 'payments']);
    Route::post('/payments', [CrossBorderController::class, 'recordPayment']);
    Route::get('/monthly-reports', [CrossBorderController::class, 'monthlyReports']);
    Route::post('/generate-report', [CrossBorderController::class, 'generateReport']);
    Route::post('/check-compliance', [CrossBorderController::class, 'checkCompliance']);
});

Route::prefix('admin/api-impact')->group(function () {
    Route::get('/dashboard', [ApiImpactAnalyzerController::class, 'dashboard']);
    Route::get('/overall-report', [ApiImpactAnalyzerController::class, 'overallReport']);
    Route::get('/analyze/{versionId}', [ApiImpactAnalyzerController::class, 'analyzeVersion'])->whereNumber('versionId');
    Route::get('/customer-usage/{tenantId}', [ApiImpactAnalyzerController::class, 'customerVersionUsage'])->whereNumber('tenantId');
    Route::post('/notify/{versionId}', [ApiImpactAnalyzerController::class, 'sendNotifications'])->whereNumber('versionId');
    Route::get('/notifications/{versionId}', [ApiImpactAnalyzerController::class, 'notificationHistory'])->whereNumber('versionId');
    Route::get('/export/{versionId}', [ApiImpactAnalyzerController::class, 'exportReport'])->whereNumber('versionId');
});

// ── 认证考试 / 案例研究 / 对比页 ──
Route::prefix('admin/certification')->group(function () {
    Route::get('/stats', [CertificationController::class, 'globalStats']);
    Route::get('/my', [CertificationController::class, 'myCertifications']);
    Route::get('/my/stats', [CertificationController::class, 'myStats']);
    Route::post('/exam/start', [CertificationController::class, 'startExam']);
    Route::get('/exam/{devCertId}/questions', [CertificationController::class, 'getExamQuestions'])->whereNumber('devCertId');
    Route::post('/exam/{devCertId}/answer', [CertificationController::class, 'submitAnswer'])->whereNumber('devCertId');
    Route::post('/exam/{devCertId}/submit', [CertificationController::class, 'submitExam'])->whereNumber('devCertId');
    Route::get('/levels', [CertificationController::class, 'getLevels']);
    Route::post('/levels', [CertificationController::class, 'createLevel']);
    Route::put('/levels/{id}', [CertificationController::class, 'updateLevel'])->whereNumber('id');
    Route::get('/levels/{levelId}/questions', [CertificationController::class, 'getQuestions'])->whereNumber('levelId');
    Route::post('/levels/{levelId}/questions', [CertificationController::class, 'addQuestion'])->whereNumber('levelId');
    Route::post('/levels/{levelId}/questions/bulk', [CertificationController::class, 'bulkAddQuestions'])->whereNumber('levelId');
    Route::post('/{id}/revoke', [CertificationController::class, 'revoke'])->whereNumber('id');
});
Route::get('/certification/verify/{number}', [CertificationController::class, 'verifyByNumber']);
Route::get('/certification/directory', [CertificationController::class, 'directory']);
Route::get('/certification/badge/{number}.svg', [CertificationController::class, 'badgeSvg']);

Route::prefix('case-studies')->group(function () {
    Route::get('/featured', [CaseStudiesController::class, 'featured']);
    Route::get('/logo-wall', [CaseStudiesController::class, 'logoWall']);
    Route::get('/categories', [CaseStudiesController::class, 'categories']);
    Route::get('/industry-tags', [CaseStudiesController::class, 'industryTags']);
    Route::get('/', [CaseStudiesController::class, 'index']);
    Route::get('/{id}', [CaseStudiesController::class, 'show'])->whereNumber('id');
});
Route::prefix('admin/case-studies')->group(function () {
    Route::get('/stats', [CaseStudiesController::class, 'stats']);
    Route::post('/upload-logo', [CaseStudiesController::class, 'uploadLogo']);
    Route::post('/', [CaseStudiesController::class, 'store']);
    Route::put('/{id}', [CaseStudiesController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [CaseStudiesController::class, 'destroy'])->whereNumber('id');
});

Route::prefix('compare')->group(function () {
    Route::get('/advantages', [ComparePageController::class, 'advantages']);
    Route::get('/competitors', [ComparePageController::class, 'competitors']);
    Route::get('/config', [ComparePageController::class, 'config']);
    Route::put('/', [ComparePageController::class, 'update']);
    Route::post('/reset', [ComparePageController::class, 'resetFromConfig']);
    Route::get('/', [ComparePageController::class, 'comparison']);
});

// ── 边缘验证 / OEM / 文件存储 / 功能采用 ──
Route::prefix('admin/edge')->group(function () {
    Route::get('/dashboard', [EdgeVerifierController::class, 'dashboard']);
    Route::get('/deployment-guide', [EdgeVerifierController::class, 'deploymentGuide']);
    Route::post('/generate-token', [EdgeVerifierController::class, 'generateToken']);
    Route::post('/batch-generate', [EdgeVerifierController::class, 'batchGenerateTokens']);
    Route::post('/verify', [EdgeVerifierController::class, 'verifyToken']);
    Route::post('/origin-verify', [EdgeVerifierController::class, 'originVerify']);
    Route::post('/revoke', [EdgeVerifierController::class, 'revokeCache']);
    Route::post('/sync-revocation', [EdgeVerifierController::class, 'syncRevocationList']);
    Route::post('/token-info', [EdgeVerifierController::class, 'tokenInfo']);
});

Route::prefix('admin/oem')->group(function () {
    Route::get('/dashboard', [OemController::class, 'dashboard']);
    Route::get('/tiers', [OemController::class, 'tiers']);
    Route::post('/subscribe', [OemController::class, 'subscribe']);
    Route::post('/cancel', [OemController::class, 'cancel']);
    Route::get('/history', [OemController::class, 'history']);
    Route::get('/check-feature', [OemController::class, 'checkFeature']);
    Route::post('/branded-login', [OemController::class, 'saveBrandedLogin']);
    Route::get('/branded-login', [OemController::class, 'getBrandedLogin']);
});

Route::prefix('admin/files')->group(function () {
    Route::get('/stats', [FileStorageController::class, 'stats']);
    Route::post('/upload', [FileStorageController::class, 'upload']);
    Route::get('/', [FileStorageController::class, 'index']);
    Route::get('/{id}', [FileStorageController::class, 'show'])->whereNumber('id');
    Route::delete('/{id}', [FileStorageController::class, 'destroy'])->whereNumber('id');
    Route::delete('/{id}/force', [FileStorageController::class, 'forceDelete'])->whereNumber('id');
    Route::get('/{id}/download', [FileStorageController::class, 'download'])->whereNumber('id');
    Route::post('/{id}/share-link', [FileStorageController::class, 'createShareLink'])->whereNumber('id');
    Route::delete('/{fileId}/share-links/{linkId}', [FileStorageController::class, 'revokeShareLink'])->whereNumber('fileId')->whereNumber('linkId');
});
Route::get('/files/shared/{token}', [FileStorageController::class, 'sharedFile']);

Route::prefix('admin/feature-adoption')->group(function () {
    Route::get('/dashboard', [FeatureAdoptionController::class, 'dashboard']);
    Route::get('/trend', [FeatureAdoptionController::class, 'trend']);
    Route::get('/events', [FeatureAdoptionController::class, 'events']);
    Route::post('/track', [FeatureAdoptionController::class, 'track']);
    Route::post('/batch-track', [FeatureAdoptionController::class, 'batchTrack']);
    Route::post('/generate-snapshot', [FeatureAdoptionController::class, 'generateSnapshot']);
    Route::post('/prune', [FeatureAdoptionController::class, 'prune']);
    Route::get('/feature-defs', [FeatureAdoptionController::class, 'featureDefs']);
    Route::get('/feature/{featureKey}', [FeatureAdoptionController::class, 'featureDetail']);
    Route::get('/category/{category}', [FeatureAdoptionController::class, 'categoryDetail']);
    Route::get('/funnel/{funnelKey}', [FeatureAdoptionController::class, 'funnel']);
});

// ── 告警 / AI 套件 / AI Ops ──
Route::prefix('alerts')->group(function () {
    Route::get('/dashboard', [AlertController::class, 'dashboard']);
    Route::get('/meta', [AlertController::class, 'meta']);
    Route::get('/rules', [AlertController::class, 'rules']);
    Route::post('/rules', [AlertController::class, 'storeRule']);
    Route::put('/rules/{id}', [AlertController::class, 'updateRule'])->whereNumber('id');
    Route::delete('/rules/{id}', [AlertController::class, 'destroyRule'])->whereNumber('id');
    Route::get('/events', [AlertController::class, 'events']);
    Route::get('/events/{id}', [AlertController::class, 'showEvent'])->whereNumber('id');
    Route::post('/events/{id}/acknowledge', [AlertController::class, 'acknowledgeEvent'])->whereNumber('id');
    Route::post('/events/{id}/resolve', [AlertController::class, 'resolveEvent'])->whereNumber('id');
    Route::post('/fire', [AlertController::class, 'fire']);
    Route::post('/evaluate', [AlertController::class, 'evaluate']);
    Route::get('/integrations', [AlertController::class, 'integrations']);
    Route::post('/integrations', [AlertController::class, 'storeIntegration']);
    Route::put('/integrations/{id}', [AlertController::class, 'updateIntegration'])->whereNumber('id');
    Route::delete('/integrations/{id}', [AlertController::class, 'destroyIntegration'])->whereNumber('id');
    Route::post('/integrations/{id}/test', [AlertController::class, 'testIntegration'])->whereNumber('id');
});

Route::prefix('ai')->group(function () {
    Route::get('/revenue-forecast', [AiIntelligenceController::class, 'revenueForecast']);
    Route::get('/churn-prediction', [AiIntelligenceController::class, 'churnPrediction']);
    Route::get('/adaptive-security', [AiIntelligenceController::class, 'adaptiveSecurity']);
    Route::post('/adaptive-security/clear-cache', [AiIntelligenceController::class, 'clearAdaptiveCache']);
    Route::get('/pricing-suggestions', [AiIntelligenceController::class, 'pricingSuggestions']);
    Route::post('/sdk-config', [AiIntelligenceController::class, 'generateSdkConfig']);
    Route::get('/sdk-options', [AiIntelligenceController::class, 'sdkOptions']);
    Route::post('/generate-tests', [AiIntelligenceController::class, 'generateTests']);
    Route::post('/generate-tests-batch', [AiIntelligenceController::class, 'generateTestsBatch']);
    Route::post('/generate-all-tests', [AiIntelligenceController::class, 'generateAllTests']);
    Route::get('/test-frameworks', [AiIntelligenceController::class, 'testFrameworks']);
});

Route::prefix('ai-ops')->group(function () {
    Route::get('/dashboard', [AIOpsAnalystController::class, 'dashboard']);
    Route::get('/templates', [AIOpsAnalystController::class, 'templates']);
    Route::post('/run-template', [AIOpsAnalystController::class, 'runTemplate']);
    Route::post('/ask', [AIOpsAnalystController::class, 'ask']);
});

// ── 端点用量 / 演示预约 / 账号注销 ──
Route::prefix('usage/endpoint')->group(function () {
    Route::get('/dashboard', [EndpointUsageAnalyticsController::class, 'dashboard']);
    Route::get('/overview', [EndpointUsageAnalyticsController::class, 'overview']);
    Route::get('/trend', [EndpointUsageAnalyticsController::class, 'trend']);
    Route::get('/latency', [EndpointUsageAnalyticsController::class, 'latency']);
    Route::get('/errors', [EndpointUsageAnalyticsController::class, 'errors']);
    Route::get('/alerts', [EndpointUsageAnalyticsController::class, 'alerts']);
});

Route::post('/demo-booking', [DemoBookingController::class, 'submit']);
Route::prefix('admin/demo-booking')->group(function () {
    Route::get('/stats', [DemoBookingController::class, 'stats']);
    Route::get('/calendly', [DemoBookingController::class, 'calendly']);
    Route::get('/', [DemoBookingController::class, 'index']);
    Route::post('/{id}/status', [DemoBookingController::class, 'updateStatus'])->whereNumber('id');
});

$deletionRoutes = function () {
    Route::get('/check', [DeletionController::class, 'checkDeletability']);
    Route::get('/reasons', [DeletionController::class, 'cancellationReasons']);
    Route::post('/', [DeletionController::class, 'requestDeletion']);
};
Route::prefix('account/deletion')->group($deletionRoutes);
Route::prefix('api/account/deletion')->group($deletionRoutes);

Route::prefix('admin/deletion')->group(function () {
    Route::get('/records', [DeletionController::class, 'deletionRecords']);
    Route::get('/stats', [DeletionController::class, 'stats']);
    Route::post('/admin/anonymize', [DeletionController::class, 'adminAnonymize']);
});
Route::prefix('api/admin/deletion')->group(function () {
    Route::get('/records', [DeletionController::class, 'deletionRecords']);
    Route::get('/stats', [DeletionController::class, 'stats']);
    Route::post('/admin/anonymize', [DeletionController::class, 'adminAnonymize']);
});

// ── 收益通知 / 设备指纹漂移 / 自动发货 ──
Route::prefix('earnings/notifications')->group(function () {
    Route::get('/stats', [EarningNotificationController::class, 'stats']);
    Route::get('/preferences', [EarningNotificationController::class, 'preferences']);
    Route::put('/preferences', [EarningNotificationController::class, 'updatePreferences']);
    Route::post('/mark-all-read', [EarningNotificationController::class, 'markAllAsRead']);
    Route::get('/', [EarningNotificationController::class, 'index']);
    Route::post('/{id}/read', [EarningNotificationController::class, 'markAsRead'])->whereNumber('id');
});

Route::prefix('fingerprint-drift')->group(function () {
    Route::get('/dashboard', [FingerprintDriftController::class, 'dashboard']);
    Route::get('/pending', [FingerprintDriftController::class, 'pendingDrifts']);
    Route::get('/device/{deviceId}', [FingerprintDriftController::class, 'deviceHistory']);
    Route::post('/snapshot', [FingerprintDriftController::class, 'recordSnapshot']);
    Route::post('/{historyId}/accept', [FingerprintDriftController::class, 'acceptDrift'])->whereNumber('historyId');
});

Route::prefix('auto-delivery')->group(function () {
    Route::get('/dashboard', [AutoDeliveryController::class, 'dashboard']);
    Route::get('/stats', [AutoDeliveryController::class, 'stats']);
    Route::post('/batch-retry', [AutoDeliveryController::class, 'batchRetry']);
    Route::get('/', [AutoDeliveryController::class, 'index']);
    Route::get('/{id}', [AutoDeliveryController::class, 'show'])->whereNumber('id');
    Route::post('/{orderId}/execute', [AutoDeliveryController::class, 'execute'])->whereNumber('orderId');
    Route::post('/{deliveryId}/retry', [AutoDeliveryController::class, 'retry'])->whereNumber('deliveryId');
    Route::post('/{deliveryId}/resend', [AutoDeliveryController::class, 'resend'])->whereNumber('deliveryId');
});

// ── 客户审计日志 ──
Route::prefix('customer/audit-logs')->group(function () {
    Route::get('/stats', [CustomerAuditLogController::class, 'stats']);
    Route::get('/action-categories', [CustomerAuditLogController::class, 'actionCategories']);
    Route::get('/export', [CustomerAuditLogController::class, 'export']);
    Route::get('/', [CustomerAuditLogController::class, 'index']);
    Route::get('/{id}', [CustomerAuditLogController::class, 'show'])->whereNumber('id');
});

// ── 推广增强 / 代理管理 / 代理层级 ──
Route::prefix('affiliate/enhanced')->group(function () {
    Route::post('/generate-link', [AffiliateEnhancedController::class, 'generateLink']);
    Route::post('/settle-commission', [AffiliateEnhancedController::class, 'settleCommission']);
    Route::post('/attribute', [AffiliateEnhancedController::class, 'attributeWithSettlement']);
    Route::post('/product-link', [AffiliateEnhancedController::class, 'productLink']);
    Route::post('/attribute-order', [AffiliateEnhancedController::class, 'attributeOrder']);
    Route::get('/store-dashboard', [AffiliateEnhancedController::class, 'storeDashboard']);
    Route::get('/product-stats/{productId}', [AffiliateEnhancedController::class, 'productStats'])->whereNumber('productId');
    Route::get('/agents/{agentId}/links', [AffiliateEnhancedController::class, 'agentLinks'])->whereNumber('agentId');
    Route::get('/agents/{agentId}/portal', [AffiliateEnhancedController::class, 'agentPortal'])->whereNumber('agentId');
});

Route::prefix('agent-manager')->group(function () {
    Route::get('/dashboard', [AgentManagerController::class, 'dashboard']);
    Route::get('/leaderboard', [AgentManagerController::class, 'leaderboard']);
    Route::get('/agents', [AgentManagerController::class, 'index']);
    Route::post('/agents', [AgentManagerController::class, 'store']);
    Route::get('/agents/{id}', [AgentManagerController::class, 'show'])->whereNumber('id');
    Route::put('/agents/{id}', [AgentManagerController::class, 'update'])->whereNumber('id');
    Route::post('/agents/{id}/approve', [AgentManagerController::class, 'approve'])->whereNumber('id');
    Route::get('/agents/{id}/performance', [AgentManagerController::class, 'performance'])->whereNumber('id');
});

Route::prefix('agent-tiers')->group(function () {
    Route::get('/overview', [AgentTierController::class, 'platformOverview']);
    Route::get('/rules', [AgentTierController::class, 'promotionRules']);
    Route::put('/rules/{rule}', [AgentTierController::class, 'updateRule'])->whereNumber('rule');
    Route::get('/history', [AgentTierController::class, 'history']);
    Route::post('/init', [AgentTierController::class, 'initTiers']);
    Route::post('/auto-promote', [AgentTierController::class, 'autoPromote']);
    Route::get('/', [AgentTierController::class, 'tierDefinitions']);
    Route::put('/{tierDefinition}', [AgentTierController::class, 'updateTier'])->whereNumber('tierDefinition');
    Route::get('/agents/{agent}/evaluate', [AgentTierController::class, 'evaluateAgent'])->whereNumber('agent');
    Route::post('/agents/{agent}/promote', [AgentTierController::class, 'promoteAgent'])->whereNumber('agent');
    Route::post('/agents/{agent}/demote', [AgentTierController::class, 'demoteAgent'])->whereNumber('agent');
    Route::get('/agents/{agent}/report', [AgentTierController::class, 'agentReport'])->whereNumber('agent');
});

// ── 电商仪表盘 / 分析 / 商城 API ──
Route::prefix('ecommerce-dashboard')->group(function () {
    Route::get('/', [EcommerceDashboardController::class, 'dashboard']);
    Route::get('/today', [EcommerceDashboardController::class, 'today']);
    Route::get('/product-ranking', [EcommerceDashboardController::class, 'productRanking']);
    Route::get('/payment-success-rate', [EcommerceDashboardController::class, 'paymentSuccessRate']);
    Route::get('/refund-rate', [EcommerceDashboardController::class, 'refundRate']);
    Route::get('/trend', [EcommerceDashboardController::class, 'trend']);
});

Route::prefix('ecommerce-analytics')->group(function () {
    Route::get('/dashboard', [EcommerceAnalyticsController::class, 'dashboard']);
    Route::get('/summary', [EcommerceAnalyticsController::class, 'summary']);
    Route::get('/sales-trend', [EcommerceAnalyticsController::class, 'salesTrend']);
    Route::get('/product-ranking', [EcommerceAnalyticsController::class, 'productRanking']);
    Route::get('/repurchase-rate', [EcommerceAnalyticsController::class, 'repurchaseRate']);
    Route::get('/payment-channels', [EcommerceAnalyticsController::class, 'paymentChannels']);
    Route::get('/customer-metrics', [EcommerceAnalyticsController::class, 'customerMetrics']);
    Route::get('/comparison', [EcommerceAnalyticsController::class, 'comparison']);
    Route::get('/forecast', [EcommerceAnalyticsController::class, 'forecast']);
    Route::get('/export-csv', [EcommerceAnalyticsController::class, 'exportCsv']);
});

$shopRoutes = function () {
    Route::get('/products', [EcommerceAPIController::class, 'products']);
    Route::get('/products/suggest', [EcommerceAPIController::class, 'productSuggest']);
    Route::get('/search/hot-terms', [EcommerceAPIController::class, 'hotSearchTerms']);
    Route::get('/search/history', [EcommerceAPIController::class, 'searchHistory']);
    Route::delete('/search/history', [EcommerceAPIController::class, 'clearSearchHistory']);
    Route::get('/filter-tags', [EcommerceAPIController::class, 'filterTags']);
    Route::get('/products/{id}', [EcommerceAPIController::class, 'productDetail'])->whereNumber('id');
    Route::get('/skus/{id}', [EcommerceAPIController::class, 'skuDetail'])->whereNumber('id');
    Route::get('/cart', [EcommerceAPIController::class, 'cartShow']);
    Route::post('/cart/add', [EcommerceAPIController::class, 'cartAdd']);
    Route::put('/cart/update', [EcommerceAPIController::class, 'cartUpdate']);
    Route::delete('/cart/remove', [EcommerceAPIController::class, 'cartRemove']);
    Route::post('/cart/clear', [EcommerceAPIController::class, 'cartClear']);
    Route::post('/cart/apply-coupon', [EcommerceAPIController::class, 'cartApplyCoupon']);
    Route::delete('/cart/coupon', [EcommerceAPIController::class, 'cartRemoveCoupon']);
    Route::post('/orders', [EcommerceAPIController::class, 'orderCreate']);
    Route::get('/orders', [EcommerceAPIController::class, 'orderList']);
    Route::get('/orders/stats', [EcommerceAPIController::class, 'orderStats']);
    Route::get('/orders/{id}', [EcommerceAPIController::class, 'orderDetail'])->whereNumber('id');
    Route::post('/orders/{id}/pay', [EcommerceAPIController::class, 'orderPay'])->whereNumber('id');
    Route::post('/orders/{id}/cancel', [EcommerceAPIController::class, 'orderCancel'])->whereNumber('id');
    Route::get('/orders/{id}/payment-status', [EcommerceAPIController::class, 'orderPaymentStatus'])->whereNumber('id');
    Route::get('/deliveries', [EcommerceAPIController::class, 'deliveryList']);
    Route::get('/deliveries/{id}', [EcommerceAPIController::class, 'deliveryDetail'])->whereNumber('id');
    Route::post('/refunds', [EcommerceAPIController::class, 'refundRequest']);
    Route::get('/refunds', [EcommerceAPIController::class, 'refundList']);
};
Route::prefix('shop')->group($shopRoutes);
Route::prefix('api/shop')->group($shopRoutes);

// ── 演示管理（后台） ──
Route::prefix('admin/demo')->group(function () {
    Route::get('/analytics', [DemoController::class, 'analytics']);
    Route::get('/config', [DemoController::class, 'getConfig']);
    Route::put('/config', [DemoController::class, 'updateConfig']);
    Route::get('/embed-code', [DemoController::class, 'embedCode']);
    Route::get('/sessions', [DemoController::class, 'sessions']);
});

// ── 自定义仪表盘 ──
Route::prefix('admin/custom-dashboards')->group(function () {
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::get('/widget-templates', [DashboardController::class, 'widgetTemplates']);
    Route::get('/', [DashboardController::class, 'index']);
    Route::post('/', [DashboardController::class, 'store']);
    Route::get('/{id}', [DashboardController::class, 'show'])->whereNumber('id');
    Route::put('/{dashboard}', [DashboardController::class, 'update']);
    Route::delete('/{dashboard}', [DashboardController::class, 'destroy']);
    Route::post('/{dashboard}/set-default', [DashboardController::class, 'setDefault']);
    Route::post('/{dashboard}/duplicate', [DashboardController::class, 'duplicate']);
    Route::post('/{dashboard}/widgets', [DashboardController::class, 'storeWidget']);
    Route::post('/{dashboard}/widgets/reorder', [DashboardController::class, 'reorderWidgets']);
    Route::put('/widgets/{widget}', [DashboardController::class, 'updateWidget']);
    Route::delete('/widgets/{widget}', [DashboardController::class, 'destroyWidget']);
    Route::get('/widgets/{widget}/data', [DashboardController::class, 'getWidgetData']);
    Route::post('/widgets/{widget}/refresh', [DashboardController::class, 'refreshWidgetData']);
});
