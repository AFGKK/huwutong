<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCleanup extends Command
{
    protected $signature = 'backup:cleanup
        {--type=all : database/files/all}';

    protected $description = '清理过期备份';

    public function handle(BackupService $backupService): int
    {
        $type = $this->option('type');

        $types = $type === 'all' ? ['database', 'files'] : [$type];

        $total = 0;
        foreach ($types as $t) {
            $count = $backupService->cleanupExpired($t);
            $this->info("清理 {$t} 过期备份: {$count} 个");
            $total += $count;
        }

        $this->info("共清理 {$total} 个过期备份");

        return Command::SUCCESS;
    }
}
