<?php

namespace App\Services;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceAppReview;
use App\Services\SensitiveWordService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceContentSecurityService
{
    protected SensitiveWordService $sensitiveWordService;

    public function __construct(SensitiveWordService $sensitiveWordService)
    {
        $this->sensitiveWordService = $sensitiveWordService;
    }

    // ─── 扫描内容 ───

    /**
     * 扫描应用描述和名称
     */
    public function scanApp(MarketplaceApp $app): array
    {
        $violations = [];

        $fields = [
            'name' => $app->name,
            'short_description' => $app->short_description,
            'description' => $app->description,
        ];

        foreach ($fields as $field => $text) {
            if (empty($text)) continue;
            $result = $this->sensitiveWordService->check($text);
            if (!empty($result)) {
                $violations[] = [
                    'field' => $field,
                    'field_label' => $this->fieldLabel($field),
                    'words' => $result,
                    'severity' => $this->assessSeverity($result),
                ];
            }
        }

        return $violations;
    }

    /**
     * 扫描用户评价
     */
    public function scanReview(MarketplaceAppReview $review): array
    {
        $violations = [];

        if (!empty($review->content)) {
            $result = $this->sensitiveWordService->check($review->content);
            if (!empty($result)) {
                $violations[] = [
                    'field' => 'review_content',
                    'field_label' => '评价内容',
                    'words' => $result,
                    'severity' => $this->assessSeverity($result),
                ];
            }
        }

        return $violations;
    }

    /**
     * 批量扫描所有已发布应用
     */
    public function scanAllApps(?callable $progress = null): array
    {
        $apps = MarketplaceApp::whereIn('status', ['published', 'pending_review'])->get();
        $results = [];

        foreach ($apps as $app) {
            $violations = $this->scanApp($app);
            if (!empty($violations)) {
                $results[] = [
                    'app_id' => $app->id,
                    'app_name' => $app->name,
                    'slug' => $app->slug,
                    'violations' => $violations,
                    'total' => count($violations),
                    'max_severity' => max(array_column($violations, 'severity')),
                ];
            }
            if ($progress) $progress();
        }

        // 按严重程度排序
        usort($results, fn($a, $b) => $b['max_severity'] <=> $a['max_severity']);

        return $results;
    }

    /**
     * 批量扫描所有待审核评价
     */
    public function scanAllPendingReviews(): array
    {
        $reviews = MarketplaceAppReview::where('status', 'pending')->with('app:id,name')->get();
        $results = [];

        foreach ($reviews as $review) {
            $violations = $this->scanReview($review);
            if (!empty($violations)) {
                $results[] = [
                    'review_id' => $review->id,
                    'app_name' => $review->app->name ?? '-',
                    'user_name' => $review->user->name ?? '-',
                    'rating' => $review->rating,
                    'violations' => $violations,
                    'total' => count($violations),
                    'max_severity' => max(array_column($violations, 'severity')),
                ];
            }
        }

        usort($results, fn($a, $b) => $b['max_severity'] <=> $a['max_severity']);
        return $results;
    }

    // ─── 严重程度评估 ───

    protected function assessSeverity(array $words): int
    {
        $highRisk = ['adult', 'porn', 'violence', 'gambling', 'drug', 'weapon', 'terror'];
        $maxScore = 1;

        foreach ($words as $word) {
            $text = is_string($word) ? $word : ($word['word'] ?? '');
            foreach ($highRisk as $risk) {
                if (stripos($text, $risk) !== false) {
                    $maxScore = max($maxScore, 3);
                }
            }
        }

        return $maxScore; // 1=low, 2=medium, 3=high
    }

    protected function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => '应用名称',
            'short_description' => '应用简介',
            'description' => '应用描述',
            'review_content' => '评价内容',
            default => $field,
        };
    }

    // ─── 安全统计 ───

    public function getSecurityStats(): array
    {
        $totalApps = MarketplaceApp::whereIn('status', ['published', 'pending_review'])->count();
        $totalReviews = MarketplaceAppReview::where('status', 'pending')->count();

        // 扫描已发布应用
        $flaggedApps = 0;
        MarketplaceApp::whereIn('status', ['published', 'pending_review'])->chunk(50, function ($apps) use (&$flaggedApps) {
            foreach ($apps as $app) {
                $violations = $this->scanApp($app);
                if (!empty($violations)) $flaggedApps++;
            }
        });

        return [
            'total_apps' => $totalApps,
            'flagged_apps' => $flaggedApps,
            'pending_reviews' => $totalReviews,
            'clean_apps' => $totalApps - $flaggedApps,
            'last_scan_at' => now()->toDateTimeString(),
        ];
    }
}
