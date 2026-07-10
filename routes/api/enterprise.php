<?php

use App\Http\Controllers\Api\AuditRetentionPolicyController;
use App\Http\Controllers\Api\AutoRenewalController;
use App\Http\Controllers\Api\BundleController;
use App\Http\Controllers\Api\BusinessMetricsController;
use App\Http\Controllers\Api\CacheInvalidationController;
use App\Http\Controllers\Api\ChannelPartnerController;
use App\Http\Controllers\Api\ChurnPredictionController;
use App\Http\Controllers\Api\CollaborationController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CommissionRiskController;
use App\Http\Controllers\Api\ConversionFunnelController;
use App\Http\Controllers\Api\CrmController;
use App\Http\Controllers\Api\CustomFieldController;
use App\Http\Controllers\Api\DatabaseReadWriteController;
use App\Http\Controllers\Api\DataAnonymizationController;
use App\Http\Controllers\Api\DataImportController;
use App\Http\Controllers\Api\DataLineageController;
use App\Http\Controllers\Api\DataRetentionController;
use App\Http\Controllers\Api\DeployController;
use App\Http\Controllers\Api\DunningController;
use App\Http\Controllers\Api\EnterpriseSsoController;
use App\Http\Controllers\Api\MarketingCampaignController;
use App\Http\Controllers\Api\PostmanController;
use App\Http\Controllers\Api\SystemHealthController;
use App\Http\Controllers\Api\TenantIsolationController;

// ── 业务指标 ──
Route::prefix('admin/business-metrics')->group(function () {
    Route::get('/dashboard', [BusinessMetricsController::class, 'dashboard']);
    Route::get('/overview', [BusinessMetricsController::class, 'overview']);
    Route::get('/mrr-trend', [BusinessMetricsController::class, 'mrrTrend']);
    Route::get('/metric-trends', [BusinessMetricsController::class, 'metricTrends']);
    Route::get('/churn-trend', [BusinessMetricsController::class, 'churnTrend']);
    Route::get('/cohort-analysis', [BusinessMetricsController::class, 'cohortAnalysis']);
    Route::get('/export', [BusinessMetricsController::class, 'export']);
});

// ── 系统健康 ──
Route::prefix('admin/system-health')->group(function () {
    Route::get('/dashboard', [SystemHealthController::class, 'dashboard']);
    Route::get('/check', [SystemHealthController::class, 'check']);
    Route::get('/trend', [SystemHealthController::class, 'trend']);
    Route::post('/snapshot', [SystemHealthController::class, 'snapshot']);
    Route::get('/thresholds', [SystemHealthController::class, 'thresholds']);
    Route::put('/thresholds/{id}', [SystemHealthController::class, 'updateThreshold'])->whereNumber('id');
    Route::get('/failed-jobs', [SystemHealthController::class, 'failedJobs']);
});

// ── 营销自动化 ──
Route::prefix('admin/marketing')->group(function () {
    Route::get('/dashboard', [MarketingCampaignController::class, 'dashboard']);
    Route::get('/stats', [MarketingCampaignController::class, 'stats']);
    Route::post('/preview-audience', [MarketingCampaignController::class, 'previewAudience']);
    Route::get('/campaigns', [MarketingCampaignController::class, 'index']);
    Route::post('/campaigns', [MarketingCampaignController::class, 'store']);
    Route::get('/campaigns/{campaignId}', [MarketingCampaignController::class, 'show'])->whereNumber('campaignId');
    Route::put('/campaigns/{campaignId}', [MarketingCampaignController::class, 'update'])->whereNumber('campaignId');
    Route::delete('/campaigns/{campaignId}', [MarketingCampaignController::class, 'destroy'])->whereNumber('campaignId');
    Route::post('/campaigns/{campaignId}/launch', [MarketingCampaignController::class, 'launch'])->whereNumber('campaignId');
    Route::post('/campaigns/{campaignId}/toggle', [MarketingCampaignController::class, 'toggle'])->whereNumber('campaignId');
    Route::post('/campaigns/{campaignId}/complete', [MarketingCampaignController::class, 'complete'])->whereNumber('campaignId');
    Route::post('/campaigns/{campaignId}/cancel', [MarketingCampaignController::class, 'cancel'])->whereNumber('campaignId');
    Route::put('/campaigns/{campaignId}/steps', [MarketingCampaignController::class, 'updateSteps'])->whereNumber('campaignId');
    Route::post('/campaigns/{campaignId}/simulate', [MarketingCampaignController::class, 'simulateSend'])->whereNumber('campaignId');
    Route::get('/campaigns/{campaignId}/analytics', [MarketingCampaignController::class, 'analytics'])->whereNumber('campaignId');
});

// ── 部署管理 ──
Route::prefix('admin/deploy')->group(function () {
    Route::get('/dashboard', [DeployController::class, 'dashboard']);
    Route::get('/environments', [DeployController::class, 'environments']);
    Route::post('/environments', [DeployController::class, 'storeEnvironment']);
    Route::put('/environments/{deployEnvironment}', [DeployController::class, 'updateEnvironment'])->whereNumber('deployEnvironment');
    Route::delete('/environments/{deployEnvironment}', [DeployController::class, 'deleteEnvironment'])->whereNumber('deployEnvironment');
    Route::get('/releases', [DeployController::class, 'releases']);
    Route::post('/releases', [DeployController::class, 'storeRelease']);
    Route::put('/releases/{deployRelease}', [DeployController::class, 'updateRelease'])->whereNumber('deployRelease');
    Route::delete('/releases/{deployRelease}', [DeployController::class, 'deleteRelease'])->whereNumber('deployRelease');
    Route::get('/jobs', [DeployController::class, 'jobs']);
    Route::get('/jobs/{deployJob}', [DeployController::class, 'jobDetail'])->whereNumber('deployJob');
    Route::post('/trigger', [DeployController::class, 'triggerDeploy']);
    Route::post('/jobs/{deployJob}/complete', [DeployController::class, 'completeDeploy'])->whereNumber('deployJob');
    Route::post('/jobs/{deployJob}/rollback', [DeployController::class, 'rollbackDeploy'])->whereNumber('deployJob');
});

// ── Postman 集合导出 ──
Route::prefix('admin/postman')->group(function () {
    Route::get('/collection', [PostmanController::class, 'downloadCollection']);
    Route::get('/environment/{envName}', [PostmanController::class, 'downloadEnvironment']);
    Route::get('/environments', [PostmanController::class, 'environments']);
    Route::get('/run-in-postman', [PostmanController::class, 'runInPostman']);
    Route::get('/stats', [PostmanController::class, 'stats']);
});

// ── 产品捆绑包 ──
Route::prefix('admin/bundles')->group(function () {
    Route::get('/stats', [BundleController::class, 'stats']);
    Route::get('/available-items', [BundleController::class, 'availableItems']);
    Route::get('/purchases', [BundleController::class, 'purchases']);
    Route::get('/', [BundleController::class, 'index']);
    Route::post('/', [BundleController::class, 'store']);
    Route::get('/{id}', [BundleController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [BundleController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [BundleController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/purchase', [BundleController::class, 'purchase'])->whereNumber('id');
});

// ── 数据导入 ──
Route::prefix('admin/data-import')->group(function () {
    Route::get('/entity-types', [DataImportController::class, 'entityTypes']);
    Route::get('/entity-fields/{entityType}', [DataImportController::class, 'entityFields']);
    Route::get('/generate-template/{entityType}', [DataImportController::class, 'generateTemplate']);
    Route::post('/upload', [DataImportController::class, 'upload']);
    Route::get('/mapping-templates', [DataImportController::class, 'mappingTemplates']);
    Route::post('/mapping-templates', [DataImportController::class, 'storeMappingTemplate']);
    Route::delete('/mapping-templates/{id}', [DataImportController::class, 'destroyMappingTemplate'])->whereNumber('id');
    Route::get('/tasks', [DataImportController::class, 'index']);
    Route::get('/tasks/{importTask}', [DataImportController::class, 'show'])->whereNumber('importTask');
    Route::delete('/tasks/{importTask}', [DataImportController::class, 'destroy'])->whereNumber('importTask');
    Route::post('/tasks/{importTask}/parse', [DataImportController::class, 'parse'])->whereNumber('importTask');
    Route::put('/tasks/{importTask}/mappings', [DataImportController::class, 'updateMappings'])->whereNumber('importTask');
    Route::post('/tasks/{importTask}/validate', [DataImportController::class, 'validate'])->whereNumber('importTask');
    Route::post('/tasks/{importTask}/execute', [DataImportController::class, 'execute'])->whereNumber('importTask');
    Route::get('/tasks/{importTask}/logs', [DataImportController::class, 'logs'])->whereNumber('importTask');
    Route::post('/tasks/{importTask}/cancel', [DataImportController::class, 'cancel'])->whereNumber('importTask');
    Route::post('/tasks/{importTask}/apply-template', [DataImportController::class, 'applyMappingTemplate'])->whereNumber('importTask');
});

// ── 数据留存 ──
Route::prefix('admin/data-retention')->group(function () {
    Route::get('/dashboard', [DataRetentionController::class, 'dashboard']);
    Route::get('/policies', [DataRetentionController::class, 'policies']);
    Route::post('/policies/sync', [DataRetentionController::class, 'syncPolicies']);
    Route::put('/policies/{dataRetentionPolicy}', [DataRetentionController::class, 'updatePolicy'])->whereNumber('dataRetentionPolicy');
    Route::post('/cleanup', [DataRetentionController::class, 'cleanup']);
    Route::get('/executions', [DataRetentionController::class, 'executions']);
    Route::get('/storage-stats', [DataRetentionController::class, 'storageStats']);
});

// ── 审计日志保留策略 ──
Route::prefix('admin/audit-retention-policies')->group(function () {
    Route::get('/overview', [AuditRetentionPolicyController::class, 'overview']);
    Route::post('/preview-prune', [AuditRetentionPolicyController::class, 'previewPrune']);
    Route::get('/', [AuditRetentionPolicyController::class, 'index']);
    Route::post('/', [AuditRetentionPolicyController::class, 'store']);
    Route::put('/{id}', [AuditRetentionPolicyController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [AuditRetentionPolicyController::class, 'destroy'])->whereNumber('id');
});

// ── 流失预测干预 ──
Route::prefix('admin/churn-prediction')->group(function () {
    Route::get('/dashboard', [ChurnPredictionController::class, 'dashboard']);
    Route::get('/trend', [ChurnPredictionController::class, 'trend']);
    Route::get('/list', [ChurnPredictionController::class, 'churnList']);
    Route::get('/interventions', [ChurnPredictionController::class, 'interventions']);
    Route::post('/interventions', [ChurnPredictionController::class, 'storeIntervention']);
    Route::put('/interventions/{churnIntervention}', [ChurnPredictionController::class, 'updateIntervention'])->whereNumber('churnIntervention');
    Route::delete('/interventions/{churnIntervention}', [ChurnPredictionController::class, 'deleteIntervention'])->whereNumber('churnIntervention');
});

// ── 转化漏斗 ──
Route::prefix('admin/conversion-funnel')->group(function () {
    Route::get('/dashboard', [ConversionFunnelController::class, 'dashboard']);
    Route::get('/data', [ConversionFunnelController::class, 'data']);
    Route::get('/by-source', [ConversionFunnelController::class, 'bySource']);
    Route::get('/trend', [ConversionFunnelController::class, 'trend']);
    Route::post('/track', [ConversionFunnelController::class, 'track']);
});

// ── 自定义字段 ──
Route::prefix('admin/custom-fields')->group(function () {
    Route::get('/metadata', [CustomFieldController::class, 'metadata']);
    Route::get('/licenses/{license}/values', [CustomFieldController::class, 'licenseValues'])->whereNumber('license');
    Route::put('/licenses/{license}/values', [CustomFieldController::class, 'updateLicenseValues'])->whereNumber('license');
    Route::get('/customers/{customer}/values', [CustomFieldController::class, 'customerValues'])->whereNumber('customer');
    Route::put('/customers/{customer}/values', [CustomFieldController::class, 'updateCustomerValues'])->whereNumber('customer');
    Route::get('/products/{product}/values', [CustomFieldController::class, 'productValues'])->whereNumber('product');
    Route::put('/products/{product}/values', [CustomFieldController::class, 'updateProductValues'])->whereNumber('product');
    Route::get('/', [CustomFieldController::class, 'index']);
    Route::post('/', [CustomFieldController::class, 'store']);
    Route::put('/{id}', [CustomFieldController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [CustomFieldController::class, 'destroy'])->whereNumber('id');
});
Route::prefix('custom-fields')->group(function () {
    Route::get('/metadata', [CustomFieldController::class, 'metadata']);
    Route::get('/', [CustomFieldController::class, 'index']);
    Route::post('/', [CustomFieldController::class, 'store']);
    Route::put('/{id}', [CustomFieldController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [CustomFieldController::class, 'destroy'])->whereNumber('id');
});

// ── 数据库读写分离 ──
Route::prefix('admin/db-read-write')->group(function () {
    Route::get('/status', [DatabaseReadWriteController::class, 'status']);
    Route::post('/reset-circuit-breaker', [DatabaseReadWriteController::class, 'resetCircuitBreaker']);
    Route::post('/health-check', [DatabaseReadWriteController::class, 'healthCheck']);
    Route::get('/cache-status', [DatabaseReadWriteController::class, 'cacheStatus']);
    Route::post('/trigger-warmup', [DatabaseReadWriteController::class, 'triggerWarmup']);
});

// ── 协作（备注/动态/关注） ──
Route::prefix('admin')->group(function () {
    Route::post('/notes/counts', [CollaborationController::class, 'noteCounts']);
    Route::put('/notes/{id}', [CollaborationController::class, 'updateNote'])->whereNumber('id');
    Route::delete('/notes/{id}', [CollaborationController::class, 'deleteNote'])->whereNumber('id');
    Route::post('/notes/{id}/toggle-pin', [CollaborationController::class, 'togglePin'])->whereNumber('id');
    Route::get('/activity-feed', [CollaborationController::class, 'activityFeed']);
    Route::get('/activity-feed/mine', [CollaborationController::class, 'myActivityFeed']);
    Route::post('/activities/last-timestamps', [CollaborationController::class, 'lastActivityTimestamps']);
    Route::get('/canned-replies', [CollaborationController::class, 'cannedReplies']);
    Route::post('/canned-replies', [CollaborationController::class, 'createCannedReply']);
    Route::put('/canned-replies/{id}', [CollaborationController::class, 'updateCannedReply'])->whereNumber('id');
    Route::delete('/canned-replies/{id}', [CollaborationController::class, 'deleteCannedReply'])->whereNumber('id');
    Route::get('/watchlist', [CollaborationController::class, 'watchlist']);
    Route::get('/collaboration-preferences', [CollaborationController::class, 'preferences']);
    Route::put('/collaboration-preferences', [CollaborationController::class, 'updatePreferences']);
    Route::get('/{entityType}/{entityId}/notes', [CollaborationController::class, 'notes'])->where('entityType', '[a-z_-]+')->whereNumber('entityId');
    Route::post('/{entityType}/{entityId}/notes', [CollaborationController::class, 'createNote'])->where('entityType', '[a-z_-]+')->whereNumber('entityId');
    Route::get('/{entityType}/{entityId}/change-logs', [CollaborationController::class, 'changeLogs'])->where('entityType', '[a-z_-]+')->whereNumber('entityId');
    Route::get('/{entityType}/{entityId}/is-watching', [CollaborationController::class, 'isWatching'])->where('entityType', '[a-z_-]+')->whereNumber('entityId');
    Route::post('/{entityType}/{entityId}/toggle-watch', [CollaborationController::class, 'toggleWatch'])->where('entityType', '[a-z_-]+')->whereNumber('entityId');
});

// ── 租户隔离（前端 /admin 路径别名） ──
Route::middleware(['ability:admin,super-admin'])->group(function () {
    Route::get('/admin/tenant-isolation/dashboard', [TenantIsolationController::class, 'dashboard']);
    Route::get('/admin/quota-plans', [TenantIsolationController::class, 'quotaPlans']);
    Route::post('/admin/quota-plans', [TenantIsolationController::class, 'createQuotaPlan']);
    Route::put('/admin/quota-plans/{id}', [TenantIsolationController::class, 'updateQuotaPlan'])->whereNumber('id');
    Route::delete('/admin/quota-plans/{id}', [TenantIsolationController::class, 'deleteQuotaPlan'])->whereNumber('id');
    Route::get('/admin/tenants/{tenantId}/quota', [TenantIsolationController::class, 'tenantQuota'])->whereNumber('tenantId');
    Route::put('/admin/tenants/{tenantId}/quota', [TenantIsolationController::class, 'updateTenantQuota'])->whereNumber('tenantId');
    Route::post('/admin/tenants/{tenantId}/refresh-usage', [TenantIsolationController::class, 'refreshTenantUsage'])->whereNumber('tenantId');
    Route::put('/admin/tenants/{tenantId}/isolation-level', [TenantIsolationController::class, 'updateIsolationLevel'])->whereNumber('tenantId');
    Route::get('/admin/tenants/{tenantId}/audit-logs', [TenantIsolationController::class, 'auditLogs'])->whereNumber('tenantId');
    Route::post('/admin/audit-logs/{id}/resolve', [TenantIsolationController::class, 'resolveAuditLog'])->whereNumber('id');
    Route::get('/admin/tenants/{tenantId}/shares', [TenantIsolationController::class, 'shares'])->whereNumber('tenantId');
    Route::post('/admin/shares', [TenantIsolationController::class, 'createShare']);
    Route::post('/admin/shares/{id}/revoke', [TenantIsolationController::class, 'revokeShare'])->whereNumber('id');
    Route::post('/admin/tenants/batch-refresh-usage', [TenantIsolationController::class, 'batchRefresh']);
});

// ── CRM 客户分群 ──
Route::prefix('crm')->group(function () {
    Route::get('/dashboard', [CrmController::class, 'dashboard']);
    Route::get('/segments', [CrmController::class, 'segments']);
    Route::post('/segments', [CrmController::class, 'storeSegment']);
    Route::post('/segments/assign', [CrmController::class, 'assignSegment']);
    Route::post('/segments/remove', [CrmController::class, 'removeSegmentCustomer']);
    Route::get('/segments/{customerSegment}/customers', [CrmController::class, 'segmentCustomers'])->whereNumber('customerSegment');
    Route::put('/segments/{customerSegment}', [CrmController::class, 'updateSegment'])->whereNumber('customerSegment');
    Route::delete('/segments/{customerSegment}', [CrmController::class, 'destroySegment'])->whereNumber('customerSegment');
    Route::post('/segments/{customerSegment}/refresh', [CrmController::class, 'refreshSegment'])->whereNumber('customerSegment');
    Route::get('/rfm-scores', [CrmController::class, 'rfmScores']);
    Route::post('/rfm-scores/recalculate', [CrmController::class, 'recalculateRfm']);
    Route::get('/churn-predictions', [CrmController::class, 'churnPredictions']);
    Route::post('/churn-predictions/recalculate', [CrmController::class, 'recalculateChurn']);
});

// ── 渠道合作伙伴 ──
Route::prefix('channel')->group(function () {
    Route::get('/dashboard', [ChannelPartnerController::class, 'dashboard']);
    Route::get('/partners', [ChannelPartnerController::class, 'partners']);
    Route::get('/partners/{agent}', [ChannelPartnerController::class, 'showPartner'])->whereNumber('agent');
    Route::post('/partners/{agent}/approve', [ChannelPartnerController::class, 'approvePartner'])->whereNumber('agent');
    Route::put('/partners/{agent}/level', [ChannelPartnerController::class, 'updatePartnerLevel'])->whereNumber('agent');
    Route::get('/settlements', [ChannelPartnerController::class, 'partnerSettlements']);
    Route::get('/referral-links', [ChannelPartnerController::class, 'partnerReferralLinks']);
    Route::get('/tier-benefits', [ChannelPartnerController::class, 'tierBenefits']);
    Route::get('/my/dashboard', [ChannelPartnerController::class, 'myDashboard']);
    Route::get('/my/payouts', [ChannelPartnerController::class, 'myPayouts']);
    Route::post('/my/payouts', [ChannelPartnerController::class, 'myRequestPayout']);
});

// ── 佣金管理 ──
Route::prefix('commission')->group(function () {
    Route::get('/dashboard', [CommissionController::class, 'dashboard']);
    Route::get('/my', [CommissionController::class, 'myCommission']);
    Route::get('/agents', [CommissionController::class, 'agents']);
    Route::post('/agents', [CommissionController::class, 'storeAgent']);
    Route::get('/agents/{agent}', [CommissionController::class, 'showAgent'])->whereNumber('agent');
    Route::put('/agents/{agent}', [CommissionController::class, 'updateAgent'])->whereNumber('agent');
    Route::get('/plans', [CommissionController::class, 'plans']);
    Route::post('/plans', [CommissionController::class, 'storePlan']);
    Route::put('/plans/{commissionPlan}', [CommissionController::class, 'updatePlan'])->whereNumber('commissionPlan');
    Route::get('/plans/{commissionPlan}/items', [CommissionController::class, 'planItems'])->whereNumber('commissionPlan');
    Route::post('/plans/{commissionPlan}/items', [CommissionController::class, 'storePlanItem'])->whereNumber('commissionPlan');
    Route::put('/plan-items/{commissionPlanItem}', [CommissionController::class, 'updatePlanItem'])->whereNumber('commissionPlanItem');
    Route::delete('/plan-items/{commissionPlanItem}', [CommissionController::class, 'destroyPlanItem'])->whereNumber('commissionPlanItem');
    Route::get('/settlements', [CommissionController::class, 'settlements']);
    Route::get('/payouts', [CommissionController::class, 'payouts']);
    Route::post('/payouts', [CommissionController::class, 'requestPayout']);
    Route::put('/payouts/{commissionPayout}', [CommissionController::class, 'processPayout'])->whereNumber('commissionPayout');
    Route::get('/referral-links', [CommissionController::class, 'referralLinks']);
    Route::post('/referral-links', [CommissionController::class, 'storeReferralLink']);
    Route::delete('/referral-links/{referralLink}', [CommissionController::class, 'destroyReferralLink'])->whereNumber('referralLink');
    Route::prefix('risk')->group(function () {
        Route::get('/dashboard', [CommissionRiskController::class, 'dashboard']);
        Route::get('/negative-balance', [CommissionRiskController::class, 'negativeBalanceAccounts']);
        Route::get('/negative-balance/{earningsAccount}', [CommissionRiskController::class, 'negativeBalanceDetail'])->whereNumber('earningsAccount');
        Route::post('/negative-balance/{earningsAccount}/clear', [CommissionRiskController::class, 'clearNegativeBalance'])->whereNumber('earningsAccount');
        Route::get('/payouts/pending-review', [CommissionRiskController::class, 'pendingReviewPayouts']);
        Route::post('/payouts/{commissionPayout}/review', [CommissionRiskController::class, 'reviewPayout'])->whereNumber('commissionPayout');
        Route::post('/run-task', [CommissionRiskController::class, 'runRiskTasks']);
    });
});

// ── 催款 (Dunning) ──
Route::prefix('dunning')->group(function () {
    Route::get('/dashboard', [DunningController::class, 'dashboard']);
    Route::get('/queue', [DunningController::class, 'queue']);
    Route::get('/queue/{dunningQueue}', [DunningController::class, 'showQueue'])->whereNumber('dunningQueue');
    Route::post('/queue/{dunningQueue}/resolve', [DunningController::class, 'resolve'])->whereNumber('dunningQueue');
    Route::get('/logs', [DunningController::class, 'logs']);
    Route::get('/strategies', [DunningController::class, 'strategies']);
    Route::post('/strategies', [DunningController::class, 'storeStrategy']);
    Route::put('/strategies/{dunningStrategy}', [DunningController::class, 'updateStrategy'])->whereNumber('dunningStrategy');
    Route::delete('/strategies/{dunningStrategy}', [DunningController::class, 'destroyStrategy'])->whereNumber('dunningStrategy');
    Route::post('/enqueue', [DunningController::class, 'enqueue']);
    Route::post('/run', [DunningController::class, 'run']);
    Route::post('/scan-overdue', [DunningController::class, 'scanOverdue']);
});

// ── 数据血缘 ──
Route::prefix('data-lineage')->group(function () {
    Route::get('/dashboard', [DataLineageController::class, 'dashboard']);
    Route::get('/export', [DataLineageController::class, 'export']);
    Route::get('/show', [DataLineageController::class, 'show']);
    Route::get('/tracked-objects', [DataLineageController::class, 'trackedObjects']);
    Route::get('/chain/{id}', [DataLineageController::class, 'chain'])->whereNumber('id');
    Route::get('/', [DataLineageController::class, 'index']);
    Route::post('/', [DataLineageController::class, 'store']);
});

// ── SDK 缓存失效推送 ──
$cacheInvalidationRoutes = function () {
    Route::get('/events', [CacheInvalidationController::class, 'stream']);
    Route::get('/pending', [CacheInvalidationController::class, 'pending']);
    Route::get('/stats', [CacheInvalidationController::class, 'stats']);
    Route::post('/invalidate', [CacheInvalidationController::class, 'invalidate']);
    Route::post('/invalidate-batch', [CacheInvalidationController::class, 'invalidateBatch']);
    Route::get('/webhooks', [CacheInvalidationController::class, 'webhooks']);
    Route::post('/webhooks', [CacheInvalidationController::class, 'storeWebhook']);
    Route::put('/webhooks/{webhook}', [CacheInvalidationController::class, 'updateWebhook'])->whereNumber('webhook');
    Route::delete('/webhooks/{webhook}', [CacheInvalidationController::class, 'destroyWebhook'])->whereNumber('webhook');
};
Route::prefix('sdk/cache')->group($cacheInvalidationRoutes);
Route::prefix('api/sdk/cache')->group($cacheInvalidationRoutes);

// ── 数据脱敏 ──
$dataAnonymizationRoutes = function () {
    Route::get('/tables', [DataAnonymizationController::class, 'tables']);
    Route::post('/export', [DataAnonymizationController::class, 'export']);
    Route::post('/preview', [DataAnonymizationController::class, 'preview']);
    Route::get('/tasks', [DataAnonymizationController::class, 'tasks']);
    Route::get('/tasks/{task}', [DataAnonymizationController::class, 'showTask'])->whereNumber('task');
    Route::post('/tasks/{task}/retry', [DataAnonymizationController::class, 'retryTask'])->whereNumber('task');
    Route::get('/rules', [DataAnonymizationController::class, 'rules']);
    Route::post('/rules', [DataAnonymizationController::class, 'storeRule']);
    Route::delete('/rules/{rule}', [DataAnonymizationController::class, 'destroyRule'])->whereNumber('rule');
    Route::get('/', [DataAnonymizationController::class, 'index']);
    Route::post('/', [DataAnonymizationController::class, 'store']);
    Route::get('/{rule}', [DataAnonymizationController::class, 'show'])->whereNumber('rule');
    Route::put('/{rule}', [DataAnonymizationController::class, 'update'])->whereNumber('rule');
    Route::delete('/{rule}', [DataAnonymizationController::class, 'destroy'])->whereNumber('rule');
};
Route::prefix('data-anonymization')->group($dataAnonymizationRoutes);
Route::prefix('api/data-anonymization')->group($dataAnonymizationRoutes);

// ── 企业 SSO ──
Route::prefix('admin/enterprise-sso')->group(function () {
    Route::get('/stats', [EnterpriseSsoController::class, 'stats']);
    Route::get('/idps', [EnterpriseSsoController::class, 'idps']);
    Route::post('/idps', [EnterpriseSsoController::class, 'storeIdp']);
    Route::put('/idps/{enterpriseIdp}', [EnterpriseSsoController::class, 'updateIdp']);
    Route::delete('/idps/{enterpriseIdp}', [EnterpriseSsoController::class, 'destroyIdp']);
    Route::get('/idps/{enterpriseIdp}/sp-metadata', [EnterpriseSsoController::class, 'spMetadata']);
    Route::post('/parse-metadata', [EnterpriseSsoController::class, 'parseMetadata']);
    Route::get('/idps/{enterpriseIdp}/domains', [EnterpriseSsoController::class, 'domainRoutes']);
    Route::post('/idps/{enterpriseIdp}/domains', [EnterpriseSsoController::class, 'storeDomainRoute']);
    Route::delete('/domains/{idpDomainRoute}', [EnterpriseSsoController::class, 'destroyDomainRoute']);
    Route::get('/idps/{enterpriseIdp}/group-mappings', [EnterpriseSsoController::class, 'groupMappings']);
    Route::post('/idps/{enterpriseIdp}/group-mappings', [EnterpriseSsoController::class, 'storeGroupMapping']);
    Route::get('/idps/{enterpriseIdp}/jit-rules', [EnterpriseSsoController::class, 'jitRules']);
    Route::post('/idps/{enterpriseIdp}/jit-rules', [EnterpriseSsoController::class, 'storeJitRule']);
    Route::post('/idps/{enterpriseIdp}/health-check', [EnterpriseSsoController::class, 'healthCheck']);
    Route::post('/resolve-domain', [EnterpriseSsoController::class, 'resolveDomain']);
});

// ── 自动续费管理 ──
Route::prefix('admin/auto-renewal')->group(function () {
    Route::get('/dashboard', [AutoRenewalController::class, 'dashboard']);
    Route::get('/plans', [AutoRenewalController::class, 'plans']);
    Route::post('/plans', [AutoRenewalController::class, 'storePlan']);
    Route::put('/plans/{plan}', [AutoRenewalController::class, 'updatePlan']);
    Route::get('/subscriptions', [AutoRenewalController::class, 'subscriptions']);
    Route::post('/subscriptions/{subscription}/renew', [AutoRenewalController::class, 'renew']);
    Route::post('/subscriptions/{subscription}/upgrade', [AutoRenewalController::class, 'upgrade']);
    Route::post('/subscriptions/{subscription}/downgrade', [AutoRenewalController::class, 'downgrade']);
    Route::post('/subscriptions/{subscription}/cancel', [AutoRenewalController::class, 'cancel']);
    Route::post('/subscriptions/{subscription}/pause', [AutoRenewalController::class, 'pause']);
    Route::post('/subscriptions/{subscription}/resume', [AutoRenewalController::class, 'resume']);
    Route::get('/subscriptions/{subscription}/attempts', [AutoRenewalController::class, 'attempts']);
});
