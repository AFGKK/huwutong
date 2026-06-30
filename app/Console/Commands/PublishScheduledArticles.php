<?php

namespace App\Console\Commands;

use App\Models\OaArticle;
use Illuminate\Console\Command;

class PublishScheduledArticles extends Command
{
    protected $signature = 'oa:publish-scheduled';
    protected $description = '发布所有到期的定时文章';

    public function handle()
    {
        $count = OaArticle::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->update([
                'status' => 'published',
                'published_at' => now(),
                'scheduled_at' => null,
            ]);

        $this->info("已发布 {$count} 篇定时文章");
        return Command::SUCCESS;
    }
}
