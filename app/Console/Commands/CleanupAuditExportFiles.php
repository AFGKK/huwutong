<?php

namespace App\Console\Commands;

use App\Services\AuditExportService;
use Illuminate\Console\Command;

class CleanupAuditExportFiles extends Command
{
    protected $signature = 'audit:cleanup-exports';
    protected $description = 'Clean up expired audit export files';

    public function handle(AuditExportService $service): void
    {
        $this->info('Cleaning up expired audit export files...');
        $cleaned = $service->cleanupExpiredFiles();
        $this->info("Cleaned up {$cleaned} expired file(s).");
    }
}
