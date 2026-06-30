<?php

namespace App\Console\Commands;

use App\Services\KeyPrefixFormatter;
use Illuminate\Console\Command;

class MigrateLicenseKeyPrefixes extends Command
{
    protected $signature = 'license:prefix-migrate';
    protected $description = 'Migrate all existing License Keys to readable prefix format (HWT-ENT/HWT-PRO/HWT-TRIAL)';

    public function handle(KeyPrefixFormatter $formatter): int
    {
        $this->info('Starting License Key prefix migration...');

        $stats = $formatter->migrateAll();

        $this->info("Total: {$stats['total']}");
        $this->info("Updated: {$stats['updated']}");
        $this->info("Skipped: {$stats['skipped']}");

        if (!empty($stats['errors'])) {
            $this->warn('Errors:');
            foreach ($stats['errors'] as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $this->info('License Key prefix migration completed successfully.');
        return self::SUCCESS;
    }
}
