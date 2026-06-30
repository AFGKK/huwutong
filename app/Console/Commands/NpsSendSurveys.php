<?php

namespace App\Console\Commands;

use App\Services\NpsSurveyService;
use Illuminate\Console\Command;

class NpsSendSurveys extends Command
{
    protected $signature = 'nps:send-surveys {--limit=50 : 每次发送数量} {--dry-run : 仅显示符合条件用户不发送}';
    protected $description = '自动向符合条件的用户发送 NPS 满意度调查';

    public function handle(NpsSurveyService $npsService): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('正在查找符合条件的用户...');
        $users = $npsService->getEligibleUsers($limit);

        if (empty($users)) {
            $this->info('暂无符合条件的用户');
            return Command::SUCCESS;
        }

        $this->info("找到 {$limit} 个符合条件的用户");

        if ($dryRun) {
            $this->warn('干运行模式 — 未发送任何调查');
            $this->table(['User ID'], array_map(fn($id) => [$id], $users));
            return Command::SUCCESS;
        }

        $sent = 0;
        foreach ($users as $userId) {
            try {
                $npsService->sendSurvey($userId);
                $sent++;
                $this->output->write('.');
            } catch (\Throwable $e) {
                $this->error("发送失败 (User:{$userId}): {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("已发送 {$sent}/{$limit} 份调查");

        return Command::SUCCESS;
    }
}
