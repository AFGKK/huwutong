<?php

use Illuminate\Support\Facades\Schedule;

// ─── 所有定时任务统一注册在此文件 ───
// Laravel 11 使用 routes/console.php 作为调度的标准方式
// 服务器 cron 需添加: * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

// ── License 自动过期 ──
// 每小时检查一次过期 License（及时清理）
Schedule::command('hwt:auto-expire-licenses')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-license.log'));

// ── 发送过期提醒 ──
// 每天早 9 点发送 7 天、3 天、1 天前的提醒
Schedule::command('hwt:send-expiry-reminders', ['--level' => '7_days'])
    ->dailyAt('09:00')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-license.log'));

Schedule::command('hwt:send-expiry-reminders', ['--level' => '3_days'])
    ->dailyAt('09:05')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-license.log'));

Schedule::command('hwt:send-expiry-reminders', ['--level' => '1_day'])
    ->dailyAt('09:10')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-license.log'));

// ── 试用期检查 ──
// 每小时检查过期试用
Schedule::command('hwt:check-trials')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-trial.log'));

// ── 订阅计费 ──
// 每天凌晨 2 点处理到期续费
Schedule::command('billing:process-renewals')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-billing.log'));

// 每天凌晨 3 点重试失败续费
Schedule::command('billing:process-retries')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-billing.log'));

// 每天凌晨 4 点处理宽限期到期的订阅
Schedule::command('billing:process-grace-period')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-billing.log'));

// ─── M2-146 超时未支付订单自动取消 ───
// 每5分钟检查一次
Schedule::command('orders:cancel-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-order.log'));

// ── SSL 证书检查 ──
// 每天凌晨 5 点检查并自动续期
Schedule::command('ssl:check', ['--renew' => true])
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-ssl.log'));

// ── 依赖安全扫描 ──
// 每周日凌晨 6 点扫描
Schedule::command('deps:scan')
    ->weeklyOn(0, '06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-deps.log'));

// ── 客户健康度评分 ──
Schedule::command('hwt:calculate-health-scores')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-health-score.log'));

// ── API 版本生命周期管理 ──
// 每天凌晨检查过期的废弃版本，自动进入 sunset/retire 流程
Schedule::command('hwt:process-expired-api-versions')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-api-versions.log'));

// ── SDK 版本分布日快照 ──
Schedule::command('hwt:snapshot-sdk-versions')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-telemetry.log'));

// ── Redis 缓存预热 (M2-23) ──
// 每天凌晨 3:00 低峰期预加载热点数据
Schedule::command('cache:warmup')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-cache.log'));

// ── 从库健康检查 (M2-23) ──
// 每 5 分钟检查从库延迟和运行状态
Schedule::command('db:read-write-status')
    ->everyFiveMinutes()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-db.log'));

// ── 数据库自动备份 ──
// 每天凌晨 2:30 执行（续费之后）
Schedule::command('db:backup')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-backup.log'));

// ── 文件自动备份 ──
// 每周日凌晨 3:30
Schedule::command('files:backup')
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-backup.log'));

// ── 清理过期备份 ──
Schedule::command('backup:cleanup')
    ->daily()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-backup.log'));

// ── 季度恢复演练 ──
// 每季度第 2 周的周一 10:00 自动执行
Schedule::command('recovery:drill --quick')
    ->cron('0 10 * 3,6,9,12 1')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-backup.log'));

// ── 多数据中心健康检查 ──
// 每 5 分钟检查一次所有数据中心健康状况
Schedule::command('multi-region:health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-dr.log'));

// ── APM 性能监控数据清理 ──
// 每天凌晨 1:00 清理超过保留期的请求记录（备份之前执行）
Schedule::command('apm:prune')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-apm.log'));

// ── 审计日志清理 ──
// 每天凌晨 3:30 清理超过保留期的历史审计日志
Schedule::command('audit:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-audit.log'));

// ── 报表调度投递 ──
// 每 5 分钟检查一次到期的报表调度任务
Schedule::command('reports:process-schedules')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-reports.log'));

// ── 系统健康快照 ──
// 每 5 分钟记录一次系统健康状态
Schedule::command('system-health:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-health.log'));

// ── 代理商月度快照 (M3-04) ──
// 每月1日凌晨3点生成上月快照
Schedule::command('agent:snapshot')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground();

// ── 交互式产品演示会话清理 (M3-70 🎮) ──
// 每小时清理过期演示会话
Schedule::command('demo:cleanup')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-demo.log'));

// ── 收入确认 (M3-55) ──
// 每天凌晨 2:30 自动执行当期收入确认
Schedule::command('revenue:recognize')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-revenue.log'));

// 每月1日凌晨 2:00 自动创建未排程发票的收入确认排程
Schedule::command('revenue:create-schedules')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-revenue.log'));

// 每月1日凌晨 4:00 自动生成月度收入快照
Schedule::command('revenue:report --snapshots')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-revenue.log'));

// ── CRL 网络恢复自动补全验证 (M1.3-03) ──
// 每 30 分钟检查一次离线激活记录是否被吊销
Schedule::command('hwt:crl-auto-verify')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-crl.log'));

// ── Seat Pool 席位池定时清理 (M2-91) ──
// 每 5 分钟清理过期席位和排队超时
Schedule::command('hwt:cleanup-seat-pool')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-seatpool.log'));

// ── T+30 佣金到期解冻 (M3-72) ──
// 每天凌晨 4:30 解冻到期佣金 (pending_balance → available_balance)
Schedule::command('withdrawal:release-pending')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-withdrawal.log'));

// ── 退款自动处理 (M3-11) ──
// 每 15 分钟处理一次待审核退款（风控评估 + 自动决策）
Schedule::command('refund:auto-process --limit=20')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-refund.log'));

// ── 本地代理数据清理 (M3-12) ──
// 每天凌晨 4:00 清理过期心跳日志、激活日志、缓存和超时待激活节点
Schedule::command('local-proxy:cleanup')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-local-proxy.log'));

// ── 预付余额自动充值 (M3-56) ──
// 每60分钟检查一次余额低于阈值的账户并自动充值
Schedule::command('prepaid:auto-recharge')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-prepaid.log'));

// ── 预付负余额宽限期检查 (M3-56) ──
// 每天凌晨 5:00 检查超期负余额并冻结
Schedule::command('prepaid:enforce-negative-balance')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-prepaid.log'));

// ── 信用额度自动评估 (M3-56) ──
// 每月1日凌晨 5:30 自动评估并调整信用额度
Schedule::command('prepaid:credit-assessment')
    ->monthlyOn(1, '05:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-prepaid.log'));

// ── 邮件营销 Drip 序列发送 (M2-102) ──
// 每 5 分钟检查并发送待处理的 Drip 邮件
Schedule::command('email-drip:send', ['--batch=50'])
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-email-drip.log'));

// ── 自动化营销活动处理 (D-24) ──
// 每 10 分钟检查待发送的营销活动
Schedule::command('marketing:process-campaigns', ['--batch=100'])
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-marketing.log'));

// ── MRR 月度变化记录 (M3-59) ──
// 每月1日凌晨 6:00 扫描订阅变动并写入 MrrChangeDetail
Schedule::command('mrr:record-changes')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-mrr.log'));

// ── 气隙部署健康检查 (M3-61) ──
// 每小时检查一次气隙环境状态（仅在气隙模式下有效）
Schedule::command('air-gapped:health --notify')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-air-gapped.log'));

// ── 所有权转移超时自动取消 (M3-65 🏷️) ──
// 每小时检查超过48小时未确认的转移请求并自动取消
Schedule::command('ownership-transfer:auto-cancel')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-ownership-transfer.log'));

// ── 用量计费月度批量结算 (M3-76 🔢) ──
// 每月1日凌晨4:00批量生成所有启用量计费的订阅账单
Schedule::command('metered:billing:batch')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-metered-billing.log'));

// ── 定时促销自动激活/过期 (M2-151 🛒) ──
// 每5分钟检查一次待激活/到期的促销活动
Schedule::command('promotions:process-scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-promotions.log'));

// ���� M2-137 Temporal ������ Worker ����
// ÿ����ִ�д������Ĺ�����ʵ��
Schedule::command('workflow:worker start --concurrent=10')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-workflow.log'));

// ÿ���賿���� 30 ��ǰ������ɹ�����
Schedule::command('workflow:cleanup --days=30')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-workflow.log'));

// ÿ���賿2��ִ�������������� (M1.1-14)
Schedule::command('data-retention:apply')->dailyAt('02:00')->withoutOverlapping()->runInBackground()->appendOutputTo(storage_path('logs/scheduler-data-retention.log'));

// ── 密钥泄露定时扫描 (M1.3-29) ──
// 每6小时快速扫描一次
Schedule::command('hwt:secret-scan --quick --dry-run')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-secret-scan.log'));

// 每周日凌晨2点全量扫描
Schedule::command('hwt:secret-scan --dry-run')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-secret-scan.log'));

// ── AI-045 主动洞察推送（每15分钟扫描未回复对话）──
Schedule::command('ai:scan-unreplied --limit=20')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-proactive-insight.log'));

// ── 消息定时销毁（每5分钟清理过期消息）──
Schedule::command('messages:prune-expired --batch=200')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-message-expiry.log'));
// ── OA 定时发布（每5分钟检查到期的定时文章）──
Schedule::command('oa:publish-scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-oa.log'));
// ── AI 虚拟作者定时创作 ──
Schedule::command('ai:auto-write --limit=2')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-ai.log'));
Schedule::command('ai:auto-write --limit=1')
    ->dailyAt('14:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-ai.log'));
Schedule::command('ai:auto-write --limit=1')
    ->dailyAt('20:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-ai.log'));
// ── AI 管理员内容巡检（每30分钟检查一次）──
Schedule::command('ai:monitor-content --limit=30')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-ai.log'));
// ── AI 知识库自增长（每天凌晨 3 点执行）──
Schedule::command('kb:auto-grow --limit=50')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-kb.log'));
// ── AI 自动化运营编排（每30分钟执行）──
Schedule::command('ai:auto-operate --limit=30')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-operate.log'));
// ── AI 自学习引擎（每天凌晨 5 点执行）──
Schedule::command('ai:self-improve --hours=24')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-selflearn.log'));

// ── AI 搜索索引重建（每天凌晨 4 点执行）──
Schedule::command('search:rebuild-index')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-search.log'));

// ── NPS 满意度调查 (M2-59) ──
// 每天上午 10 点自动发送调查
Schedule::command('nps:send-surveys --limit=50')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-nps.log'));

// 每天午夜生成 NPS 汇总快照
Schedule::command('nps:generate-snapshot')
    ->dailyAt('23:55')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-nps.log'));

// 每天凌晨清理过期调查
Schedule::command('nps:expire-surveys')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-nps.log'));

// ── Meilisearch 全量补齐（增量由 Observer 自动完成）──
if (config('meilisearch.sync.scheduled', true)) {
    Schedule::command('meilisearch:sync')
        ->dailyAt('02:30')
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/scheduler-meilisearch.log'));
}
