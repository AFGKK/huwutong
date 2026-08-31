<?php

namespace App\Services;

use App\Models\ForumPost;
use App\Models\UserReport;
use Illuminate\Support\Facades\Log;

/**
 * 广场帖子 AI 自动审核服务
 * 
 * 发帖时自动检查敏感内容，举报时触发 AI 审核并自动处理。
 */
class PostModerationService
{
    protected PromptFirewallService $firewall;
    protected SensitiveWordService $sensitiveWord;

    public function __construct(PromptFirewallService $firewall, SensitiveWordService $sensitiveWord)
    {
        $this->firewall = $firewall;
        $this->sensitiveWord = $sensitiveWord;
    }

    /**
     * 审核帖子内容（发帖时调用）
     *
     * @return array{passed: bool, action: string, reason: ?string}
     */
    public function inspectPost(ForumPost $post): array
    {
        $content = $post->content ?? '';

        // 1. 敏感词检测
        $sensitive = $this->sensitiveWord->check($content);
        if ($sensitive['hasSensitive']) {
            $this->lockPost($post, __('app.api.service_post_moderation.sensitive_words', ['words' => implode(', ', $sensitive['matched'])]));
            return [
                'passed' => false,
                'action' => 'locked',
                'reason' => __('app.api.service_post_moderation.rule_violation'),
            ];
        }

        // 2. 防火墙规则检测
        $firewallResult = $this->firewall->inspect($content);
        if ($firewallResult['blocked']) {
            $this->lockPost($post, $firewallResult['reason'] ?? __('app.api.service_post_moderation.security_policy'));
            return [
                'passed' => false,
                'action' => 'locked',
                'reason' => $firewallResult['reason'] ?? __('app.api.service_post_moderation.community_violation'),
            ];
        }

        // 3. 检查图片（如有）
        if (!empty($post->images)) {
            foreach ($post->images as $image) {
                // 此处可接入图片审核 API
                // 暂简单检查 URL 是否包含违规域名
                if (is_string($image) && preg_match('/\b(porn|xxx|adult|gambling)\b/i', $image)) {
                    $this->lockPost($post, __('app.api.service_post_moderation.image_violation'));
                    return [
                        'passed' => false,
                        'action' => 'locked',
                        'reason' => __('app.api.service_post_moderation.image_locked'),
                    ];
                }
            }
        }

        return [
            'passed' => true,
            'action' => 'approved',
            'reason' => null,
        ];
    }

    /**
     * 审核举报（举报时调用）
     * 自动检查被举报内容并处理
     *
     * @return array{action: string, message: string}
     */
    public function reviewReport(UserReport $report): array
    {
        if ($report->reportable_type !== ForumPost::class) {
            return ['action' => 'skipped', 'message' => __('app.api.service_post_moderation.non_forum_skip')];
        }

        /** @var ForumPost|null $post */
        $post = ForumPost::withTrashed()->find($report->reportable_id);
        if (!$post) {
            $report->update(['status' => 'resolved', 'admin_note' => '内容已删除，自动结案', 'handled_at' => now()]);
            return ['action' => 'resolved', 'message' => __('app.common.content_not_found_auto_closed')];
        }

        // 已被锁定，无需重复处理
        if ($post->is_locked) {
            $report->update(['status' => 'resolved', 'admin_note' => '内容已被锁定，自动结案', 'handled_at' => now()]);
            return ['action' => 'resolved', 'message' => __('app.common.content_locked_auto_closed')];
        }

        // 运行审核
        $result = $this->inspectPost($post);

        if ($result['passed']) {
            // 审核通过，标记为无需处理
            $report->update(['status' => 'dismissed', 'admin_note' => 'AI 审核通过，未发现违规', 'handled_at' => now()]);
            return ['action' => 'dismissed', 'message' => __('app.common.ai_moderation_passed')];
        }

        // 违规，更新举报状态
        $report->update([
            'status' => 'resolved',
            'admin_note' => 'AI 自动处理: ' . ($result['reason'] ?? '内容违规'),
            'handled_at' => now(),
        ]);

        Log::info('[PostModeration] AI 自动处理举报', [
            'report_id' => $report->id,
            'post_id' => $post->id,
            'reason' => $result['reason'],
        ]);

        return ['action' => 'locked', 'message' => $result['reason'] ?? __('app.common.auto_processed')];
    }

    /**
     * 锁定帖子
     */
    protected function lockPost(ForumPost $post, string $reason): void
    {
        $post->update([
            'is_locked' => true,
        ]);

        Log::warning('[PostModeration] 帖子被自动锁定', [
            'post_id' => $post->id,
            'user_id' => $post->user_id,
            'reason' => $reason,
        ]);
    }
}
