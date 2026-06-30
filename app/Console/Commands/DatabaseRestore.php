<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * M2-36 🗄️ 数据库恢复命令
 *
 * 从备份记录恢复数据库。支持从本地或远程存储恢复。
 *
 * 用法:
 *   php artisan db:restore                  — 交互式选择恢复
 *   php artisan db:restore --backup=42      — 指定备份 ID 恢复
 *   php artisan db:restore --latest         — 恢复到最近的备份
 *   php artisan db:restore --dry-run        — 仅验证不实际恢复
 */
class DatabaseRestore extends Command
{
    protected $signature = 'db:restore
        {--backup= : 指定备份记录 ID}
        {--latest : 恢复到最近的备份}
        {--dry-run : 仅验证不实际执行}
        {--force : 跳过确认提示}';

    protected $description = 'M2-36 从备份恢复数据库（整库恢复）';

    public function handle(BackupService $backupService): int
    {
        $backupId = $this->option('backup');
        $latest = $this->option('latest');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // 确定要恢复的备份
        if ($backupId) {
            $record = BackupRecord::find($backupId);
        } elseif ($latest) {
            $record = BackupRecord::byType('database')->completed()->latest()->first();
        } else {
            // 交互式选择
            $records = BackupRecord::byType('database')->completed()->latest()->take(20)->get();
            if ($records->isEmpty()) {
                $this->error('没有可用的数据库备份记录');
                return Command::FAILURE;
            }
            $choices = $records->map(fn($r) => "[#{$r->id}] {$r->name} - {$r->formatted_size} - {$r->created_at}")->toArray();
            $selected = $this->choice('选择要恢复的备份', $choices);
            preg_match('/#(\d+)/', $selected, $m);
            $record = BackupRecord::find($m[1]);
        }

        if (!$record) {
            $this->error('未找到指定的备份记录');
            return Command::FAILURE;
        }

        $this->warn("⚠️  数据库恢复操作");
        $this->table(['属性', '值'], [
            ['备份 ID', $record->id],
            ['备份名称', $record->name],
            ['文件大小', $record->formatted_size],
            ['创建时间', $record->created_at],
            ['备份类型', $record->type],
            ['数据库', $record->database],
        ]);

        if ($dryRun) {
            $this->info('✅ Dry-run 模式：备份可用，未执行恢复');
            return Command::SUCCESS;
        }

        if (!$force && !$this->confirm('确认恢复？当前数据将被覆盖！', false)) {
            $this->info('已取消');
            return Command::SUCCESS;
        }

        $this->info('正在恢复数据库...');

        try {
            $backupService->restoreDatabase($record);
            $this->info('✅ 数据库恢复成功');
            $this->warn('建议恢复后执行: php artisan cache:clear && php artisan config:clear');

            Log::info('数据库恢复完成', [
                'backup_id' => $record->id,
                'backup_name' => $record->name,
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("恢复失败: {$e->getMessage()}");
            Log::error('数据库恢复失败', [
                'backup_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }
}
