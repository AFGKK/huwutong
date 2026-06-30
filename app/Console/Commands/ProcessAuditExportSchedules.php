<?php

namespace App\Console\Commands;

use App\Services\AuditExportService;
use Illuminate\Console\Command;

class ProcessAuditExportSchedules extends Command
{
    protected $signature = 'audit:process-schedules';
    protected $description = 'Execute due audit export schedules';

    public function handle(AuditExportService $service): void
    {
        $this->info('Processing due audit export schedules...');
        $results = $service->processDueSchedules();
        $count = count($results);

        foreach ($results as $result) {
            if (isset($result['error'])) {
                $this->error("  Schedule #{$result['schedule_id']}: {$result['error']}");
            } else {
                $this->info("  Schedule #{$result['schedule_id']} -> Task #{$result['task_id']} ({$result['status']})");
            }
        }

        $this->info("Processed {$count} schedule(s).");
    }
}
