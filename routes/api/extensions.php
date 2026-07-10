<?php

use App\Http\Controllers\Api\BillingHistoryPortalController;
use App\Http\Controllers\Api\ChangelogController;
use App\Http\Controllers\Api\ComplianceReportAiController;
use App\Http\Controllers\Api\CustomerDataExportController;
use App\Http\Controllers\Api\GraphQLController;
use App\Http\Controllers\Api\GrpcController;
use App\Http\Controllers\Api\InnovationAuthController;
use App\Http\Controllers\Api\InvoiceEnhancementController;
use App\Http\Controllers\Api\LicenseMergeController;
use App\Http\Controllers\Api\LicenseNoteController;
use App\Http\Controllers\Api\LicenseRestrictionController;
use App\Http\Controllers\Api\MigrationAssistantController;
use App\Http\Controllers\Api\PrepaidBalanceController;
use App\Http\Controllers\Api\PromotionEngineController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\RenewalReminderController;
use App\Http\Controllers\Api\ReportBuilderController;
use App\Http\Controllers\Api\ReportSchedulerController;
use App\Http\Controllers\Api\ResaleController;
use App\Http\Controllers\Api\RoiCalculatorController;
use App\Http\Controllers\Api\SavedSearchController;
use App\Http\Controllers\Api\ScheduledNotificationController;
use App\Http\Controllers\Api\ScheduledPromotionController;
use App\Http\Controllers\Api\SloController;
use App\Http\Controllers\Api\SmartContractController;
use App\Http\Controllers\Api\SslCertificateController;
use App\Http\Controllers\Api\StatusPageController;
use App\Http\Controllers\Api\TaxComplianceController;
use App\Http\Controllers\Api\TenantTeamController;
use App\Http\Controllers\Api\TracingController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\VasAdminController;
use App\Http\Controllers\Api\WebhookSimulatorController;
use App\Http\Controllers\Api\WorkflowDashboardController;
use App\Http\Controllers\Api\ZapierController;

// ── 更新日志 ──
Route::prefix('admin/changelog')->group(function () {
    Route::get('/', [ChangelogController::class, 'index']);
    Route::post('/', [ChangelogController::class, 'store']);
    Route::get('/stats', [ChangelogController::class, 'stats']);
    Route::post('/auto-generate', [ChangelogController::class, 'autoGenerate']);
    Route::post('/create-snapshot', [ChangelogController::class, 'createSnapshot']);
    Route::get('/auto-detect-history', [ChangelogController::class, 'autoDetectHistory']);
    Route::post('/migration-guide', [ChangelogController::class, 'migrationGuide']);
    Route::get('/{id}', [ChangelogController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [ChangelogController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [ChangelogController::class, 'destroy'])->whereNumber('id');
});

$changelogPublicRoutes = function () {
    Route::get('/', [ChangelogController::class, 'publicIndex']);
    Route::get('/latest', [ChangelogController::class, 'publicLatest']);
    Route::get('/versions', [ChangelogController::class, 'publicByVersion']);
};
Route::prefix('changelog')->group($changelogPublicRoutes);
Route::prefix('api/changelog')->group($changelogPublicRoutes);

// ── 发票增强 ──
Route::prefix('admin/invoice-enhance')->group(function () {
    Route::get('/templates', [InvoiceEnhancementController::class, 'templates']);
    Route::post('/templates', [InvoiceEnhancementController::class, 'storeTemplate']);
    Route::put('/templates/{invoiceTemplate}', [InvoiceEnhancementController::class, 'updateTemplate']);
    Route::delete('/templates/{invoiceTemplate}', [InvoiceEnhancementController::class, 'destroyTemplate']);
    Route::get('/default-template', [InvoiceEnhancementController::class, 'defaultTemplate']);
    Route::get('/reconciliations', [InvoiceEnhancementController::class, 'reconciliations']);
    Route::post('/reconciliations', [InvoiceEnhancementController::class, 'storeReconciliation']);
    Route::post('/reconciliations/{invoiceReconciliation}/resolve', [InvoiceEnhancementController::class, 'resolveReconciliation']);
    Route::get('/reconciliation-stats', [InvoiceEnhancementController::class, 'reconciliationStats']);
    Route::post('/auto-reconcile', [InvoiceEnhancementController::class, 'autoReconcile']);
    Route::get('/splits', [InvoiceEnhancementController::class, 'splits']);
    Route::post('/split', [InvoiceEnhancementController::class, 'split']);
    Route::get('/stats', [InvoiceEnhancementController::class, 'enhancedStats']);
});

// ── License 转移（管理端） ──
Route::prefix('transfers')->group(function () {
    Route::get('/', [TransferController::class, 'index']);
    Route::post('/', [TransferController::class, 'store']);
    Route::get('/stats', [TransferController::class, 'stats']);
    Route::get('/{transfer}', [TransferController::class, 'show']);
    Route::post('/{transfer}/approve', [TransferController::class, 'approve']);
    Route::post('/{transfer}/reject', [TransferController::class, 'reject']);
    Route::post('/{transfer}/cancel', [TransferController::class, 'cancel']);
    Route::post('/{transfer}/generate-code', [TransferController::class, 'generateCode']);
    Route::post('/{transfer}/verify-code', [TransferController::class, 'verifyCode']);
});

// ── 预付费余额（管理端） ──
$prepaidAdminRoutes = function () {
    Route::get('/stats', [PrepaidBalanceController::class, 'stats']);
    Route::get('/all-transactions', [PrepaidBalanceController::class, 'allTransactions']);
    Route::get('/customers/{customer}/balance', [PrepaidBalanceController::class, 'customerBalance']);
    Route::post('/customers/{customer}/recharge', [PrepaidBalanceController::class, 'adminRecharge']);
    Route::post('/customers/{customer}/deduct', [PrepaidBalanceController::class, 'adminDeduct']);
    Route::post('/customers/{customer}/adjust', [PrepaidBalanceController::class, 'adminAdjust']);
    Route::get('/customers/{customer}/transactions', [PrepaidBalanceController::class, 'adminTransactions']);
    Route::post('/customers/{customer}/credit-limit', [PrepaidBalanceController::class, 'setCreditLimit']);
    Route::get('/customers/{customer}/credit-limit', [PrepaidBalanceController::class, 'getCreditLimit']);
};
Route::prefix('billing/prepaid')->group($prepaidAdminRoutes);
Route::prefix('api/billing/prepaid')->group($prepaidAdminRoutes);

// ── 二手市场 ──
Route::prefix('admin/resale')->group(function () {
    Route::get('/marketplace', [ResaleController::class, 'browseMarketplace']);
    Route::get('/stats', [ResaleController::class, 'marketStats']);
    Route::get('/stats/seller', [ResaleController::class, 'sellerStats']);
    Route::get('/listings/sellable', [ResaleController::class, 'getSellableLicenses']);
    Route::get('/listings/mine', [ResaleController::class, 'getSellerListings']);
    Route::post('/listings', [ResaleController::class, 'createListing']);
    Route::get('/listings/{id}', [ResaleController::class, 'getListingDetail'])->whereNumber('id');
    Route::put('/listings/{id}', [ResaleController::class, 'updateListing'])->whereNumber('id');
    Route::post('/listings/{id}/publish', [ResaleController::class, 'publishListing'])->whereNumber('id');
    Route::post('/listings/{id}/review', [ResaleController::class, 'reviewListing'])->whereNumber('id');
    Route::post('/listings/{id}/cancel', [ResaleController::class, 'cancelListing'])->whereNumber('id');
    Route::post('/listings/{listingId}/purchase', [ResaleController::class, 'purchaseListing'])->whereNumber('listingId');
    Route::post('/transactions/{id}/confirm-payment', [ResaleController::class, 'confirmPayment'])->whereNumber('id');
    Route::post('/transactions/{id}/seller-confirm', [ResaleController::class, 'sellerConfirm'])->whereNumber('id');
    Route::post('/transactions/{id}/execute-transfer', [ResaleController::class, 'executeTransfer'])->whereNumber('id');
    Route::post('/transactions/{id}/cancel', [ResaleController::class, 'cancelTransaction'])->whereNumber('id');
});

// ── 促销引擎 ──
Route::prefix('promotion-engine')->group(function () {
    Route::get('/rules', [PromotionEngineController::class, 'index']);
    Route::post('/rules', [PromotionEngineController::class, 'store']);
    Route::get('/stats', [PromotionEngineController::class, 'stats']);
    Route::post('/calculate', [PromotionEngineController::class, 'calculate']);
    Route::post('/apply', [PromotionEngineController::class, 'apply']);
    Route::post('/best-promotion', [PromotionEngineController::class, 'bestPromotion']);
    Route::post('/check-stackability', [PromotionEngineController::class, 'checkStackability']);
    Route::get('/rules/{promotionRule}', [PromotionEngineController::class, 'show']);
    Route::put('/rules/{promotionRule}', [PromotionEngineController::class, 'update']);
    Route::delete('/rules/{promotionRule}', [PromotionEngineController::class, 'destroy']);
    Route::post('/rules/{promotionRule}/toggle-status', [PromotionEngineController::class, 'toggleStatus']);
});

// ── ROI 计算器 ──
Route::prefix('roi-calculator')->group(function () {
    Route::post('/calculate', [RoiCalculatorController::class, 'calculate']);
    Route::get('/defaults', [RoiCalculatorController::class, 'defaults']);
});

// ── 报表构建器 ──
Route::prefix('admin/report-builder')->group(function () {
    Route::get('/data-sources', [ReportBuilderController::class, 'dataSources']);
    Route::get('/dashboard', [ReportBuilderController::class, 'dashboard']);
    Route::get('/reports', [ReportBuilderController::class, 'reports']);
    Route::post('/reports', [ReportBuilderController::class, 'createReport']);
    Route::get('/reports/{id}', [ReportBuilderController::class, 'showReport'])->whereNumber('id');
    Route::put('/reports/{id}', [ReportBuilderController::class, 'updateReport'])->whereNumber('id');
    Route::delete('/reports/{id}', [ReportBuilderController::class, 'deleteReport'])->whereNumber('id');
    Route::post('/reports/{id}/generate', [ReportBuilderController::class, 'generateReport'])->whereNumber('id');
    Route::post('/reports/{id}/snapshot', [ReportBuilderController::class, 'saveSnapshot'])->whereNumber('id');
    Route::get('/reports/{id}/snapshots', [ReportBuilderController::class, 'snapshots'])->whereNumber('id');
    Route::post('/reports/{id}/export', [ReportBuilderController::class, 'exportReport'])->whereNumber('id');
    Route::get('/dashboards', [ReportBuilderController::class, 'dashboards']);
    Route::post('/dashboards', [ReportBuilderController::class, 'createDashboard']);
    Route::put('/dashboards/{id}', [ReportBuilderController::class, 'updateDashboard'])->whereNumber('id');
    Route::delete('/dashboards/{id}', [ReportBuilderController::class, 'deleteDashboard'])->whereNumber('id');
});

// ── 保存的搜索 ──
Route::prefix('saved-searches')->group(function () {
    Route::get('/', [SavedSearchController::class, 'index']);
    Route::post('/', [SavedSearchController::class, 'store']);
    Route::put('/{savedSearch}', [SavedSearchController::class, 'update']);
    Route::delete('/{savedSearch}', [SavedSearchController::class, 'destroy']);
});

// ── 定时通知 ──
Route::prefix('admin/scheduled-notification')->group(function () {
    Route::get('/dashboard', [ScheduledNotificationController::class, 'dashboard']);
    Route::get('/options/list', [ScheduledNotificationController::class, 'options']);
    Route::get('/', [ScheduledNotificationController::class, 'index']);
    Route::post('/', [ScheduledNotificationController::class, 'store']);
    Route::get('/{id}', [ScheduledNotificationController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [ScheduledNotificationController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [ScheduledNotificationController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/send', [ScheduledNotificationController::class, 'send'])->whereNumber('id');
    Route::post('/{id}/cancel', [ScheduledNotificationController::class, 'cancel'])->whereNumber('id');
    Route::get('/{id}/preview', [ScheduledNotificationController::class, 'preview'])->whereNumber('id');
    Route::get('/{id}/delivery-logs', [ScheduledNotificationController::class, 'deliveryLogs'])->whereNumber('id');
    Route::get('/{id}/count-recipients', [ScheduledNotificationController::class, 'countRecipients'])->whereNumber('id');
});

// ── SSL 证书 ──
Route::prefix('ssl-certificates')->group(function () {
    Route::get('/', [SslCertificateController::class, 'index']);
    Route::post('/', [SslCertificateController::class, 'store']);
    Route::get('/stats', [SslCertificateController::class, 'stats']);
    Route::get('/{sslCertificate}', [SslCertificateController::class, 'show']);
    Route::put('/{sslCertificate}', [SslCertificateController::class, 'update']);
    Route::post('/{sslCertificate}/renew', [SslCertificateController::class, 'renew']);
    Route::post('/{sslCertificate}/revoke', [SslCertificateController::class, 'revoke']);
    Route::get('/{sslCertificate}/content', [SslCertificateController::class, 'certificateContent']);
});

// ── 智能合约 ──
Route::prefix('admin/contracts')->group(function () {
    Route::get('/dashboard', [SmartContractController::class, 'dashboard']);
    Route::get('/trends', [SmartContractController::class, 'trends']);
    Route::get('/types', [SmartContractController::class, 'types']);
    Route::get('/evaluation-logs', [SmartContractController::class, 'evaluationLogs']);
    Route::get('/entity-assignments', [SmartContractController::class, 'entityAssignments']);
    Route::post('/entity-evaluate', [SmartContractController::class, 'evaluateEntity']);
    Route::post('/assignments', [SmartContractController::class, 'storeAssignment']);
    Route::put('/assignments/{assignment}', [SmartContractController::class, 'updateAssignment']);
    Route::delete('/assignments/{assignment}', [SmartContractController::class, 'destroyAssignment']);
    Route::post('/seed', [SmartContractController::class, 'seedContracts']);
    Route::get('/', [SmartContractController::class, 'contracts']);
    Route::post('/', [SmartContractController::class, 'storeContract']);
    Route::get('/{contract}', [SmartContractController::class, 'showContract']);
    Route::put('/{contract}', [SmartContractController::class, 'updateContract']);
    Route::delete('/{contract}', [SmartContractController::class, 'destroyContract']);
    Route::post('/{contract}/evaluate', [SmartContractController::class, 'evaluateContract']);
    Route::get('/{contractId}/assignments', [SmartContractController::class, 'assignments'])->whereNumber('contractId');
});

// ── Webhook 模拟器 ──
Route::prefix('webhook-simulator')->group(function () {
    Route::get('/event-types', [WebhookSimulatorController::class, 'eventTypes']);
    Route::get('/event-info/{eventType}', [WebhookSimulatorController::class, 'eventInfo']);
    Route::get('/endpoints', [WebhookSimulatorController::class, 'endpoints']);
    Route::post('/simulate', [WebhookSimulatorController::class, 'simulate']);
    Route::get('/history', [WebhookSimulatorController::class, 'history']);
});

// ── Zapier 集成 ──
Route::prefix('admin/zapier')->group(function () {
    Route::get('/dashboard', [ZapierController::class, 'dashboard']);
    Route::get('/workflow-templates', [ZapierController::class, 'workflowTemplates']);
    Route::get('/embed-config', [ZapierController::class, 'embedConfig']);
});

// ── 增值服务（VAS） ──
Route::prefix('admin/vas')->group(function () {
    Route::get('/categories', [VasAdminController::class, 'categories']);
    Route::get('/billing-modes', [VasAdminController::class, 'billingModes']);
    Route::get('/services', [VasAdminController::class, 'services']);
    Route::post('/services', [VasAdminController::class, 'storeService']);
    Route::get('/services/{id}', [VasAdminController::class, 'showService'])->whereNumber('id');
    Route::put('/services/{vasService}', [VasAdminController::class, 'updateService']);
    Route::delete('/services/{vasService}', [VasAdminController::class, 'destroyService']);
    Route::get('/subscriptions', [VasAdminController::class, 'subscriptions']);
    Route::post('/subscribe', [VasAdminController::class, 'subscribe']);
    Route::post('/subscriptions/{vasSubscription}/cancel', [VasAdminController::class, 'cancelSubscription']);
    Route::get('/stats', [VasAdminController::class, 'stats']);
    Route::get('/marketplace', [VasAdminController::class, 'marketplace']);
});

// ── 团队协作（前端 /api/team 双前缀兼容） ──
$tenantTeamRoutes = function () {
    Route::get('/', [TenantTeamController::class, 'overview']);
    Route::get('/members', [TenantTeamController::class, 'members']);
    Route::post('/invite', [TenantTeamController::class, 'invite']);
    Route::post('/invitations/accept', [TenantTeamController::class, 'acceptInvitation']);
    Route::post('/invitations/decline', [TenantTeamController::class, 'declineInvitation']);
    Route::get('/invitations/pending', [TenantTeamController::class, 'pendingInvitations']);
    Route::post('/invitations/{invitation}/cancel', [TenantTeamController::class, 'cancelInvitation']);
    Route::post('/invitations/{invitation}/resend', [TenantTeamController::class, 'resendInvitation']);
    Route::put('/members/{member}/role', [TenantTeamController::class, 'updateMemberRole'])->whereNumber('member');
    Route::delete('/members/{member}', [TenantTeamController::class, 'removeMember'])->whereNumber('member');
    Route::post('/transfer-admin', [TenantTeamController::class, 'transferAdmin']);
    Route::post('/leave', [TenantTeamController::class, 'leave']);
};
Route::prefix('api/team')->group($tenantTeamRoutes);

// ── SLO / 链路追踪 ──
Route::prefix('admin/slo')->group(function () {
    Route::get('/dashboard', [SloController::class, 'dashboard']);
    Route::get('/meta/sli-types', [SloController::class, 'sliTypes']);
    Route::post('/calculate-all', [SloController::class, 'calculateAll']);
    Route::get('/', [SloController::class, 'index']);
    Route::post('/', [SloController::class, 'store']);
    Route::get('/{id}', [SloController::class, 'show'])->whereNumber('id');
    Route::put('/{sloDefinition}', [SloController::class, 'update']);
    Route::delete('/{sloDefinition}', [SloController::class, 'destroy']);
    Route::post('/{sloDefinition}/calculate', [SloController::class, 'calculate']);
});

Route::prefix('admin/tracing')->group(function () {
    Route::get('/stats', [TracingController::class, 'stats']);
    Route::get('/', [TracingController::class, 'index']);
    Route::get('/{id}', [TracingController::class, 'show'])->whereNumber('id');
});

// ── 状态页（管理端） ──
Route::prefix('status')->group(function () {
    Route::get('/components', [StatusPageController::class, 'components']);
    Route::post('/components', [StatusPageController::class, 'storeComponent']);
    Route::put('/components/{component}', [StatusPageController::class, 'updateComponent']);
    Route::delete('/components/{component}', [StatusPageController::class, 'destroyComponent']);
    Route::get('/incidents', [StatusPageController::class, 'incidents']);
    Route::post('/incidents', [StatusPageController::class, 'storeIncident']);
    Route::get('/incidents/{incident}', [StatusPageController::class, 'showIncident']);
    Route::post('/incidents/{incident}/update', [StatusPageController::class, 'updateIncidentStatus']);
    Route::delete('/incidents/{incident}', [StatusPageController::class, 'destroyIncident']);
    Route::get('/subscribers', [StatusPageController::class, 'subscribers']);
    Route::post('/checks', [StatusPageController::class, 'runChecks']);
    Route::get('/stats', [StatusPageController::class, 'stats']);
});

// ── 创新认证（区块链 / MCP / Serverless / Edge） ──
Route::prefix('admin/innovation')->group(function () {
    Route::put('/status', [InnovationAuthController::class, 'updateStatus']);
    Route::prefix('blockchain')->group(function () {
        Route::get('/dashboard', [InnovationAuthController::class, 'blockchainDashboard']);
        Route::get('/licenses', [InnovationAuthController::class, 'blockchainList']);
        Route::post('/challenge', [InnovationAuthController::class, 'createChallenge']);
        Route::post('/verify-wallet', [InnovationAuthController::class, 'verifyWallet']);
    });
    Route::prefix('mcp')->group(function () {
        Route::get('/dashboard', [InnovationAuthController::class, 'mcpDashboard']);
        Route::get('/servers', [InnovationAuthController::class, 'mcpServers']);
        Route::post('/servers', [InnovationAuthController::class, 'registerMcp']);
        Route::get('/agents', [InnovationAuthController::class, 'aiAgents']);
        Route::post('/agents', [InnovationAuthController::class, 'registerAgent']);
        Route::get('/agents/{agent}/quota', [InnovationAuthController::class, 'checkAgentQuota']);
    });
    Route::prefix('serverless')->group(function () {
        Route::get('/dashboard', [InnovationAuthController::class, 'serverlessDashboard']);
        Route::get('/functions', [InnovationAuthController::class, 'serverlessFunctions']);
        Route::post('/functions', [InnovationAuthController::class, 'registerFunction']);
        Route::post('/functions/{function}/token', [InnovationAuthController::class, 'generateServerlessToken']);
    });
    Route::prefix('edge')->group(function () {
        Route::get('/dashboard', [InnovationAuthController::class, 'edgeDashboard']);
        Route::get('/nodes', [InnovationAuthController::class, 'edgeNodes']);
        Route::post('/nodes', [InnovationAuthController::class, 'registerEdgeNode']);
    });
});

// ── 税务合规 ──
Route::prefix('admin/tax/compliance')->group(function () {
    Route::get('/dashboard', [TaxComplianceController::class, 'dashboard']);
    Route::get('/reports', [TaxComplianceController::class, 'reports']);
    Route::post('/reports/generate', [TaxComplianceController::class, 'generateReport']);
    Route::post('/reports/{reportId}/file', [TaxComplianceController::class, 'fileReport'])->whereNumber('reportId');
    Route::get('/documents', [TaxComplianceController::class, 'documents']);
    Route::post('/documents', [TaxComplianceController::class, 'storeDocument']);
    Route::put('/documents/{documentId}', [TaxComplianceController::class, 'updateDocument'])->whereNumber('documentId');
    Route::delete('/documents/{documentId}', [TaxComplianceController::class, 'destroyDocument'])->whereNumber('documentId');
    Route::get('/rules', [TaxComplianceController::class, 'rules']);
    Route::post('/rules', [TaxComplianceController::class, 'storeRule']);
    Route::put('/rules/{ruleId}', [TaxComplianceController::class, 'updateRule'])->whereNumber('ruleId');
    Route::delete('/rules/{ruleId}', [TaxComplianceController::class, 'destroyRule'])->whereNumber('ruleId');
});

// ── AI 合规报告 ──
Route::prefix('admin/compliance-ai')->group(function () {
    Route::get('/dashboard', [ComplianceReportAiController::class, 'dashboard']);
    Route::post('/generate', [ComplianceReportAiController::class, 'generate']);
    Route::get('/frameworks', [ComplianceReportAiController::class, 'frameworks']);
    Route::get('/reports', [ComplianceReportAiController::class, 'index']);
    Route::get('/reports/{complianceAiReport}', [ComplianceReportAiController::class, 'show']);
});

// ── 工作流面板 ──
Route::prefix('admin/workflows')->group(function () {
    Route::get('/dashboard', [WorkflowDashboardController::class, 'overview']);
    Route::get('/definitions', [WorkflowDashboardController::class, 'definitions']);
    Route::get('/instances', [WorkflowDashboardController::class, 'instances']);
    Route::get('/stats', [WorkflowDashboardController::class, 'stats']);
    Route::get('/config', [WorkflowDashboardController::class, 'stepDefinitions']);
    Route::get('/{id}', [WorkflowDashboardController::class, 'show'])->whereNumber('id');
    Route::get('/{id}/progress', [WorkflowDashboardController::class, 'show'])->whereNumber('id');
    Route::get('/{id}/saga', [WorkflowDashboardController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/cancel', [WorkflowDashboardController::class, 'cancel'])->whereNumber('id');
});

// ── 账单历史（门户，管理端也可访问） ──
Route::prefix('billing')->group(function () {
    Route::get('/invoices', [BillingHistoryPortalController::class, 'invoices']);
    Route::get('/invoices/{id}', [BillingHistoryPortalController::class, 'show'])->whereNumber('id');
    Route::get('/stats', [BillingHistoryPortalController::class, 'stats']);
    Route::get('/subscriptions', [BillingHistoryPortalController::class, 'subscriptions']);
    Route::get('/failed-payments', [BillingHistoryPortalController::class, 'failedPayments']);
    Route::get('/auto-renewals', [BillingHistoryPortalController::class, 'autoRenewals']);
    Route::get('/filter-options', [BillingHistoryPortalController::class, 'filterOptions']);
});

// ── 数据导出（管理端） ──
Route::prefix('admin/data-exports')->group(function () {
    Route::get('/', [CustomerDataExportController::class, 'adminIndex']);
    Route::get('/stats', [CustomerDataExportController::class, 'adminStats']);
    Route::post('/', [CustomerDataExportController::class, 'adminCreateExport']);
});

// ── 迁移助手 ──
Route::prefix('admin/migration-assistant')->group(function () {
    Route::get('/dashboard', [MigrationAssistantController::class, 'dashboard']);
    Route::get('/sources', [MigrationAssistantController::class, 'sources']);
    Route::get('/jobs', [MigrationAssistantController::class, 'index']);
    Route::post('/jobs', [MigrationAssistantController::class, 'create']);
    Route::get('/jobs/{migrationAssistantJob}', [MigrationAssistantController::class, 'show']);
    Route::post('/jobs/{migrationAssistantJob}/run', [MigrationAssistantController::class, 'run']);
});

// ── 续费提醒 ──
Route::prefix('admin/renewal-reminder')->group(function () {
    Route::get('/templates', [RenewalReminderController::class, 'templates']);
    Route::post('/templates', [RenewalReminderController::class, 'storeTemplate']);
    Route::put('/templates/{renewalReminderTemplate}', [RenewalReminderController::class, 'updateTemplate']);
    Route::delete('/templates/{renewalReminderTemplate}', [RenewalReminderController::class, 'deleteTemplate']);
    Route::post('/process-due', [RenewalReminderController::class, 'processDue']);
    Route::get('/logs', [RenewalReminderController::class, 'reminderLogs']);
    Route::get('/conversion-analytics', [RenewalReminderController::class, 'conversionAnalytics']);
    Route::get('/optimization-suggestions', [RenewalReminderController::class, 'optimizationSuggestions']);
});

// ── 报表调度 ──
Route::prefix('admin/report-scheduler')->group(function () {
    Route::get('/dashboard', [ReportSchedulerController::class, 'dashboard']);
    Route::get('/schedulable-reports', [ReportSchedulerController::class, 'schedulableReports']);
    Route::get('/schedules', [ReportSchedulerController::class, 'schedules']);
    Route::post('/schedules', [ReportSchedulerController::class, 'createSchedule']);
    Route::put('/schedules/{id}', [ReportSchedulerController::class, 'updateSchedule'])->whereNumber('id');
    Route::delete('/schedules/{id}', [ReportSchedulerController::class, 'deleteSchedule'])->whereNumber('id');
    Route::post('/schedules/{id}/toggle', [ReportSchedulerController::class, 'toggleSchedule'])->whereNumber('id');
    Route::post('/schedules/{id}/trigger', [ReportSchedulerController::class, 'triggerSchedule'])->whereNumber('id');
    Route::get('/delivery-logs', [ReportSchedulerController::class, 'deliveryLogs']);
});

// ── 定时促销 ──
Route::prefix('scheduled-promotions')->group(function () {
    Route::get('/stats', [ScheduledPromotionController::class, 'stats']);
    Route::get('/calendar', [ScheduledPromotionController::class, 'calendar']);
    Route::get('/visible', [ScheduledPromotionController::class, 'visiblePromotions']);
    Route::get('/', [ScheduledPromotionController::class, 'index']);
    Route::post('/', [ScheduledPromotionController::class, 'store']);
    Route::get('/{id}', [ScheduledPromotionController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [ScheduledPromotionController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [ScheduledPromotionController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/publish', [ScheduledPromotionController::class, 'publish'])->whereNumber('id');
    Route::post('/{id}/pause', [ScheduledPromotionController::class, 'pause'])->whereNumber('id');
    Route::post('/{promotionId}/check-eligibility', [ScheduledPromotionController::class, 'checkEligibility'])->whereNumber('promotionId');
});

// ── 对账 ──
Route::prefix('admin/reconciliation')->group(function () {
    Route::get('/dashboard', [ReconciliationController::class, 'dashboard']);
    Route::get('/reconciliations', [ReconciliationController::class, 'reconciliations']);
    Route::post('/reconciliations/{invoiceReconciliation}/resolve', [ReconciliationController::class, 'resolve']);
    Route::get('/imports', [ReconciliationController::class, 'imports']);
    Route::post('/imports/csv', [ReconciliationController::class, 'importCsv']);
    Route::get('/channel-rows', [ReconciliationController::class, 'channelRows']);
    Route::post('/manual-match', [ReconciliationController::class, 'manualMatch']);
    Route::get('/calendars', [ReconciliationController::class, 'calendars']);
    Route::post('/calendars/generate', [ReconciliationController::class, 'generateCalendars']);
    Route::get('/report', [ReconciliationController::class, 'report']);
});

// ── License 合并 ──
Route::prefix('license-merge')->group(function () {
    Route::post('/preview', [LicenseMergeController::class, 'preview']);
    Route::post('/execute', [LicenseMergeController::class, 'merge']);
    Route::get('/history', [LicenseMergeController::class, 'history']);
    Route::get('/search-customers', [LicenseMergeController::class, 'searchCustomers']);
    Route::get('/history/{job}', [LicenseMergeController::class, 'detail']);
    Route::post('/history/{job}/rollback', [LicenseMergeController::class, 'rollback']);
});

// ── License 备注 ──
Route::prefix('licenses/{license}/notes')->whereNumber('license')->group(function () {
    Route::get('/', [LicenseNoteController::class, 'index']);
    Route::post('/', [LicenseNoteController::class, 'store']);
    Route::delete('/{note}', [LicenseNoteController::class, 'destroy']);
});

// ── License 限制 ──
Route::prefix('admin/licenses/{licenseId}/restrictions')->whereNumber('licenseId')->group(function () {
    Route::get('/ip', [LicenseRestrictionController::class, 'getIpRestriction']);
    Route::post('/ip', [LicenseRestrictionController::class, 'saveIpRestriction']);
    Route::delete('/ip', [LicenseRestrictionController::class, 'deleteIpRestriction']);
    Route::get('/geo', [LicenseRestrictionController::class, 'getGeoFence']);
    Route::post('/geo', [LicenseRestrictionController::class, 'saveGeoFence']);
    Route::delete('/geo', [LicenseRestrictionController::class, 'deleteGeoFence']);
});
Route::prefix('admin/license-restrictions')->group(function () {
    Route::post('/test-ip', [LicenseRestrictionController::class, 'testIp']);
    Route::post('/test-geo', [LicenseRestrictionController::class, 'testGeo']);
    Route::get('/countries', [LicenseRestrictionController::class, 'countries']);
    Route::get('/logs', [LicenseRestrictionController::class, 'logs']);
});

// ── GraphQL ──
Route::prefix('admin/graphql')->group(function () {
    Route::post('/', [GraphQLController::class, 'query']);
    Route::get('/schema', [GraphQLController::class, 'schema']);
    Route::get('/explorer/data', [GraphQLController::class, 'explorer']);
});

// ── gRPC ──
Route::prefix('admin/grpc')->group(function () {
    Route::get('/dashboard', [GrpcController::class, 'dashboard']);
    Route::get('/health', [GrpcController::class, 'health']);
    Route::get('/config', [GrpcController::class, 'config']);
    Route::get('/endpoints', [GrpcController::class, 'endpoints']);
    Route::get('/circuit-breaker', [GrpcController::class, 'circuitBreaker']);
    Route::post('/reset-circuit-breaker', [GrpcController::class, 'resetCircuitBreaker']);
});
