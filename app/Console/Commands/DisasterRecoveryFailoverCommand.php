<?php

namespace App\Console\Commands;

use App\Models\FailoverRule;
use App\Services\MultiRegionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M2-37 🗄️ 灾备切换命令
 *
 * 手动触发数据中心故障切换或恢复（回切）。
 * 在紧急情况下快速切换流量到备用数据中心。
 *
 * 用法:
 *   php artisan dr:failover                     — 交互式选择并切换
 *   php artisan dr:failover --rule=1            — 指定规则切换
 *   php artisan dr:failover --rule=1 --restore  — 回切到主数据中心
 *   php artisan dr:failover --dry-run           — 仅检查不执行
 *   php artisan dr:failover --list              — 查看所有规则
 */
class DisasterRecoveryFailoverCommand extends Command
{
    protected $signature = 'dr:failover
        {--rule= : 故障切换规则 ID}
        {--restore : 执行恢复回切（从备用切回主数据中心）}
        {--reason= : 切换原因}
        {--dry-run : 仅验证不实际执行切换}
        {--force : 跳过确认提示}
        {--list : 列出所有故障切换规则}';

    protected $description = 'M2-37 手动执行数据中心故障切换或恢复回切';

    public function handle(MultiRegionService $multiRegion): int
    {
        $tenantId = auth()->user()->tenant_id ?? 1;

        if ($this->option('list')) {
            return $this->listRules($multiRegion, $tenantId);
        }

        $ruleId = $this->option('rule');
        $isRestore = $this->option('restore');
        $reason = $this->option('reason') ?? ($isRestore ? '计划性恢复回切' : '紧急故障切换');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // 交互式选择规则
        if (!$ruleId) {
            $rules = FailoverRule::where('tenant_id', $tenantId)
                ->with(['primaryDc', 'backupDc'])
                ->get();

            if ($rules->isEmpty()) {
                $this->error('没有配置故障切换规则');
                $this->info('请先在管理后台配置: 多区域管理 → 故障切换规则');
                return Command::FAILURE;
            }

            $choices = $rules->map(fn($r) => "[#{$r->id}] {$r->name} ({$r->primaryDc->name} → {$r->backupDc->name}) [{$r->status}]")->toArray();
            $selected = $this->choice('选择故障切换规则', $choices);
            preg_match('/#(\d+)/', $selected, $m);
            $ruleId = $m[1];
        }

        $rule = FailoverRule::with(['primaryDc', 'backupDc'])->find($ruleId);
        if (!$rule) {
            $this->error("规则 #{$ruleId} 不存在");
            return Command::FAILURE;
        }

        // 显示切换信息
        $action = $isRestore ? '恢复回切' : '故障切换';
        $from = $isRestore ? $rule->backupDc->name : $rule->primaryDc->name;
        $to = $isRestore ? $rule->primaryDc->name : $rule->backupDc->name;

        $this->warn("⚠️  灾备 {$action}");
        $this->table(['项目', '值'], [
            ['规则名称', $rule->name],
            ['从', $from],
            ['到', $to],
            ['原因', $reason],
            ['当前状态', $rule->status],
            ['自动切换', $rule->auto_failover ? '开启' : '关闭'],
        ]);

        // 状态检查
        if ($isRestore && $rule->status !== 'failover') {
            $this->warn('⚠️  规则当前状态不是 failover，可能无需恢复');
            if (!$force && !$this->confirm('仍要继续？', false)) {
                return Command::SUCCESS;
            }
        }
        if (!$isRestore && $rule->status === 'failover') {
            $this->warn('⚠️  规则当前已处于 failover 状态');
            if (!$force && !$this->confirm('仍要再次切换？', false)) {
                return Command::SUCCESS;
            }
        }

        if ($dryRun) {
            $this->info('✅ Dry-run 模式：验证通过，未执行切换');
            $this->comment("  切换命令将会执行: dr:failover --rule={$rule->id} " . ($isRestore ? '--restore' : ''));
            return Command::SUCCESS;
        }

        if (!$force && !$this->confirm("确认执行 {$action}？此操作将切换流量到 {$to}", false)) {
            $this->info('已取消');
            return Command::SUCCESS;
        }

        // 执行切换
        $this->info("正在执行 {$action}...");

        try {
            if ($isRestore) {
                $log = $multiRegion->executeRestore($rule, $reason);
            } else {
                $log = $multiRegion->executeFailover($rule, $reason);
            }

            $this->info("✅ {$action} 完成");
            $this->table(['属性', '值'], [
                ['切换记录 ID', $log->id],
                ['动作', $log->action],
                ['从', $log->from_dc],
                ['到', $log->to_dc],
                ['时间', $log->created_at],
            ]);

            Log::warning("灾备切换已执行", [
                'rule_id' => $rule->id,
                'action' => $isRestore ? 'restore' : 'failover',
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("{$action} 失败: {$e->getMessage()}");
            Log::error("灾备切换失败", [
                'rule_id' => $rule->id,
                'action' => $isRestore ? 'restore' : 'failover',
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }

    private function listRules(MultiRegionService $multiRegion, int $tenantId): int
    {
        $rules = $multiRegion->listFailoverRules($tenantId);

        if ($rules->isEmpty()) {
            $this->info('没有配置故障切换规则');
            return Command::SUCCESS;
        }

        $this->table(
            ['ID', '名称', '主数据中心', '备用数据中心', '状态', '自动切换', '最后切换'],
            $rules->map(fn($r) => [
                $r->id,
                $r->name,
                $r->primaryDc?->name ?? '-',
                $r->backupDc?->name ?? '-',
                $r->status,
                $r->auto_failover ? '✅' : '❌',
                $r->last_failover_at ?? '-',
            ])
        );

        return Command::SUCCESS;
    }
}
