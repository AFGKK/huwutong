<?php

namespace App\Console\Commands;

use App\Services\NpsSurveyService;
use Illuminate\Console\Command;

class NpsGenerateSnapshot extends Command
{
    protected $signature = 'nps:generate-snapshot {--date= : 指定日期 (Y-m-d)}';
    protected $description = '生成每日 NPS 汇总快照';

    public function handle(NpsSurveyService $npsService): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $this->info("正在生成 {$date} 的 NPS 快照...");

        $npsService->generateDailySnapshot();

        $this->info('NPS 快照生成完成');
        return Command::SUCCESS;
    }
}
