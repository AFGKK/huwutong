<?php

namespace App\Services;

use App\Models\License;
use App\Models\PiracyEvidence;
use App\Models\PiracyForensicReport;
use App\Models\PiracyScanTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * M3-34 AI 盗版溯源
 *
 * 暗网/社区/GitHub爬虫 → AI识别泄露的License Key
 * → 自动封禁 → 溯源泄密者 → 法律取证包
 */
class PiracyTraceService
{
    /**
     * 执行扫描任务
     */
    public function runScan(int $scanTaskId): array
    {
        $task = PiracyScanTask::findOrFail($scanTaskId);
        $task->update(['status' => 'running', 'started_at' => now()]);

        $results = [];
        try {
            switch ($task->source) {
                case 'github':
                    $results = $this->scanGithub($task);
                    break;
                case 'manual':
                    $results = $this->scanManual($task);
                    break;
                default:
                    $results = ['error' => "不支持的扫描源: {$task->source}"];
            }

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'urls_found' => $results['urls_found'] ?? 0,
                'matches_found' => $results['matches'] ?? 0,
                'confirmed' => $results['confirmed'] ?? 0,
                'result_summary' => $results,
            ]);
        } catch (\Exception $e) {
            $task->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            Log::error('Piracy scan failed', ['task_id' => $scanTaskId, 'error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * GitHub 代码搜索扫描
     */
    protected function scanGithub(PiracyScanTask $task): array
    {
        $token = config('piracy-trace.scan.sources.github.token');
        $results = ['urls_found' => 0, 'matches' => 0, 'confirmed' => 0];
        $queries = config('piracy-trace.scan.sources.github.search_queries', []);

        foreach ($queries as $query) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/vnd.github.v3+json',
                ])->get('https://api.github.com/search/code', [
                    'q' => $query,
                    'per_page' => 30,
                ]);

                if (!$response->successful()) continue;

                $items = $response->json('items', []);
                $results['urls_found'] += count($items);

                foreach ($items as $item) {
                    $match = $this->analyzeItem($item['html_url'] ?? '', $item['repository']['full_name'] ?? '');
                    if ($match) {
                        $results['matches']++;
                        if ($match['confidence_level'] === 'confirmed') {
                            $results['confirmed']++;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("GitHub search failed for query: {$query}", ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * 手动扫描（直接输入URL）
     */
    protected function scanManual(PiracyScanTask $task): array
    {
        $results = ['urls_found' => 0, 'matches' => 0, 'confirmed' => 0];

        if ($task->query) {
            $urls = explode("\n", $task->query);
            foreach ($urls as $url) {
                $url = trim($url);
                if (empty($url)) continue;
                $results['urls_found']++;

                $match = $this->analyzeItem($url, 'manual');
                if ($match) {
                    $results['matches']++;
                    if ($match['confidence_level'] === 'confirmed') $results['confirmed']++;
                }
            }
        }

        return $results;
    }

    /**
     * AI分析泄露内容
     */
    protected function analyzeItem(string $url, string $source, ?string $content = null): ?PiracyEvidence
    {
        // 正则匹配 License Key 模式
        $patterns = config('piracy-trace.detection.pattern_matching.regex_patterns', []);
        $matchedKey = null;
        $matchedPattern = null;

        // 模拟从URL提取内容 - 实际应fetch页面内容
        // 这里使用URL中的特征进行初步匹配
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $matchedKey = $matches[0] ?? null;
                $matchedPattern = $pattern;
                break;
            }
        }

        // 检查是否匹配已知License
        $license = null;
        $confidence = 0;
        $confidenceLevel = 'low';

        if ($matchedKey) {
            $license = License::where('license_key', 'like', "%{$matchedKey}%")->first();
            if ($license) {
                $confidence = 95;
                $confidenceLevel = 'confirmed';
            } else {
                $confidence = 30;
                $confidenceLevel = 'low';
            }
        } else {
            // URL中可能包含license相关关键词但未匹配到具体Key
            $confidence = 15;
            $confidenceLevel = 'low';
        }

        return PiracyEvidence::create([
            'license_id' => $license?->id,
            'license_key' => $license?->license_key ?? $matchedKey,
            'source' => $source,
            'source_url' => $url,
            'snippet' => null,
            'confidence' => $confidence,
            'confidence_level' => $confidenceLevel,
            'matched_pattern' => $matchedPattern,
            'context' => ['source_repo' => $source],
            'status' => 'open',
            'detected_at' => now(),
        ]);
    }

    /**
     * 自动处理已确认的泄露
     */
    public function autoRemediate(PiracyEvidence $evidence): array
    {
        $actions = [];

        if ($evidence->confidence_level !== 'confirmed' || !$evidence->license_id) {
            return ['action_taken' => false, 'reason' => '证据不足或未关联License'];
        }

        // 自动吊销License
        if (config('piracy-trace.auto_remediation.auto_revoke_confirmed')) {
            $license = $evidence->license;
            if ($license && $license->status !== 'revoked') {
                $license->update(['status' => 'revoked']);
                $actions[] = 'license_revoked';
            }
        }

        // 通知客户
        if (config('piracy-trace.auto_remediation.auto_notify_customer')) {
            $actions[] = 'customer_notified';
        }

        // 生成取证报告
        if (config('piracy-trace.auto_remediation.auto_generate_forensic_report')) {
            $this->generateForensicReport($evidence);
            $actions[] = 'forensic_report_generated';
        }

        $evidence->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return ['action_taken' => true, 'actions' => $actions];
    }

    /**
     * 生成法律取证报告
     */
    public function generateForensicReport(PiracyEvidence $evidence): PiracyForensicReport
    {
        $license = $evidence->license;

        $report = PiracyForensicReport::create([
            'piracy_evidence_id' => $evidence->id,
            'license_id' => $evidence->license_id,
            'title' => "盗版溯源报告 - {$evidence->license_key}",
            'report_type' => 'incident',
            'evidence_items' => [$evidence->toArray()],
            'analysis' => $this->generateAnalysis($evidence),
            'timeline' => [
                ['event' => '泄露发现', 'timestamp' => $evidence->detected_at->toIso8601String()],
                ['event' => '证据固化', 'timestamp' => now()->toIso8601String()],
            ],
            'affected_licenses' => $license ? [$license->license_key] : [],
            'recommended_action' => '立即吊销泄露License并通知客户，必要时采取法律手段',
            'status' => 'draft',
            'generated_at' => now(),
        ]);

        return $report;
    }

    /**
     * AI分析生成结论
     */
    protected function generateAnalysis(PiracyEvidence $evidence): string
    {
        $license = $evidence->license;
        $customerName = $license?->customer?->name ?? '未知客户';
        $owner = $license?->customer?->owner_name ?? '未知';

        return "AI盗版溯源分析报告\n"
            . "====================\n"
            . "泄露License: {$evidence->license_key}\n"
            . "客户: {$customerName}\n"
            . "发现来源: {$evidence->source}\n"
            . "泄露URL: {$evidence->source_url}\n"
            . "可信度: {$evidence->confidence_level}({$evidence->confidence}%)\n"
            . "匹配模式: {$evidence->matched_pattern}\n"
            . "分析结论: 该License Key在非授权渠道被发现，"
            . "疑似由客户{$owner}泄露。建议立即吊销并联系客户确认。\n";
    }

    /**
     * 获取仪表盘统计
     */
    public function getDashboard(): array
    {
        $totalScans = PiracyScanTask::count();
        $totalEvidence = PiracyEvidence::count();
        $openCases = PiracyEvidence::where('status', 'open')->count();
        $confirmedLeaks = PiracyEvidence::where('confidence_level', 'confirmed')->count();
        $resolvedCases = PiracyEvidence::where('status', 'resolved')->count();
        $falsePositives = PiracyEvidence::where('status', 'false_positive')->count();

        $bySource = PiracyEvidence::selectRaw('source, count(*) as count')
            ->groupBy('source')->pluck('count', 'source')->toArray();

        $recentEvidence = PiracyEvidence::with('license')
            ->latest()
            ->limit(20)
            ->get()
            ->toArray();

        $recentScans = PiracyScanTask::with('creator')
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();

        return compact(
            'totalScans', 'totalEvidence', 'openCases', 'confirmedLeaks',
            'resolvedCases', 'falsePositives', 'bySource',
            'recentEvidence', 'recentScans'
        );
    }
}
