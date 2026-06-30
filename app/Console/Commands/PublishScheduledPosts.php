<?php

namespace App\Console\Commands;

use App\Models\ForumPost;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'plaza:publish-scheduled';

    protected $description = 'Publish scheduled forum posts that are due';

    public function handle()
    {
        $count = ForumPost::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->update(['status' => 'published', 'scheduled_at' => null]);

        $this->info("Published {$count} scheduled posts.");
    }
}
