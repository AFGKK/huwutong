<?php

use App\Http\Controllers\Api\AirGappedController;
use App\Http\Controllers\Api\AlertingController;
use App\Http\Controllers\Api\AttackDetectionController;
use App\Http\Controllers\Api\AutomationRuleController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BlueGreenController;
use App\Http\Controllers\Api\CustomerSmtpController;
use App\Http\Controllers\Api\QueueMonitorController;
use App\Http\Controllers\Api\RevenueDashboardController;
use App\Http\Controllers\Api\SecurityCenterController;
use App\Http\Controllers\Api\SecuritySopController;
use App\Http\Controllers\Api\WafController;
use App\Http\Controllers\Api\WebhookMonitorController;

// ── 队列死信监控 (M2-82) ──
Route::prefix('admin/queue-monitor')->group(function () {
    Route::get('/dashboard', [QueueMonitorController::class, 'dashboard']);
    Route::get('/failed-jobs', [QueueMonitorController::class, 'failedJobs']);
    Route::get('/dead-letters', [QueueMonitorController::class, 'deadLetters']);
    Route::post('/dead-letters/batch-retry', [QueueMonitorController::class, 'batchRetry']);
    Route::post('/dead-letters/{id}/retry', [QueueMonitorController::class, 'retryDeadLetter'])->whereNumber('id');
    Route::post('/dead-letters/{id}/ignore', [QueueMonitorController::class, 'ignoreDeadLetter'])->whereNumber('id');
    Route::get('/trend', [QueueMonitorController::class, 'trend']);
    Route::post('/cleanup', [QueueMonitorController::class, 'cleanup']);
});

// ── Webhook 投递监控 ──
Route::prefix('admin/webhook-monitor')->group(function () {
    Route::get('/overview', [WebhookMonitorController::class, 'overview']);
    Route::get('/endpoints/{endpointId}', [WebhookMonitorController::class, 'endpointDetail'])->whereNumber('endpointId');
    Route::get('/failures', [WebhookMonitorController::class, 'failures']);
    Route::get('/latency-distribution', [WebhookMonitorController::class, 'latencyDistribution']);
    Route::post('/aggregate-daily', [WebhookMonitorController::class, 'aggregateDaily']);
});

// ── 安全中心 + SOP ──
Route::prefix('admin/security')->group(function () {
    Route::get('/dashboard', [SecurityCenterController::class, 'dashboard']);
    Route::get('/security-score', [SecurityCenterController::class, 'securityScore']);

    Route::get('/ip-whitelists', [SecurityCenterController::class, 'ipWhitelists']);
    Route::post('/ip-whitelists', [SecurityCenterController::class, 'storeIpWhitelist']);
    Route::post('/ip-whitelists/bulk-import', [SecurityCenterController::class, 'bulkImportIps']);
    Route::put('/ip-whitelists/{ipWhitelist}', [SecurityCenterController::class, 'updateIpWhitelist'])->whereNumber('ipWhitelist');
    Route::delete('/ip-whitelists/{ipWhitelist}', [SecurityCenterController::class, 'destroyIpWhitelist'])->whereNumber('ipWhitelist');

    Route::get('/policies', [SecurityCenterController::class, 'policies']);
    Route::put('/policies/{loginPolicy}', [SecurityCenterController::class, 'updatePolicy'])->whereNumber('loginPolicy');

    Route::get('/sessions', [SecurityCenterController::class, 'sessions']);
    Route::post('/sessions/terminate-mine', [SecurityCenterController::class, 'terminateMySessions']);
    Route::post('/sessions/terminate-all', [SecurityCenterController::class, 'terminateAllSessions']);
    Route::post('/sessions/{userSession}/terminate', [SecurityCenterController::class, 'terminateSession'])->whereNumber('userSession');

    Route::get('/sop-templates', [SecuritySopController::class, 'sopTemplates']);
    Route::post('/sop-templates', [SecuritySopController::class, 'storeSopTemplate']);
    Route::get('/sop-templates/{securitySopTemplate}', [SecuritySopController::class, 'showSopTemplate'])->whereNumber('securitySopTemplate');
    Route::put('/sop-templates/{securitySopTemplate}', [SecuritySopController::class, 'updateSopTemplate'])->whereNumber('securitySopTemplate');
    Route::delete('/sop-templates/{securitySopTemplate}', [SecuritySopController::class, 'deleteSopTemplate'])->whereNumber('securitySopTemplate');
    Route::post('/sop-templates/{securitySopTemplate}/execute', [SecuritySopController::class, 'executeSop'])->whereNumber('securitySopTemplate');

    Route::get('/sop-executions', [SecuritySopController::class, 'sopExecutions']);
    Route::get('/sop-stats', [SecuritySopController::class, 'sopStats']);

    Route::get('/events', [SecurityCenterController::class, 'events']);
    Route::get('/event-types', [SecurityCenterController::class, 'eventTypes']);
    Route::post('/events/{securityEvent}/handle-sop', [SecuritySopController::class, 'handleEvent'])->whereNumber('securityEvent');
    Route::post('/events/{securityEvent}/resolve', [SecuritySopController::class, 'resolveEvent'])->whereNumber('securityEvent');
});

// ── WAF 防护 ──
Route::prefix('admin/waf')->group(function () {
    Route::get('/dashboard', [WafController::class, 'dashboard']);
    Route::post('/rules/seed', [WafController::class, 'seedRules']);
    Route::get('/rules', [WafController::class, 'rules']);
    Route::post('/rules', [WafController::class, 'storeRule']);
    Route::put('/rules/{wafRule}', [WafController::class, 'updateRule'])->whereNumber('wafRule');
    Route::delete('/rules/{wafRule}', [WafController::class, 'destroyRule'])->whereNumber('wafRule');
    Route::post('/rules/{wafRule}/toggle', [WafController::class, 'toggleRule'])->whereNumber('wafRule');
    Route::get('/ip-list', [WafController::class, 'ipList']);
    Route::post('/ip-list', [WafController::class, 'addIp']);
    Route::post('/ip-list/batch', [WafController::class, 'batchAddIp']);
    Route::get('/ip-list/check', [WafController::class, 'checkIp']);
    Route::delete('/ip-list/{wafIpList}', [WafController::class, 'deleteIp'])->whereNumber('wafIpList');
    Route::get('/logs', [WafController::class, 'logs']);
    Route::get('/trend', [WafController::class, 'trend']);
    Route::post('/logs/prune', [WafController::class, 'pruneLogs']);
    Route::get('/config', [WafController::class, 'getConfig']);
    Route::put('/config', [WafController::class, 'updateConfig']);
});

// ── 气隙部署 (Air-Gapped) ──
Route::prefix('admin/air-gapped')->group(function () {
    Route::get('/status', [AirGappedController::class, 'status']);
    Route::get('/metrics', [AirGappedController::class, 'metrics']);
    Route::get('/health', [AirGappedController::class, 'healthCheck']);
    Route::get('/docker', [AirGappedController::class, 'dockerInfo']);
    Route::get('/licenses', [AirGappedController::class, 'listLicenses']);
    Route::post('/licenses/scan-usb', [AirGappedController::class, 'scanUsb']);
    Route::post('/licenses/import', [AirGappedController::class, 'importLicense']);
    Route::post('/licenses/upload', [AirGappedController::class, 'uploadLicense']);
    Route::get('/updates', [AirGappedController::class, 'listUpdates']);
    Route::post('/updates/apply', [AirGappedController::class, 'applyUpdate']);
    Route::post('/updates/upload', [AirGappedController::class, 'uploadUpdate']);
});

// ── 收益仪表盘（扩展 admin/revenue，MRR 瀑布图在 developer.php） ──
Route::middleware(['ability:admin,super-admin'])->prefix('admin/revenue')->group(function () {
    Route::get('/dashboard', [RevenueDashboardController::class, 'dashboard']);
    Route::get('/overview', [RevenueDashboardController::class, 'overview']);
    Route::get('/channel-roi', [RevenueDashboardController::class, 'channelRoi']);
    Route::get('/channel-trend', [RevenueDashboardController::class, 'channelTrend']);
    Route::get('/channel-quality', [RevenueDashboardController::class, 'channelQuality']);
    Route::get('/revenue-trend', [RevenueDashboardController::class, 'revenueTrend']);
    Route::get('/payment-methods', [RevenueDashboardController::class, 'paymentMethods']);
    Route::get('/agent-levels', [RevenueDashboardController::class, 'agentLevels']);
    Route::get('/agent-leaderboard', [RevenueDashboardController::class, 'agentLeaderboard']);
    Route::get('/monthly-report', [RevenueDashboardController::class, 'monthlyReport']);
});

// ── 客户 SMTP ──
Route::prefix('admin/customer-smtp')->group(function () {
    Route::get('/dashboard', [CustomerSmtpController::class, 'dashboard']);
    Route::get('/providers/list', [CustomerSmtpController::class, 'providers']);
    Route::get('/logs/list', [CustomerSmtpController::class, 'logs']);
    Route::post('/recover', [CustomerSmtpController::class, 'recover']);
    Route::get('/', [CustomerSmtpController::class, 'index']);
    Route::post('/', [CustomerSmtpController::class, 'store']);
    Route::put('/{id}', [CustomerSmtpController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [CustomerSmtpController::class, 'destroy'])->whereNumber('id');
    Route::post('/{id}/test', [CustomerSmtpController::class, 'test'])->whereNumber('id');
    Route::post('/{id}/set-primary', [CustomerSmtpController::class, 'setPrimary'])->whereNumber('id');
    Route::post('/{id}/send-test', [CustomerSmtpController::class, 'sendTest'])->whereNumber('id');
});

// ── 自动化规则 ──
Route::prefix('admin/automation')->group(function () {
    Route::get('/dashboard', [AutomationRuleController::class, 'dashboard']);
    Route::get('/triggers', [AutomationRuleController::class, 'triggers']);
    Route::get('/available-actions', [AutomationRuleController::class, 'actions']);
    Route::get('/rules', [AutomationRuleController::class, 'index']);
    Route::post('/rules', [AutomationRuleController::class, 'store']);
    Route::get('/rules/{id}', [AutomationRuleController::class, 'show'])->whereNumber('id');
    Route::put('/rules/{rule}', [AutomationRuleController::class, 'update'])->whereNumber('rule');
    Route::delete('/rules/{rule}', [AutomationRuleController::class, 'destroy'])->whereNumber('rule');
    Route::post('/rules/{rule}/toggle', [AutomationRuleController::class, 'toggle'])->whereNumber('rule');
    Route::post('/rules/{rule}/execute', [AutomationRuleController::class, 'execute'])->whereNumber('rule');
    Route::get('/rules/{ruleId}/executions', [AutomationRuleController::class, 'executions'])->whereNumber('ruleId');
    Route::get('/executions', [AutomationRuleController::class, 'allExecutions']);
    Route::get('/webhooks', [AutomationRuleController::class, 'webhooks']);
    Route::post('/webhooks', [AutomationRuleController::class, 'storeWebhook']);
    Route::put('/webhooks/{webhook}', [AutomationRuleController::class, 'updateWebhook'])->whereNumber('webhook');
    Route::delete('/webhooks/{webhook}', [AutomationRuleController::class, 'destroyWebhook'])->whereNumber('webhook');
    Route::post('/webhooks/{webhook}/test', [AutomationRuleController::class, 'testWebhook'])->whereNumber('webhook');
});

// ── 攻击检测 ──
Route::prefix('admin/attack-detection')->group(function () {
    Route::get('/dashboard', [AttackDetectionController::class, 'dashboard']);
    Route::get('/events', [AttackDetectionController::class, 'events']);
    Route::get('/events/{attackEvent}', [AttackDetectionController::class, 'show'])->whereNumber('attackEvent');
    Route::put('/events/{attackEvent}/status', [AttackDetectionController::class, 'updateStatus'])->whereNumber('attackEvent');
    Route::get('/ip-blocks', [AttackDetectionController::class, 'ipBlocks']);
    Route::post('/ip-blocks', [AttackDetectionController::class, 'blockIp']);
    Route::delete('/ip-blocks/{ip}', [AttackDetectionController::class, 'unblockIp'])->where('ip', '.*');
    Route::post('/analyze', [AttackDetectionController::class, 'analyze']);
});

// ── 蓝绿部署 ──
Route::prefix('admin/blue-green')->group(function () {
    Route::get('/dashboard', [BlueGreenController::class, 'dashboard']);
    Route::get('/history', [BlueGreenController::class, 'history']);
    Route::post('/start', [BlueGreenController::class, 'start']);
    Route::post('/deployments/{id}/health-check', [BlueGreenController::class, 'healthCheck'])->whereNumber('id');
    Route::post('/deployments/{id}/verify', [BlueGreenController::class, 'verify'])->whereNumber('id');
    Route::post('/deployments/{id}/switch', [BlueGreenController::class, 'switch'])->whereNumber('id');
    Route::post('/deployments/{id}/rollback', [BlueGreenController::class, 'rollback'])->whereNumber('id');
    Route::get('/deployments/{id}', [BlueGreenController::class, 'show'])->whereNumber('id');
});

// ── 备份管理 ──
Route::prefix('backups')->group(function () {
    Route::get('/stats', [BackupController::class, 'stats']);
    Route::get('/config', [BackupController::class, 'config']);
    Route::get('/', [BackupController::class, 'index']);
    Route::post('/database', [BackupController::class, 'backupDatabase']);
    Route::post('/files', [BackupController::class, 'backupFiles']);
    Route::get('/{backupRecord}/download', [BackupController::class, 'download'])->whereNumber('backupRecord');
    Route::delete('/{backupRecord}', [BackupController::class, 'destroy'])->whereNumber('backupRecord');
    Route::post('/{backupRecord}/restore', [BackupController::class, 'restore'])->whereNumber('backupRecord');
});

// ── 智能告警（前端 /admin/alerting 路径别名） ──
Route::middleware(['ability:admin,super-admin'])->prefix('admin/alerting')->group(function () {
    Route::get('/dashboard', [AlertingController::class, 'dashboard']);
    Route::get('/rules', [AlertingController::class, 'rules']);
    Route::get('/rules/{id}', [AlertingController::class, 'ruleShow'])->whereNumber('id');
    Route::post('/rules', [AlertingController::class, 'ruleStore']);
    Route::put('/rules/{alertRule}', [AlertingController::class, 'ruleUpdate'])->whereNumber('alertRule');
    Route::delete('/rules/{alertRule}', [AlertingController::class, 'ruleDestroy'])->whereNumber('alertRule');
    Route::get('/channels', [AlertingController::class, 'channels']);
    Route::post('/channels', [AlertingController::class, 'channelStore']);
    Route::put('/channels/{alertChannel}', [AlertingController::class, 'channelUpdate'])->whereNumber('alertChannel');
    Route::delete('/channels/{alertChannel}', [AlertingController::class, 'channelDestroy'])->whereNumber('alertChannel');
    Route::post('/channels/{alertChannel}/test', [AlertingController::class, 'testChannel'])->whereNumber('alertChannel');
    Route::get('/escalations', [AlertingController::class, 'escalations']);
    Route::post('/escalations', [AlertingController::class, 'escalationStore']);
    Route::put('/escalations/{alertEscalation}', [AlertingController::class, 'escalationUpdate'])->whereNumber('alertEscalation');
    Route::delete('/escalations/{id}', [AlertingController::class, 'escalationDestroy'])->whereNumber('id');
    Route::get('/events', [AlertingController::class, 'events']);
    Route::get('/events/{id}', [AlertingController::class, 'eventShow'])->whereNumber('id');
    Route::post('/events/{alertEvent}/acknowledge', [AlertingController::class, 'acknowledgeEvent'])->whereNumber('alertEvent');
    Route::post('/events/{alertEvent}/resolve', [AlertingController::class, 'resolveEvent'])->whereNumber('alertEvent');
    Route::get('/event-stats', [AlertingController::class, 'eventStats']);
    Route::get('/metric-types', [AlertingController::class, 'metricTypes']);
    Route::get('/severities', [AlertingController::class, 'severities']);
});
