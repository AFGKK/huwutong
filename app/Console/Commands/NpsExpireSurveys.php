<?php

namespace App\Console\Commands;

use App\Models\NpsSurvey;
use Illuminate\Console\Command;

class NpsExpireSurveys extends Command
{
    protected $signature = 'nps:expire-surveys {--dry-run : 仅显示过期数量不执行}';
    protected $description = '将已过期的 NPS 调查标记为 expired';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $expired = NpsSurvey::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        $count = $expired->count();

        if ($count === 0) {
            $this->info('无待过期的调查');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("干运行: {$count} 份调查将过期");
            return Command::SUCCESS;
        }

        $updated = NpsSurvey::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("已将 {$updated} 份调查标记为过期");
        return Command::SUCCESS;
    }
}
