<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateProductEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-product-embeddings';

    protected $description = '为所有活跃产品生成 AI 嵌入向量，用于智能推荐';

    public function handle()
    {
        $this->info('开始生成产品嵌入向量...');
        $service = app(\App\Services\AiRecommendationService::class);
        $count = $service->generateAllProductEmbeddings();
        $this->info("完成！已为 {$count} 个产品生成了嵌入向量。");
    }
}
