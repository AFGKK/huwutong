<?php

namespace App\Console\Commands;

use App\Models\AiFriendProfile;
use App\Models\OaArticle;
use App\Models\OfficialAccount;
use App\Services\AiFriendOrchestrator;
use App\Events\OaArticlePublished;
use Illuminate\Console\Command;

class AiAutoWrite extends Command
{
    protected $signature = 'ai:auto-write {--limit=2 : 每次创作篇数} {--category=writer : AI 好友类别}';
    protected $description = 'AI 虚拟作者定时自动创作文章';

    public function handle(AiFriendOrchestrator $orchestrator): int
    {
        $limit = (int) $this->option('limit');
        $category = $this->option('category');

        $writers = AiFriendProfile::where('category', $category)
            ->with('llmConfig')
            ->whereHas('llmConfig')
            ->get();

        if ($writers->isEmpty()) {
            $this->warn("没有找到 {$category} 类别的 AI 好友，请先创建一个");
            return Command::SUCCESS;
        }

        $accounts = OfficialAccount::where('status', 'active')->get();
        if ($accounts->isEmpty()) {
            $this->warn('没有可用的互物号');
            return Command::SUCCESS;
        }

        $topics = ['行业趋势分析', '技术教程', '产品更新', '用户案例', '最佳实践', '常见问题'];
        $count = 0;

        foreach ($writers as $writer) {
            for ($i = 0; $i < $limit; $i++) {
                try {
                    $topic = $topics[array_rand($topics)];
                    $result = $orchestrator->forFriend($writer)->generate(null,
                        "请写一篇关于「{$topic}」的互物号文章，标题要吸引人，内容专业有价值，500-800字。返回格式：\n标题：xxx\n\n正文内容");

                    $reply = $result['content'] ?? '';
                    if (empty($reply)) continue;

                    // 解析标题和内容
                    $title = 'AI 自动创作';
                    $content = $reply;
                    if (preg_match('/^标题[：:]\s*(.+)/m', $reply, $tm)) {
                        $title = trim($tm[1]);
                        $content = preg_replace('/^标题[：:].+(\n|$)/m', '', $reply);
                    }

                    $account = $accounts->random();

                    $article = OaArticle::create([
                        'account_id' => $account->id,
                        'author_id' => $writer->user_id,
                        'title' => mb_substr($title, 0, 200),
                        'content' => $content,
                        'summary' => mb_substr(strip_tags($content), 0, 200),
                        'status' => 'published',
                        'published_at' => now(),
                    ]);

                    OaArticlePublished::dispatch($article);
                    $count++;
                    $this->info("已创作并发布: {$title}");
                } catch (\Throwable $e) {
                    $this->error("创作失败: " . $e->getMessage());
                }
            }
        }

        $this->info("本次共创作 {$count} 篇文章");
        return Command::SUCCESS;
    }
}
