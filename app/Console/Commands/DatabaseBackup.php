<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup
        {--name= : 自定义备份名称}
        {--no-cleanup : 备份后不自动清理过期备份}';

    protected $description = '执行数据库备份';

    public function handle(BackupService $backupService): int
    {
        $this->info('开始数据库备份...');

        try {
            $record = $backupService->backupDatabase($this->option('name'));

            $this->info("备份完成: {$record->file_name}");
            $this->info("大小: {$record->formatted_size}");
            $this->info("耗时: {$record->duration_seconds}秒");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("备份失败: {$e->getMessage()}");
            Log::error('数据库备份命令失败', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
