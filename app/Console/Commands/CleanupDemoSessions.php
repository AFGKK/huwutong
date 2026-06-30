<?php

namespace App\Console\Commands;

use App\Models\DemoSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 清理过期演示会话
 *
 * 每小时执行：清理超过30分钟的过期会话
 *
 * @m3-70 InteractiveDemo
 */
class CleanupDemoSessions extends Command
{
    protected $signature = 'demo:cleanup {--max-age=60 : 清理超过多少分钟的过期会话}';
    protected $description = '清理过期的交互式演示会话';

    public function handle(): int
    {
        $maxAgeMinutes = (int) $this->option('max-age');
        $cutoff = now()->subMinutes($maxAgeMinutes);

        $count = DemoSession::where('status', 'active')
            ->where('expires_at', '<', $cutoff)
            ->update(['status' => 'expired']);

        // 彻底删除24小时前的已过期/已完成会话
        $deleted = DemoSession::whereIn('status', ['expired', 'completed'])
            ->where('updated_at', '<', now()->subHours(24))
            ->delete();

        $this->info("已标记 {$count} 个会话为过期，已删除 {$deleted} 个旧会话");

        Log::info('Demo cleanup completed', [
            'expired' => $count,
            'deleted' => $deleted,
        ]);

        return self::SUCCESS;
    }
}
