<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class FileBackup extends Command
{
    protected $signature = 'files:backup
        {--name= : 自定义备份名称}';

    protected $description = '执行文件备份';

    public function handle(BackupService $backupService): int
    {
        $this->info('开始文件备份...');

        try {
            $record = $backupService->backupFiles($this->option('name'));

            $this->info("备份完成: {$record->file_name}");
            $this->info("大小: {$record->formatted_size}");
            $this->info("耗时: {$record->duration_seconds}秒");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("备份失败: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
