<?php

namespace App\Listeners;

use App\Events\OaSubmissionCreated;
use App\Models\AiFriendProfile;
use App\Models\OaSubmission;
use App\Services\AiFriendOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AiReviewSubmission implements ShouldQueue
{
    public function __construct(private AiFriendOrchestrator $orchestrator) {}

    public function handle(OaSubmissionCreated $event): void
    {
        $submission = $event->submission;

        $reviewers = AiFriendProfile::where('category', 'reviewer')
            ->with('llmConfig')
            ->whereHas('llmConfig')
            ->get();

        foreach ($reviewers as $reviewer) {
            try {
                $result = $this->orchestrator->forFriend($reviewer)->generate(null,
                    "你是一个文章审核员。请审核以下投稿，从内容质量、原创性、合规性三个维度评分（1-10分），并决定「通过」或「拒绝」。只返回 JSON：{\"score\":数字,\"decision\":\"通过/拒绝\",\"reason\":\"原因\"}\n\n标题：{$submission->title}\n\n内容：{$submission->content}");

                $reply = $result['content'] ?? '';
                // 尝试解析 JSON
                if (preg_match('/\{.*\}/s', $reply, $m)) {
                    $decoded = json_decode($m[0], true);
                    if ($decoded && isset($decoded['decision'])) {
                        if ($decoded['decision'] === '通过' && ($decoded['score'] ?? 0) >= 6) {
                            $submission->update(['status' => 'approved', 'reviewed_at' => now()]);
                            Log::info("[AiReviewer] 自动通过投稿 #{$submission->id}, 评分: {$decoded['score']}");
                        } else {
                            $reason = $decoded['reason'] ?? 'AI 审核未通过';
                            $submission->update(['status' => 'rejected', 'reject_reason' => $reason, 'reviewed_at' => now()]);
                            Log::info("[AiReviewer] 自动拒绝投稿 #{$submission->id}: {$reason}");
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error("[AiReviewer] 审核失败: " . $e->getMessage());
            }
        }
    }
}
