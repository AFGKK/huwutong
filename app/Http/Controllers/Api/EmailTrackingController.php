<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTrackingController extends Controller
{
    /**
     * 邮件追踪概览
     */
    public function overview(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id;

        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $funnel = EmailLog::funnelStats($tenantId);
        $dailyStats = EmailLog::dailyStats($tenantId, 30);
        $byTemplate = EmailLog::statsByTemplate($tenantId, $startDate, $endDate);

        return ApiResponse::success([
            'funnel' => $funnel,
            'daily' => $dailyStats,
            'by_template' => $byTemplate,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ]);
    }

    /**
     * 邮件发送明细（带追踪状态）
     */
    public function logs(Request $request): JsonResponse
    {
        $query = EmailLog::where('tenant_id', $request->user()->tenant_id);

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

        $perPage = min((int) $request->input('per_page', 20), 100);

        $sortField = $request->input('sort', '-created_at');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');
        $allowedSorts = ['to_email', 'status', 'created_at', 'sent_at', 'opened_at'];
        if (in_array($field, $allowedSorts)) {
            $query->orderBy($field, $direction);
        } else {
            $query->latest();
        }

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 按模板下钻统计
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

    /**
     * 退信原因归类统计
     */
    public function bounceStats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $bounces = EmailLog::where('tenant_id', $tenantId)
            ->where('status', 'bounced')
            ->whereNotNull('bounce_reason')
            ->selectRaw("
                bounce_reason,
                COUNT(*) as count,
                MAX(created_at) as last_bounced_at
            ")
            ->groupBy('bounce_reason')
            ->orderByDesc('count')
            ->get();

        $bounceRate = EmailLog::funnelStats($tenantId);

        return ApiResponse::success([
            'bounce_categories' => $bounces,
            'bounce_rate' => $bounceRate['bounce_rate'] ?? 0,
            'total_bounced' => $bounceRate['bounced'] ?? 0,
        ]);
    }

    /**
     * 曝光打点（跟踪像素 API - 公开，嵌入邮件 HTML）
     */
    public function trackingPixel(Request $request): \Illuminate\Http\Response
    {
        $trackingId = $request->query('id');

        if ($trackingId) {
            $log = EmailLog::where('tracking_id', $trackingId)->first();
            if ($log) {
                $log->update([
                    'opened_at' => $log->opened_at ?? now(),
                    'opened_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        // 返回 1x1 透明 GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * 链接点击追踪（重定向）
     */
    public function clickRedirect(Request $request): \Illuminate\Http\RedirectResponse
    {
        $trackingId = $request->query('id');
        $url = $request->query('url');

        if ($trackingId && $url) {
            $log = EmailLog::where('tracking_id', $trackingId)->first();
            if ($log) {
                $log->update([
                    'clicked_at' => $log->clicked_at ?? now(),
                    'click_url' => $url,
                ]);
            }
        }

        return redirect($url ?? url('/'));
    }
}
