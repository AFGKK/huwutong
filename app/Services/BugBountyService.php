<?php

namespace App\Services;

use App\Models\BugBountyHallOfFame;
use App\Models\BugBountyReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BugBountyService
{
    /**
     * 奖励政策配置
     */
    public const POLICY = [
        'program_name' => '互物通 (HuWuTong) Bug Bounty Program',
        'scope' => [
            'api.huwutong.com',
            '*.huwutong.com',
            'SDK packages (js-sdk, python-sdk, java-sdk)',
            'Mobile API endpoints',
        ],
        'out_of_scope' => [
            'Self-XSS that cannot be escalated',
            'Social engineering attacks',
            'Denial of Service attacks',
            'Physical attacks on infrastructure',
            'Third-party services not controlled by HuWuTong (e.g. GitHub, AWS)',
            'Rate limiting bypass without demonstrated harm',
            'Missing HTTP security headers without exploitability',
        ],
        'rewards' => [
            'critical' => ['min' => 500, 'max' => 2000, 'label' => 'Critical: $500 - $2,000+'],
            'high' => ['min' => 200, 'max' => 500, 'label' => 'High: $200 - $500'],
            'medium' => ['min' => 100, 'max' => 200, 'label' => 'Medium: $100 - $200'],
            'low' => ['min' => 50, 'max' => 100, 'label' => 'Low: $50 - $100'],
            'informational' => ['min' => 0, 'max' => 0, 'label' => 'Informational: No reward (Hall of Fame only)'],
        ],
        'rules' => [
            'Provide clear steps to reproduce with PoC when possible',
            'Allow reasonable time for fix (typically 90 days for critical issues)',
            'Do not access or modify user data without permission',
            'Report vulnerabilities privately — do not disclose publicly before fix',
            'Only test on your own accounts',
            'One report per vulnerability (duplicates will be marked as such)',
            'No automated scanning without prior approval',
        ],
        'response_time' => 'We aim to acknowledge receipt within 48 hours and provide initial assessment within 5 business days.',
        'disclosure_policy' => 'We practice Coordinated Disclosure. After a fix is deployed, we grant permission for public disclosure after 30 days.',
        'legal_safe_harbor' => 'We will not pursue legal action against researchers who act in good faith and follow this policy.',
        'contact' => [
            'email' => 'security@huwutong.com',
            'pgp_fingerprint' => 'F3FA E9A7 2B8D 1C4E 9A0B 5C6D 7E8F 9A0B 1C2D 3E4F',
            'hackerone' => 'https://hackerone.com/huwutong',
            'bugcrowd' => 'https://bugcrowd.com/huwutong',
        ],
    ];

    /**
     * 分页列表漏洞报告
     */
    public function listReports(array $filters = []): LengthAwarePaginator
    {
        $query = BugBountyReport::query();

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if (!empty($filters['severity'])) {
            $query->bySeverity($filters['severity']);
        }
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reporter_email', 'like', "%{$search}%")
                    ->orWhere('reporter_name', 'like', "%{$search}%")
                    ->orWhere('vulnerability_type', 'like', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 提交漏洞报告
     */
    public function submitReport(array $data): BugBountyReport
    {
        $report = new BugBountyReport();
        $report->fill([
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_email' => $data['reporter_email'] ?? null,
            'reporter_handle' => $data['reporter_handle'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'steps_to_reproduce' => $data['steps_to_reproduce'] ?? null,
            'impact' => $data['impact'] ?? null,
            'severity' => $data['severity'] ?? 'medium',
            'vulnerability_type' => $data['vulnerability_type'] ?? null,
            'affected_endpoint' => $data['affected_endpoint'] ?? null,
            'affected_version' => $data['affected_version'] ?? null,
            'status' => 'submitted',
        ]);
        $report->save();

        return $report;
    }

    /**
     * 审核报告（审核中）
     */
    public function review(int $id, string $assignedTo): ?BugBountyReport
    {
        $report = BugBountyReport::findOrFail($id);
        $report->update([
            'status' => 'under_review',
            'assigned_to' => $assignedTo,
        ]);
        return $report;
    }

    /**
     * 确认漏洞并发放赏金
     */
    public function confirm(int $id, array $confirmData): BugBountyReport
    {
        $report = BugBountyReport::findOrFail($id);
        $report->update([
            'status' => 'confirmed',
            'severity' => $confirmData['severity'] ?? $report->severity,
            'bounty_amount' => $confirmData['bounty_amount'] ?? self::suggestedBounty($report->severity),
            'bounty_currency' => $confirmData['bounty_currency'] ?? 'USD',
            'is_public' => $confirmData['is_public'] ?? false,
            'resolution_notes' => $confirmData['resolution_notes'] ?? null,
            'confirmed_at' => now(),
        ]);

        return $report;
    }

    /**
     * 标记为已修复
     */
    public function markFixed(int $id, string $notes = null): BugBountyReport
    {
        $report = BugBountyReport::findOrFail($id);
        $report->update([
            'status' => 'fixed',
            'resolution_notes' => $notes ? ($report->resolution_notes ? $report->resolution_notes . "\n" . $notes : $notes) : $report->resolution_notes,
            'fixed_at' => now(),
        ]);

        $this->updateHallOfFame($report);

        return $report;
    }

    /**
     * 拒绝报告
     */
    public function decline(int $id, string $reason): BugBountyReport
    {
        $report = BugBountyReport::findOrFail($id);
        $report->update([
            'status' => 'declined',
            'resolution_notes' => $reason,
        ]);
        return $report;
    }

    /**
     * 标记已打款
     */
    public function markPaid(int $id): BugBountyReport
    {
        $report = BugBountyReport::findOrFail($id);
        $report->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        return $report;
    }

    /**
     * 统计数据
     */
    public function getStats(): array
    {
        return [
            'total' => BugBountyReport::count(),
            'by_severity' => collect(self::SEVERITY_ORDER)->mapWithKeys(fn($s) => [
                $s => [
                    'count' => BugBountyReport::where('severity', $s)->count(),
                    'label' => BugBountyReport::severityLabel($s),
                    'suggested_bounty' => self::suggestedBounty($s),
                ],
            ]),
            'by_status' => collect(BugBountyReport::STATUSES)->mapWithKeys(fn($s) => [
                $s => [
                    'count' => BugBountyReport::where('status', $s)->count(),
                    'label' => BugBountyReport::statusLabel($s),
                ],
            ]),
            'total_bounty_paid' => BugBountyReport::where('status', 'paid')->sum('bounty_amount'),
            'total_bounty_pending' => BugBountyReport::whereIn('status', ['confirmed', 'fixed'])->sum('bounty_amount'),
            'avg_response_time_hours' => $this->calculateAvgResponseTime(),
            'open_count' => BugBountyReport::whereIn('status', ['submitted', 'under_review'])->count(),
        ];
    }

    /**
     * 致谢墙列表
     */
    public function getHallOfFame(): Collection
    {
        $rankOrder = ['gold' => 0, 'silver' => 1, 'bronze' => 2, 'honorable' => 3];
        return BugBountyHallOfFame::ranked()->get()->sortBy(function ($item) use ($rankOrder) {
            return ($rankOrder[$item->rank] ?? 4) * 1000 + ($item->sort_order ?? 0);
        })->values();
    }

    /**
     * 维护致谢墙
     */
    protected function updateHallOfFame(BugBountyReport $report): void
    {
        if (!$report->is_public || !in_array($report->status, ['fixed', 'paid'])) {
            return;
        }

        $handle = $report->reporter_handle ?: $report->reporter_email;
        $displayName = $report->reporter_name ?: $report->reporter_handle ?: 'Anonymous';

        $entry = BugBountyHallOfFame::where('hacker_handle', $handle)->first();
        $acknowledged = $entry ? ($entry->acknowledged_reports ?? []) : [];

        if (!in_array($report->id, $acknowledged)) {
            $acknowledged[] = $report->id;
        }

        $totalBounty = BugBountyReport::whereIn('id', $acknowledged)
            ->whereIn('status', ['fixed', 'paid'])
            ->sum('bounty_amount');
        $reportCount = count($acknowledged);

        if ($entry) {
            $entry->update([
                'reports_count' => $reportCount,
                'total_bounty' => $totalBounty,
                'rank' => $this->calculateRank($totalBounty, $reportCount),
                'acknowledged_reports' => $acknowledged,
            ]);
        } else {
            BugBountyHallOfFame::create([
                'hacker_name' => $displayName,
                'hacker_handle' => $handle,
                'reports_count' => $reportCount,
                'total_bounty' => $totalBounty,
                'rank' => $this->calculateRank($totalBounty, $reportCount),
                'acknowledged_reports' => $acknowledged,
            ]);
        }
    }

    /**
     * 计算排名
     */
    protected function calculateRank(float $totalBounty, int $count): string
    {
        if ($totalBounty >= 2000 || $count >= 5) return 'gold';
        if ($totalBounty >= 500 || $count >= 3) return 'silver';
        if ($totalBounty >= 100 || $count >= 1) return 'bronze';
        return 'honorable';
    }

    /**
     * 根据严重级别获取推荐赏金
     */
    public static function suggestedBounty(string $severity): float
    {
        return BugBountyReport::suggestedBounty($severity);
    }

    /**
     * 安全政策内容（用于 security.txt）
     */
    public static function getSecurityTxt(): string
    {
        $policy = self::POLICY;
        return implode("\n", [
            '# Security Policy for HuWuTong (互物通)',
            '# https://www.huwutong.com',
            '',
            'Contact: mailto:' . $policy['contact']['email'],
            'Contact: ' . $policy['contact']['hackerone'],
            'Contact: ' . $policy['contact']['bugcrowd'],
            'Encryption: https://www.huwutong.com/pgp-key.txt',
            'Policy: https://www.huwutong.com/security-policy',
            'Preferred-Languages: en, zh-CN',
            'Hiring: https://careers.huwutong.com',
            'Acknowledgments: https://www.huwutong.com/hall-of-fame',
            'Canonical: https://www.huwutong.com/.well-known/security.txt',
            '',
            '---',
            'PGP Fingerprint: ' . $policy['contact']['pgp_fingerprint'],
            'Expires: ' . now()->addDays(365)->format('Y-m-d\TH:i:s\Z'),
            '',
        ]);
    }

    /**
     * 获取政策内容（用于政策页面）
     */
    public static function getPolicyContent(): array
    {
        $policy = self::POLICY;

        $rewardTable = '';
        foreach ($policy['rewards'] as $severity => $reward) {
            $rewardTable .= sprintf(
                "| %s | %s | %s |\n",
                BugBountyReport::severityLabel($severity),
                ucfirst($severity),
                $reward['label']
            );
        }

        return [
            'program_name' => $policy['program_name'],
            'last_updated' => now()->format('Y-m-d'),
            'scope' => $policy['scope'],
            'out_of_scope' => $policy['out_of_scope'],
            'rewards' => $policy['rewards'],
            'reward_table' => $rewardTable,
            'rules' => $policy['rules'],
            'response_time' => $policy['response_time'],
            'disclosure_policy' => $policy['disclosure_policy'],
            'legal_safe_harbor' => $policy['legal_safe_harbor'],
            'contact' => $policy['contact'],
        ];
    }

    const SEVERITY_ORDER = ['critical', 'high', 'medium', 'low', 'informational'];

    /**
     * 计算平均响应时间
     */
    protected function calculateAvgResponseTime(): float
    {
        $total = 0;
        $count = 0;
        BugBountyReport::whereNotNull('confirmed_at')->chunk(100, function ($reports) use (&$total, &$count) {
            foreach ($reports as $report) {
                $total += $report->created_at->diffInHours($report->confirmed_at);
                $count++;
            }
        });
        return $count > 0 ? round($total / $count, 1) : 0;
    }
}
