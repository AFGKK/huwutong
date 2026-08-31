<?php

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Support\Facades\Log;

/**
 * AI 摘要生成服务
 * 调用 LLM 为博客文章生成一句话摘要
 */
class BlogSummaryService
{
    protected LlmService $llm;

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    /**
     * 为文章生成摘要并保存
     */
    public function generate(BlogPost $post): string
    {
        // 如果已有摘要且不是AI生成的，跳过
        if ($post->excerpt && !str_contains($post->excerpt, '[AI]')) {
            return $post->excerpt;
        }

        $content = strip_tags($post->content);
        $title = $post->title;

        try {
            $prompt = "你是博客摘要助手。请为以下文章生成一句话摘要（30-60字），用简洁的中文概括核心内容。\n\n标题：{$title}\n\n内容：" . mb_substr($content, 0, 1000);

            $result = $this->llm->chat([
                ['role' => 'user', 'content' => $prompt],
            ], ['temperature' => 0.3, 'max_tokens' => 150]);

            $summary = trim($result['content'] ?? '');
            if (empty($summary)) {
                throw new \Exception(__("app.blog_summary.ai_empty_summary"));
            }

            // 清理可能的引号
            $summary = trim($summary, '"\'「」『』');
            $summary = '[AI] ' . $summary;

            // 保存到数据库
            $post->update(['excerpt' => $summary]);

            return $summary;

        } catch (\Throwable $e) {
            Log::warning('[BlogSummary] AI 摘要生成失败: ' . $e->getMessage());

            // 降级：取前100字作为摘要
            $fallback = mb_substr(strip_tags($content), 0, 100);
            if (mb_strlen($fallback) > 10) {
                $post->update(['excerpt' => $fallback]);
                return $fallback;
            }

            return $post->excerpt ?? '';
        }
    }
}
