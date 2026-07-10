<?php

namespace App\Services;

use App\Models\ContentModeration;
use App\Models\ConversationMessage;
use App\Models\ChannelMessage;
use App\Models\ForumPost;
use Illuminate\Support\Facades\Log;

/**
 * AI 内容质量评分与自动化运营服务
 *
 * 对 IM 消息/频道/广场等内容进行质量评分，
 * 自动折叠低质内容、归档过期内容、标记违规。
 */
class ContentQualityService
{
    protected LlmService $llm;

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    // ═══════════════════════════════════════════
    //  质量评分
    // ═══════════════════════════════════════════

    /**
     * 对一段内容进行质量评分（规则 + LLM 辅助）
     *
     * @return array{score: float, issues: array, suggestion: string}
     */
    public function rate(string $content, string $type = 'text'): array
    {
        $issues = [];
        $score = 1.0;

        // 规则1: 过短内容
        $len = mb_strlen(trim($content));
        if ($len < 5) {
            $issues[] = '内容过短';
            $score -= 0.3;
        } elseif ($len < 15) {
            $issues[] = '内容较短';
            $score -= 0.1;
        }

        // 规则2: 纯标点/表情
        if (preg_match('/^[\p{P}\p{So}\s]+$/u', trim($content))) {
            $issues[] = '纯符号/表情';
            $score -= 0.4;
        }

        // 规则3: 重复内容
        if ($len > 10) {
            $unique = count(array_unique(preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY)));
            $ratio = $unique / $len;
            if ($ratio < 0.2) {
                $issues[] = '字符高度重复';
                $score -= 0.3;
            }
            // 重复短句
            $sentences = array_count_values(array_filter(preg_split('/[。！？\n]+/u', $content)));
            foreach ($sentences as $s => $count) {
                if ($count > 2 && mb_strlen($s) > 2) {
                    $issues[] = '重复发送相同内容';
                    $score -= 0.2;
                    break;
                }
            }
        }

        // 规则4: 全部大写/夸张格式
        if (preg_match('/[A-Z]/u', $content)) {
            $upperCount = mb_strlen(preg_replace('/[^A-Z]/u', '', $content));
            if ($upperCount > $len * 0.7 && $len > 5) {
                $issues[] = '过度使用大写';
                $score -= 0.15;
            }
        }

        // 规则5: URL 占比过高
        $urlCount = preg_match_all('/https?:\/\/[^\s]+/u', $content);
        if ($urlCount > 2 && $len < 100) {
            $issues[] = '纯链接/广告倾向';
            $score -= 0.2;
        }

        $score = max(0, min(1, $score));

        // 建议
        $suggestion = 'approved';
        if ($score < 0.3) {
            $suggestion = 'deleted';
        } elseif ($score < 0.5) {
            $suggestion = 'folded';
        } elseif ($score < 0.7) {
            $suggestion = 'flagged';
        }

        return [
            'score' => round($score, 2),
            'issues' => $issues,
            'suggestion' => $suggestion,
        ];
    }

    // ═══════════════════════════════════════════
    //  批量扫描与处理
    // ═══════════════════════════════════════════

    /**
     * 扫描并处理低质量 IM 消息
     */
    public function scanMessages(int $limit = 50): array
    {
        $processed = ['folded' => 0, 'deleted' => 0, 'flagged' => 0];

        $messages = ConversationMessage::where('message_type', 'text')
            ->whereNotNull('content')
            ->whereNull('deleted_at')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($messages as $msg) {
            $result = $this->rate($msg->content, 'text');

            if ($result['score'] >= 0.7) continue;

            $action = $result['suggestion'];
            $this->applyAction($msg, $action, $result, 'auto');
            $processed[$action]++;
        }

        return $processed;
    }

    /**
     * 扫描并处理低质频道消息
     */
    public function scanChannelMessages(int $limit = 50): array
    {
        $processed = ['folded' => 0, 'deleted' => 0, 'flagged' => 0];

        $messages = ChannelMessage::whereNotNull('content')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($messages as $msg) {
            $result = $this->rate($msg->content, 'text');
            if ($result['score'] >= 0.7) continue;

            $action = $result['suggestion'];
            $this->applyAction($msg, $action, $result, 'auto');
            $processed[$action]++;
        }

        return $processed;
    }

    /**
     * 扫描并处理低质/过期广场帖子
     */
    public function scanForumPosts(int $limit = 50, int $archiveDays = 90): array
    {
        $processed = ['folded' => 0, 'archived' => 0, 'deleted' => 0];

        $posts = ForumPost::where('is_locked', false)
            ->whereNotNull('content')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($posts as $post) {
            $result = $this->rate($post->content, 'text');

            // 低质量折叠
            if ($result['score'] < 0.5) {
                $this->applyAction($post, 'folded', $result, 'auto');
                $processed['folded']++;
                continue;
            }

            // 过期归档
            if ($post->created_at && $post->created_at->lt(now()->subDays($archiveDays))) {
                if ($post->replies_count === 0 && $post->likes_count === 0) {
                    $this->applyAction($post, 'archived', [
                        'score' => 0.3, 'issues' => ['长期无互动'], 'suggestion' => 'archived',
                    ], 'auto');
                    $processed['archived']++;
                }
            }
        }

        return $processed;
    }

    // ═══════════════════════════════════════════
    //  执行动作
    // ═══════════════════════════════════════════

    /**
     * 对内容执行自动化操作
     */
    protected function applyAction($model, string $action, array $result, string $moderatedBy = 'auto'): void
    {
        $moderationStatus = match($action) {
            'folded' => 'folded',
            'archived' => 'archived',
            'deleted' => 'deleted',
            default => 'flagged',
        };

        // 创建审核记录
        ContentModeration::updateOrCreate(
            [
                'moderatable_type' => get_class($model),
                'moderatable_id' => $model->id,
            ],
            [
                'quality_score' => $result['score'],
                'moderation_status' => $moderationStatus,
                'reason' => implode('; ', $result['issues'] ?? []),
                'action_taken' => $action,
                'details' => $result,
                'moderated_by' => 0,
                'moderated_at' => now(),
            ]
        );

        // 执行操作
        switch ($action) {
            case 'deleted':
                $model->delete();
                Log::info("[ContentQuality] 已删除 " . class_basename($model) . "#{$model->id}");
                break;

            case 'folded':
                // 广场帖子锁定
                if ($model instanceof ForumPost) {
                    $model->update(['is_locked' => true]);
                }
                Log::info("[ContentQuality] 已折叠 " . class_basename($model) . "#{$model->id}");
                break;

            case 'archived':
                if (method_exists($model, 'delete')) {
                    $model->delete();
                }
                Log::info("[ContentQuality] 已归档 " . class_basename($model) . "#{$model->id}");
                break;

            case 'flagged':
                Log::info("[ContentQuality] 已标记 " . class_basename($model) . "#{$model->id}");
                break;
        }
    }

    /**
     * 统一运行所有自动化运营任务
     */
    public function runAll(int $limit = 50, int $archiveDays = 90): array
    {
        $results = [];

        $results['messages'] = $this->scanMessages($limit);
        $results['channel_messages'] = $this->scanChannelMessages($limit);
        $results['forum_posts'] = $this->scanForumPosts($limit, $archiveDays);

        $total = 0;
        foreach ($results as $source => $counts) {
            $total += array_sum($counts);
        }

        $results['total_processed'] = $total;
        return $results;
    }

    /**
     * 获取运营统计
     */
    public function getStats(): array
    {
        return [
            'total_records' => ContentModeration::count(),
            'by_status' => ContentModeration::selectRaw('moderation_status, count(*) as total')
                ->groupBy('moderation_status')
                ->pluck('total', 'moderation_status')
                ->toArray(),
            'avg_quality' => round(ContentModeration::avg('quality_score') ?? 0, 2),
            'recent_actions' => ContentModeration::latest()->take(10)->get([
                'id', 'moderatable_type', 'action_taken', 'reason', 'quality_score', 'moderated_at',
            ]),
        ];
    }
}
