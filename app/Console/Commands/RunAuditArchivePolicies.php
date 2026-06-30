<?php

namespace App\Console\Commands;

use App\Services\AuditExportService;
use Illuminate\Console\Command;

class RunAuditArchivePolicies extends Command
{
    protected $signature = 'audit:run-archives';
    protected $description = 'Execute all active audit archive policies';

    public function handle(AuditExportService $service): void
    {
        $this->info('Running audit archive policies...');
        $results = $service->executeArchivePolicies();

        foreach ($results as $result) {
            if (isset($result['error'])) {
                $this->error("  Policy #{$result['policy_id']}: {$result['error']}");
            } else {
                $stat = "Type: {$result['type']}, Archived: {$result['archived']}, Deleted: {$result['deleted']}";
                if ($result['archive_file']) {
                    $stat .= ", File: {$result['archive_file']}";
                }
                $this->info("  Policy #{$result['policy_id']}: {$stat}");
            }
        }

        $this->info('Archive policies execution completed.');
    }
}
