<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateArticleEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-article-embeddings';

    protected $description = '为所有已发布的文章生成 AI 嵌入向量，用于智能推荐';

    public function handle()
    {
        $this->info('开始生成文章嵌入向量...');
        $service = app(\App\Services\AiRecommendationService::class);
        $count = $service->generateAllEmbeddings();
        $this->info("完成！已为 {$count} 篇文章生成了嵌入向量。");
    }
}
