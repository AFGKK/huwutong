<?php

namespace App\Console\Commands;

use App\Services\SystemHealthService;
use Illuminate\Console\Command;

class RecordSystemHealthSnapshot extends Command
{
    protected $signature = 'system-health:snapshot';
    protected $description = '记录系统健康快照（定时执行）';

    public function handle(SystemHealthService $healthService): int
    {
        try {
            $log = $healthService->snapshot();
            $this->info("健康快照已记录 [ID: {$log->id}, 评分: {$log->overall_score}, 状态: {$log->status}]");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("快照记录失败: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
