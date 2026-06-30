<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class DataRetentionApplyCommand extends Command
{
    protected $signature = 'data-retention:apply
                           {--policy= : 指定策略键名，不指定则执行所有}
                           {--dry-run : 预览模式，不实际删除}
                           {--sync : 从 config/data-retention.php 同步策略}
                           {--force : 强制跳过确认}';

    protected $description = '执行数据留存策略（清理到期数据）';

    public function handle(DataRetentionService $service): int
    {
        // 同步策略
        if ($this->option('sync')) {
            $this->components->task('同步策略配置', function () use ($service) {
                $result = $service->syncPoliciesFromConfig();
                $this->newLine();
                $this->components->twoColumnDetail('新增', (string) $result['created']);
                $this->components->twoColumnDetail('更新', (string) $result['synced']);
                $this->info($result['message']);
            });
            $this->newLine();
        }

        $policy = $this->option('policy');
        $dryRun = $this->option('dry-run');

        if (!$dryRun && !$this->option('force')) {
            $msg = $policy
                ? "即将清理策略「{$policy}」的到期数据"
                : '即将清理全部策略的到期数据';

            if ($this->input->isInteractive()) {
                if (!$this->confirm("{$msg}，是否继续？")) {
                    $this->warn('已取消');
                    return self::SUCCESS;
                }
            }
        }

        $this->components->task(
            $dryRun ? '预览数据清理' : '执行数据清理',
            function () use ($service, $policy, $dryRun) {
                $result = $service->cleanup($policy, $dryRun);
                $this->table(
                    ['策略', '状态', '影响记录', '说明'],
                    collect($result['results'])->map(fn ($r) => [
                        $r['policy'],
                        $r['status'],
                        $r['affected_records'] ?? 0,
                        $r['message'] ?? ($r['table'] ?? ''),
                    ])->toArray()
                );
                $this->components->twoColumnDetail('总计策略', (string) $result['total_policies']);
                $this->components->twoColumnDetail('影响记录', (string) $result['total_affected']);
            }
        );

        return self::SUCCESS;
    }
}
