<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\KbAutoGrowDraft;
use App\Models\RagMessage;
use App\Models\AgentMessage;
use App\Models\HandoffRequest;
use App\Models\ForumPost;
use App\Models\ConversationMessage;
use App\Services\LlmService;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\Log;

/**
 * AI 知识库自增长服务
 *
 * 从客服对话、AI 聊天、广场帖子等来源自动提取知识，
 * 经 LLM 蒸馏去重后生成 KB 文章草稿供人工审核。
 */
class KbAutoGrowService
{
    protected LlmService $llm;
    protected KnowledgeBaseService $kb;

    public function __construct(LlmService $llm, KnowledgeBaseService $kb)
    {
        $this->llm = $llm;
        $this->kb = $kb;
    }

    /**
     * 运行一次完整的知识提取管道
     *
     * @param array $options {sources: string[], limitPerSource: int, minConfidence: float}
     * @return array{total_extracted: int, by_source: array}
     */
    public function run(array $options = []): array
    {
        $sources = $options['sources'] ?? ['rag_chat', 'handoff', 'forum_post', 'im_chat'];
        $limitPerSource = $options['limit_per_source'] ?? 20;
        $minConfidence = $options['min_confidence'] ?? 0.5;

        $results = ['total_extracted' => 0, 'by_source' => []];

        foreach ($sources as $source) {
            $method = 'scan' . str_replace('_', '', ucwords($source, '_'));
            if (!method_exists($this, $method)) {
                Log::warning("[KbAutoGrow] 未知来源: {$source}");
                continue;
            }

            try {
                $count = $this->$method($limitPerSource, $minConfidence);
                $results['by_source'][$source] = $count;
                $results['total_extracted'] += $count;
                Log::info("[KbAutoGrow] {$source}: 提取了 {$count} 条知识");
            } catch (\Throwable $e) {
                Log::error("[KbAutoGrow] {$source} 提取失败: " . $e->getMessage());
                $results['by_source'][$source] = 0;
            }
        }

        return $results;
    }

    // ═══════════════════════════════════════════
    //  各来源扫描
    // ═══════════════════════════════════════════

    /**
     * 扫描 AI 客服对话（高价值：高置信度 / 用户标记有用）
     */
    protected function scanRagChat(int $limit, float $minConfidence): int
    {
        $messages = RagMessage::where('role', 'assistant')
            ->where(function ($q) use ($minConfidence) {
                $q->where('was_helpful', true)
                  ->orWhere('confidence', '>=', $minConfidence);
            })
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->latest()
            ->take($limit)
            ->get();

        $extracted = 0;
        foreach ($messages as $msg) {
            // 查找对应的用户问题
            $userMsg = RagMessage::where('conversation_id', $msg->conversation_id)
                ->where('created_at', '<', $msg->created_at)
                ->where('role', 'user')
                ->latest()
                ->first();

            if (!$userMsg || empty(trim($userMsg->content))) continue;

            $qaPair = "问：{$userMsg->content}\n答：{$msg->content}";

            if ($this->extractAndSave($qaPair, 'rag_chat', $msg->id, $msg->confidence ?? 0.6)) {
                $extracted++;
            }
        }

        return $extracted;
    }

    /**
     * 扫描已解决的人工客服对话
     */
    protected function scanHandoff(int $limit, float $minConfidence): int
    {
        $resolvedRequests = HandoffRequest::whereIn('status', ['resolved', 'closed'])
            ->latest()
            ->take($limit)
            ->get();

        $extracted = 0;
        foreach ($resolvedRequests as $request) {
            $messages = AgentMessage::where('handoff_request_id', $request->id)
                ->whereNotNull('content')
                ->orderBy('created_at')
                ->get();

            if ($messages->isEmpty()) continue;

            $conversationText = $messages->map(fn($m) => 
                ($m->sender_type === 'customer' ? '客户' : '客服') . "：{$m->content}"
            )->implode("\n");

            if ($this->extractAndSave($conversationText, 'handoff', $request->id, 0.5)) {
                $extracted++;
            }
        }

        return $extracted;
    }

    /**
     * 扫描广场高赞帖子
     */
    protected function scanForumPost(int $limit, float $minConfidence): int
    {
        $posts = ForumPost::where('is_locked', false)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->orderBy('likes_count', 'desc')
            ->take($limit)
            ->get();

        $extracted = 0;
        foreach ($posts as $post) {
            $title = $post->title ?? '';
            $text = "标题：{$title}\n内容：{$post->content}";

            // 计算帖子热度作为置信度参考
            $confidence = min(0.95, 0.3 + ($post->likes_count ?? 0) * 0.05);

            if ($this->extractAndSave($text, 'forum_post', $post->id, $confidence)) {
                $extracted++;
            }
        }

        return $extracted;
    }

    /**
     * 扫描 IM 群聊消息（提取 FAQ 类知识）
     */
    protected function scanImChat(int $limit, float $minConfidence): int
    {
        // 取最近有回复的群聊消息（sender 发了问题，有人回复了）
        $messages = ConversationMessage::where('message_type', 'text')
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->whereHas('conversation', fn($q) => $q->where('type', 'group'))
            ->latest()
            ->take($limit * 3)
            ->get();

        $extracted = 0;
        $seen = [];

        foreach ($messages as $msg) {
            // 避免重复提取相似内容
            $key = md5(mb_substr($msg->content, 0, 50));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            // 查找是否有回复
            $reply = ConversationMessage::where('conversation_id', $msg->conversation_id)
                ->where('thread_parent_id', $msg->id)
                ->whereNotNull('content')
                ->first();

            if (!$reply) continue;

            $pair = "问：{$msg->content}\n答：{$reply->content}";

            if ($this->extractAndSave($pair, 'im_chat', $msg->id, 0.4)) {
                $extracted++;
            }
        }

        return $extracted;
    }

    // ═══════════════════════════════════════════
    //  核心：LLM 提取 + 去重 + 保存
    // ═══════════════════════════════════════════

    /**
     * 对一段对话文本执行 LLM 知识提取
     *
     * @return bool 是否提取到新知识
     */
    protected function extractAndSave(string $sourceText, string $sourceType, int $sourceId, float $baseConfidence): bool
    {
        if (mb_strlen(trim($sourceText)) < 20) return false;

        // 去重检查：相似内容是否已有草稿或已发布文章
        $textHash = md5(mb_substr($sourceText, 0, 100));
        $exists = KbAutoGrowDraft::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();

        if ($exists) return false;

        // 调用 LLM 提取结构化知识
        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => implode("\n", [
                    '你是一个知识提取专家。从以下对话/文本中提取有价值的知识点。',
                    '如果文本包含解决问题的方法、操作步骤、最佳实践或常见问题解答，请提取为结构化知识。',
                    '如果没有有价值的知识，返回 JSON: {"has_knowledge": false}',
                    '如果有，返回 JSON:',
                    json_encode([
                        'has_knowledge' => true,
                        'title' => '简洁的标题（15字内）',
                        'content' => '详细的文章内容（Markdown格式，200-500字）',
                        'excerpt' => '一句话摘要（50字内）',
                        'tags' => ['标签1', '标签2'],
                        'confidence' => 0.0, // 0~1 置信度评分
                    ], JSON_UNESCAPED_UNICODE),
                    '只返回 JSON，不要其他内容。',
                ])],
                ['role' => 'user', 'content' => $sourceText],
            ], [
                'temperature' => 0.2,
                'max_tokens' => 2000,
                'model' => 'deepseek-chat',
            ], 'kb_auto_grow');

            $reply = $result['content'] ?? '';

            // 提取 JSON
            if (!preg_match('/\{.*\}/s', $reply, $m)) return false;

            $parsed = json_decode($m[0], true);
            if (!$parsed || empty($parsed['has_knowledge']) || empty($parsed['title'])) return false;

            $confidence = min(0.95, ($parsed['confidence'] ?? $baseConfidence) * 1.1);

            // 最终置信度过滤
            if ($confidence < 0.3) return false;

            // 标题级去重
            $dupTitle = KbAutoGrowDraft::where('title', $parsed['title'])->exists();
            $dupArticle = KbArticle::where('title', $parsed['title'])->exists();
            if ($dupTitle || $dupArticle) return false;

            // 保存草稿
            KbAutoGrowDraft::create([
                'title' => mb_substr($parsed['title'], 0, 300),
                'content' => $parsed['content'] ?? '',
                'excerpt' => mb_substr($parsed['excerpt'] ?? '', 0, 500),
                'tags' => $parsed['tags'] ?? [],
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_summary' => mb_substr($sourceText, 0, 200),
                'confidence' => $confidence,
                'status' => 'pending',
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::warning("[KbAutoGrow] LLM 提取失败: " . $e->getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════
    //  审核操作
    // ═══════════════════════════════════════════

    /**
     * 审核通过：将草稿发布为 KB 文章
     */
    public function approve(int $draftId, int $reviewerId): ?KbArticle
    {
        $draft = KbAutoGrowDraft::findOrFail($draftId);
        if ($draft->status !== 'pending') return null;

        try {
            $article = DB::transaction(function () use ($draft, $reviewerId) {
                $article = $this->kb->createArticle([
                    'title' => $draft->title,
                    'content' => $draft->content,
                    'excerpt' => $draft->excerpt,
                    'tags' => $draft->tags ?? [],
                    'status' => 'published',
                    'published_at' => now(),
                ]);

                $draft->update([
                    'status' => 'approved',
                    'kb_article_id' => $article->id,
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => now(),
                ]);

                return $article;
            });

            Log::info("[KbAutoGrow] 审核通过草稿 #{$draftId} → 文章 #{$article->id}");
            return $article;

        } catch (\Throwable $e) {
            Log::error("[KbAutoGrow] 审核失败 #{$draftId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 审核拒绝
     */
    public function reject(int $draftId, int $reviewerId): bool
    {
        $draft = KbAutoGrowDraft::findOrFail($draftId);
        if ($draft->status !== 'pending') return false;

        return $draft->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * 获取待审核列表
     */
    public function getPendingDrafts(int $perPage = 20)
    {
        return KbAutoGrowDraft::pending()
            ->orderBy('confidence', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'pending' => KbAutoGrowDraft::where('status', 'pending')->count(),
            'approved' => KbAutoGrowDraft::where('status', 'approved')->count(),
            'rejected' => KbAutoGrowDraft::where('status', 'rejected')->count(),
            'by_source' => KbAutoGrowDraft::selectRaw('source_type, count(*) as total')
                ->groupBy('source_type')
                ->pluck('total', 'source_type')
                ->toArray(),
        ];
    }
}
