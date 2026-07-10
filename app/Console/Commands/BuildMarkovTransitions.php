<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildMarkovTransitions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:build-markov-transitions {--seed : 从现有阅读/点赞记录导入历史行为}';

    protected $description = '构建马尔可夫转移矩阵并可选导入历史行为数据';

    public function handle()
    {
        if ($this->option('seed')) {
            $this->seedHistoricalData();
        }

        $this->info('构建马尔可夫转移矩阵...');
        $service = app(\App\Services\BehaviorSequenceService::class);
        $result = $service->buildTransitionMatrix();

        $this->info("完成！一阶转移: {$result['first_order']} 条, 二阶转移: {$result['second_order']} 条");
    }

    private function seedHistoricalData()
    {
        $this->info('导入历史行为数据...');
        $bar = $this->output->createProgressBar(4);
        $bar->start();

        $seqService = app(\App\Services\BehaviorSequenceService::class);
        $count = 0;

        // 从阅读记录导入
        $reads = \App\Models\OaArticleRead::whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(90))
            ->orderBy('user_id')->orderBy('created_at')
            ->get(['user_id', 'article_id', 'created_at']);

        $lastUser = null;
        $sessionArticles = [];
        foreach ($reads as $r) {
            if ($r->user_id !== $lastUser && !empty($sessionArticles)) {
                $sessionArticles = [];
            }
            $lastUser = $r->user_id;
            DB::table('oa_behavior_sequences')->insert([
                'user_id'    => $r->user_id,
                'article_id' => $r->article_id,
                'action'     => 'read',
                'session_id' => "hist_read_{$r->user_id}",
                'position'   => count($sessionArticles) + 1,
                'created_at' => $r->created_at,
            ]);
            $sessionArticles[] = $r->article_id;
            $count++;
        }
        $bar->advance();

        // 从点赞记录导入
        $likes = \App\Models\Like::where('likeable_type', 'App\\Models\\OaArticle')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(90))
            ->orderBy('user_id')->orderBy('created_at')
            ->get(['user_id', 'likeable_id', 'created_at']);

        foreach ($likes as $l) {
            DB::table('oa_behavior_sequences')->insert([
                'user_id'    => $l->user_id,
                'article_id' => $l->likeable_id,
                'action'     => 'like',
                'session_id' => "hist_like_{$l->user_id}",
                'position'   => 1,
                'created_at' => $l->created_at,
            ]);
            $count++;
        }
        $bar->advance();

        // 从收藏记录导入
        $favs = \App\Models\Favorite::where('favorable_type', 'App\\Models\\OaArticle')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', now()->subDays(90))
            ->orderBy('user_id')->orderBy('created_at')
            ->get(['user_id', 'favorable_id', 'created_at']);

        foreach ($favs as $f) {
            DB::table('oa_behavior_sequences')->insert([
                'user_id'    => $f->user_id,
                'article_id' => $f->favorable_id,
                'action'     => 'favorite',
                'session_id' => "hist_fav_{$f->user_id}",
                'position'   => 1,
                'created_at' => $f->created_at,
            ]);
            $count++;
        }
        $bar->advance();

        $bar->finish();
        $this->newLine();
        $this->info("已导入 {$count} 条历史行为记录");
    }
}
