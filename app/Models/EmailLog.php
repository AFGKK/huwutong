<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class EmailLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'notifiable_type', 'notifiable_id',
        'template_code', 'from_email', 'to_email',
        'subject', 'status', 'error_message',
        'sent_at', 'delivered_at', 'opened_at', 'clicked_at',
        'bounced_at', 'bounce_reason',
        'tracking_id', 'opened_ip', 'user_agent', 'click_url',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 生成唯一追踪 ID
     */
    public static function generateTrackingId(): string
    {
        return Str::random(32) . '.' . time();
    }

    /**
     * 按模板统计
     */
    public static function statsByTemplate(?int $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = self::query();
        if ($tenantId) $query->where('tenant_id', $tenantId);
        if ($startDate) $query->where('created_at', '>=', $startDate);
        if ($endDate) $query->where('created_at', '<=', $endDate . ' 23:59:59');

        return $query->selectRaw("
                template_code,
                COUNT(*) as total_sent,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            ")
            ->groupBy('template_code')
            ->orderByDesc('total_sent')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * 按天统计
     */
    public static function dailyStats(?int $tenantId = null, int $days = 30): array
    {
        $query = self::query();
        if ($tenantId) $query->where('tenant_id', $tenantId);
        $query->where('created_at', '>=', now()->subDays($days));

        return $query->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as total,
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

    /**
     * 总漏斗统计
     */
    public static function funnelStats(?int $tenantId = null): array
    {
        $query = self::query();
        if ($tenantId) $query->where('tenant_id', $tenantId);

        $total = (clone $query)->count();
        $delivered = (clone $query)->whereNotNull('delivered_at')->count();
        $opened = (clone $query)->whereNotNull('opened_at')->count();
        $clicked = (clone $query)->whereNotNull('clicked_at')->count();
        $bounced = (clone $query)->where('status', 'bounced')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        return [
            'total_sent' => $total,
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
}
