<?php

namespace App\Services;

use App\Models\NpsResponse;
use App\Models\NpsSummary;
use App\Models\NpsSurvey;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * NPS 客户满意度调查服务 (M2-59)
 */
class NpsSurveyService
{
    /**
     * 获取仪表盘数据
     */
    public function getDashboard(string $startDate, string $endDate): array
    {
        $responses = NpsResponse::whereBetween('created_at', [$startDate, $endDate])->get();
        $total = $responses->count();

        $promoters = $responses->whereBetween('score', [9, 10])->count();
        $passives = $responses->whereBetween('score', [7, 8])->count();
        $detractors = $responses->whereBetween('score', [0, 6])->count();

        $npsScore = $total > 0
            ? round(($promoters - $detractors) / $total * 100, 1)
            : 0;

        // 调查发送统计
        $surveysSent = NpsSurvey::whereBetween('sent_at', [$startDate, $endDate])->count();
        $surveysCompleted = NpsSurvey::whereBetween('completed_at', [$startDate, $endDate])
            ->where('status', 'completed')->count();
        $responseRate = $surveysSent > 0
            ? round($surveysCompleted / $surveysSent * 100, 1)
            : 0;

        // 趋势数据（按天）
        $trend = NpsResponse::selectRaw('DATE(created_at) as date, COUNT(*) as total,
                SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) as promoters,
                SUM(CASE WHEN score BETWEEN 7 AND 8 THEN 1 ELSE 0 END) as passives,
                SUM(CASE WHEN score <= 6 THEN 1 ELSE 0 END) as detractors')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                $t = $row->total;
                return [
                    'date' => $row->date,
                    'total' => $t,
                    'promoters' => (int) $row->promoters,
                    'passives' => (int) $row->passives,
                    'detractors' => (int) $row->detractors,
                    'score' => $t > 0
                        ? round(((int) $row->promoters - (int) $row->detractors) / $t * 100, 1)
                        : 0,
                ];
            });

        // 最近反馈
        $recentFeedback = NpsResponse::with('survey')
            ->whereNotNull('feedback')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'score' => $r->score,
                    'category' => $r->category,
                    'feedback' => $r->feedback,
                    'best_feature' => $r->best_feature,
                    'improvement' => $r->improvement,
                    'created_at' => $r->created_at->toIso8601String(),
                ];
            });

        return [
            'stats' => [
                'total_responses' => $total,
                'promoters' => $promoters,
                'passives' => $passives,
                'detractors' => $detractors,
                'nps_score' => $npsScore,
                'surveys_sent' => $surveysSent,
                'surveys_completed' => $surveysCompleted,
                'response_rate' => $responseRate,
                'target_score' => config('nps-survey.dashboard.target_score', 50),
            ],
            'trend' => $trend,
            'recent_feedback' => $recentFeedback,
        ];
    }

    /**
     * 发送 NPS 调查
     */
    public function sendSurvey(int $userId, string $channel = 'email'): ?NpsSurvey
    {
        // 检查最小间隔
        $lastSurvey = NpsSurvey::where('user_id', $userId)
            ->where('status', 'completed')
            ->latest()
            ->first();

        $minInterval = config('nps-survey.trigger.min_interval_days', 90);
        if ($lastSurvey && $lastSurvey->completed_at->diffInDays(now()) < $minInterval) {
            return null;
        }

        $expiryDays = config('nps-survey.trigger.expiry_days', 14);

        $survey = NpsSurvey::create([
            'user_id' => $userId,
            'status' => 'sent',
            'channel' => $channel,
            'sent_at' => now(),
            'expires_at' => now()->addDays($expiryDays),
        ]);

        // 异步发送 NPS 调查（由 Job 处理邮件/通知）
        \App\Jobs\SendNpsSurveyJob::dispatch($userId, $channel)
            ->onQueue('nps');

        return $survey;
    }

    /**
     * 提交 NPS 评分
     */
    public function submitResponse(int $surveyId, array $data): NpsResponse
    {
        $survey = NpsSurvey::findOrFail($surveyId);

        if ($survey->status === 'completed') {
            throw new \RuntimeException(__("app.nps_survey.msg_08f33123"));
        }

        $score = (int) ($data['score'] ?? 0);
        $category = $this->categorizeScore($score);

        $response = NpsResponse::create([
            'survey_id' => $surveyId,
            'user_id' => $survey->user_id,
            'score' => $score,
            'feedback' => $data['feedback'] ?? null,
            'best_feature' => $data['best_feature'] ?? null,
            'improvement' => $data['improvement'] ?? null,
            'category' => $category,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);

        $survey->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // 贬损者自动创建跟单
        if (
            config('nps-survey.detractor_followup.enabled', true)
            && $score <= config('nps-survey.detractor_followup.auto_ticket_threshold', 6)
        ) {
            $this->createDetractorTicket($survey->user_id, $response);
        }

        return $response;
    }

    /**
     * 贬损者自动创建工单
     */
    protected function createDetractorTicket(int $userId, NpsResponse $response): void
    {
        try {
            // 查找或创建 Ticket 服务
            $ticketService = app(\App\Services\TicketService::class);

            $user = User::find($userId);
            if (!$user) return;

            $ticketService->createTicket([
                'user_id' => $userId,
                'subject' => "NPS 贬损者跟进 - 评分 {$response->score}/10",
                'description' => "客户反馈：{$response->feedback}\n"
                    . "最喜欢的功能：{$response->best_feature}\n"
                    . "改进建议：{$response->improvement}",
                'priority' => config('nps-survey.detractor_followup.ticket_priority', 'high'),
                'category' => config('nps-survey.detractor_followup.ticket_category', 'feedback'),
                'source' => 'nps_survey',
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to create detractor ticket for user {$userId}: {$e->getMessage()}");
        }
    }

    /**
     * 获取 NPS 报告
     */
    public function getReport(string $startDate, string $endDate): array
    {
        $responses = NpsResponse::with('survey')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $total = $responses->count();

        // 按类别分组
        $byCategory = [
            'promoters' => $responses->where('category', 'promoter')->values(),
            'passives' => $responses->where('category', 'passive')->values(),
            'detractors' => $responses->where('category', 'detractor')->values(),
        ];

        // 评分分布
        $scoreDistribution = collect(range(0, 10))->mapWithKeys(function ($score) use ($responses) {
            return [$score => $responses->where('score', $score)->count()];
        });

        // 按渠道统计
        $byChannel = NpsSurvey::whereBetween('sent_at', [$startDate, $endDate])
            ->get()
            ->groupBy('channel')
            ->map(function ($group) {
                $total = $group->count();
                $completed = $group->where('status', 'completed')->count();
                return [
                    'total' => $total,
                    'completed' => $completed,
                    'rate' => $total > 0 ? round($completed / $total * 100, 1) : 0,
                ];
            });

        return [
            'total_responses' => $total,
            'score_distribution' => $scoreDistribution,
            'by_category' => [
                'promoters' => ['count' => $byCategory['promoters']->count(), 'pct' => $total > 0 ? round($byCategory['promoters']->count() / $total * 100, 1) : 0],
                'passives' => ['count' => $byCategory['passives']->count(), 'pct' => $total > 0 ? round($byCategory['passives']->count() / $total * 100, 1) : 0],
                'detractors' => ['count' => $byCategory['detractors']->count(), 'pct' => $total > 0 ? round($byCategory['detractors']->count() / $total * 100, 1) : 0],
            ],
            'by_channel' => $byChannel,
            'nps_score' => $total > 0
                ? round(($byCategory['promoters']->count() - $byCategory['detractors']->count()) / $total * 100, 1)
                : 0,
        ];
    }

    /**
     * 生成每日快照
     */
    public function generateDailySnapshot(): NpsSummary
    {
        $today = now()->startOfDay();
        $responses = NpsResponse::whereDate('created_at', $today)->get();
        $total = $responses->count();

        $promoters = $responses->whereBetween('score', [9, 10])->count();
        $passives = $responses->whereBetween('score', [7, 8])->count();
        $detractors = $responses->whereBetween('score', [0, 6])->count();
        $npsScore = $total > 0 ? round(($promoters - $detractors) / $total * 100, 1) : 0;

        $surveysSent = NpsSurvey::whereDate('sent_at', $today)->count();
        $responseRate = $surveysSent > 0 ? round($total / $surveysSent * 1000) : 0;

        return NpsSummary::updateOrCreate(
            ['snapshot_date' => $today->toDateString()],
            [
                'total_responses' => $total,
                'promoters' => $promoters,
                'passives' => $passives,
                'detractors' => $detractors,
                'nps_score' => $npsScore,
                'response_rate' => $responseRate,
            ]
        );
    }

    /**
     * 获取历史快照趋势
     */
    public function getSnapshotTrend(int $days = 90): array
    {
        return NpsSummary::recent($days)
            ->orderBy('snapshot_date')
            ->get()
            ->toArray();
    }

    /**
     * 获取调查列表
     */
    public function getSurveys(array $filters = []): array
    {
        $query = NpsSurvey::with(['user:id,name,email', 'response']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 获取反馈列表
     */
    public function getResponses(array $filters = []): array
    {
        $query = NpsResponse::with(['survey', 'user:id,name,email']);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['score_min'])) {
            $query->where('score', '>=', (int) $filters['score_min']);
        }
        if (!empty($filters['score_max'])) {
            $query->where('score', '<=', (int) $filters['score_max']);
        }
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * 根据分数分类
     */
    public function categorizeScore(int $score): string
    {
        if ($score >= 9) return 'promoter';
        if ($score >= 7) return 'passive';
        return 'detractor';
    }

    /**
     * 获取可发送调查的用户
     */
    public function getEligibleUsers(int $limit = 50): array
    {
        $daysAfterReg = config('nps-survey.trigger.days_after_registration', 7);
        $minInterval = config('nps-survey.trigger.min_interval_days', 90);

        $users = User::where('created_at', '<=', now()->subDays($daysAfterReg))
            ->whereDoesntHave('npsSurveys', function ($q) use ($minInterval) {
                $q->where('created_at', '>=', now()->subDays($minInterval));
            })
            ->limit($limit)
            ->get(['id', 'name', 'email']);

        return $users->toArray();
    }
}
