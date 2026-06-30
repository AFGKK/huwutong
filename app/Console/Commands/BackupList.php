<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupList extends Command
{
    protected $signature = 'backup:list
        {--type=database : 类型 database/files}
        {--limit=20 : 显示条数}
        {--status=completed : 状态过滤}';

    protected $description = '列出备份记录';

    public function handle(): int
    {
        $query = BackupRecord::byType($this->option('type'))
            ->where('status', $this->option('status'))
            ->latest()
            ->limit((int) $this->option('limit'));

        $records = $query->get(['id', 'name', 'type', 'status', 'file_size', 'duration_seconds', 'completed_at', 'expires_at']);

        if ($records->isEmpty()) {
            $this->info('没有找到备份记录');
            return Command::SUCCESS;
        }

        $headers = ['ID', '名称', '类型', '状态', '大小', '耗时(秒)', '完成时间', '到期时间'];
        $rows = $records->map(fn($r) => [
            $r->id,
            $r->name,
            $r->type,
            $r->status,
            $r->formatted_size,
            $r->duration_seconds,
            $r->completed_at?->toDateTimeString(),
            $r->expires_at?->toDateTimeString(),
        ])->toArray();

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }
}
