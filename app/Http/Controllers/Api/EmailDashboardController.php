<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailDashboardController extends Controller
{
    /**
     * 邮件投递面板概览
     *
     * GET /api/admin/email-dashboard/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        // 基础漏斗
        $funnel = $this->getFunnelStats($tenantId, $startDate, $endDate);

        // 按日统计（近30天）
        $daily = $this->getDailyStats($tenantId, 30);

        // 按模板统计
        $byTemplate = $this->getTemplateStats($tenantId, $startDate, $endDate);

        // 退信分析
        $bounceAnalysis = $this->getBounceAnalysis($tenantId);

        // 发送量趋势（周同比）
        $trend = $this->getWeekTrend($tenantId);

        // 按时段发送分布（近7天）
        $hourly = $this->getHourlyDistribution($tenantId);

        return ApiResponse::success([
            'funnel' => $funnel,
            'daily' => $daily,
            'by_template' => $byTemplate,
            'bounce_analysis' => $bounceAnalysis,
            'trend' => $trend,
            'hourly' => $hourly,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ]);
    }

    /**
     * 邮件发送明细
     *
     * GET /api/admin/email-dashboard/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = EmailLog::where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('to_email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('template_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        if ($request->filled('filter.template_code')) {
            $query->where('template_code', $request->input('filter.template_code'));
        }

        if ($request->filled('date_range')) {
            [$start, $end] = explode(',', $request->input('date_range'));
            $query->whereBetween('created_at', [$start, $end . ' 23:59:59']);
        }

        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');
        $allowedSorts = ['to_email', 'subject', 'status', 'created_at', 'sent_at', 'opened_at'];
        if (in_array($field, $allowedSorts)) {
            $query->orderBy($field, $direction);
        } else {
            $query->latest();
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 邮件详情
     *
     * GET /api/admin/email-dashboard/logs/{id}
     */
    public function logDetail(int $id, Request $request): JsonResponse
    {
        $log = EmailLog::where('tenant_id', $request->user()->tenant_id)
            ->with(['notifiable'])
            ->find($id);

        if (! $log) {
            return ApiResponse::error('NOT_FOUND', '邮件记录不存在', 404);
        }

        return ApiResponse::success($log);
    }

    /**
     * 模板详情下钻
     *
     * GET /api/admin/email-dashboard/templates/{templateCode}
     */
    public function templateDetail(string $templateCode, Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $query = EmailLog::where('tenant_id', $tenantId)
            ->where('template_code', $templateCode)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59']);

        $total = (clone $query)->count();
        $delivered = (clone $query)->whereNotNull('delivered_at')->count();
        $opened = (clone $query)->whereNotNull('opened_at')->count();
        $clicked = (clone $query)->whereNotNull('clicked_at')->count();
        $bounced = (clone $query)->where('status', 'bounced')->count();

        $daily = (clone $query)->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return ApiResponse::success([
            'template_code' => $templateCode,
            'funnel' => [
                'total_sent' => $total,
                'delivered' => $delivered,
                'opened' => $opened,
                'clicked' => $clicked,
                'bounced' => $bounced,
                'delivery_rate' => $total > 0 ? round($delivered / $total * 100, 1) : 0,
                'open_rate' => $delivered > 0 ? round($opened / $delivered * 100, 1) : 0,
                'click_rate' => $opened > 0 ? round($clicked / $opened * 100, 1) : 0,
            ],
            'daily' => $daily,
        ]);
    }

    protected function getFunnelStats(?int $tenantId, string $startDate, string $endDate): array
    {
        $query = EmailLog::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59']);

        $total = (clone $query)->count();
        $queued = (clone $query)->where('status', 'queued')->count();
        $sent = (clone $query)->whereNotNull('sent_at')->count();
        $delivered = (clone $query)->whereNotNull('delivered_at')->count();
        $opened = (clone $query)->whereNotNull('opened_at')->count();
        $clicked = (clone $query)->whereNotNull('clicked_at')->count();
        $bounced = (clone $query)->where('status', 'bounced')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        return [
            'total_sent' => $total,
            'queued' => $queued,
            'sent' => $sent,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'failed' => $failed,
            'delivery_rate' => $total > 0 ? round($delivered / $total * 100, 1) : 0,
            'open_rate' => $delivered > 0 ? round($opened / $delivered * 100, 1) : 0,
            'click_rate' => $opened > 0 ? round($clicked / $opened * 100, 1) : 0,
            'bounce_rate' => $total > 0 ? round($bounced / $total * 100, 1) : 0,
        ];
    }

    protected function getDailyStats(?int $tenantId, int $days = 30): array
    {
        $query = EmailLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays($days));

        return $query->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    protected function getTemplateStats(?int $tenantId, string $startDate, string $endDate): array
    {
        $query = EmailLog::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59']);

        return $query->selectRaw("
                template_code,
                COUNT(*) as total_sent,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced
            ")
            ->groupBy('template_code')
            ->orderByDesc('total_sent')
            ->limit(20)
            ->get()
            ->toArray();
    }

    protected function getBounceAnalysis(?int $tenantId): array
    {
        $total = EmailLog::where('tenant_id', $tenantId)->count();
        $bounced = EmailLog::where('tenant_id', $tenantId)->where('status', 'bounced');
        $totalBounced = (clone $bounced)->count();

        $byReason = (clone $bounced)
            ->whereNotNull('bounce_reason')
            ->selectRaw("
                bounce_reason,
                COUNT(*) as count,
                MAX(created_at) as last_bounced_at
            ")
            ->groupBy('bounce_reason')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        // 退信高发域名
        $byDomain = (clone $bounced)
            ->selectRaw("
                SUBSTRING_INDEX(to_email, '@', -1) as domain,
                COUNT(*) as count
            ")
            ->groupBy('domain')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'total' => $totalBounced,
            'rate' => $total > 0 ? round($totalBounced / $total * 100, 2) : 0,
            'by_reason' => $byReason,
            'by_domain' => $byDomain,
        ];
    }

    protected function getWeekTrend(?int $tenantId): array
    {
        $thisWeek = EmailLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $lastWeek = EmailLog::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->count();

        $change = $lastWeek > 0 ? round(($thisWeek - $lastWeek) / $lastWeek * 100, 1) : 0;

        return [
            'this_week' => $thisWeek,
            'last_week' => $lastWeek,
            'change_percent' => $change,
            'direction' => $change >= 0 ? 'up' : 'down',
        ];
    }

    protected function getHourlyDistribution(?int $tenantId): array
    {
        $query = EmailLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7));

        return $query->selectRaw("
                HOUR(created_at) as hour,
                COUNT(*) as total
            ")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }
}
