<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperBugBountyReport
 */
class BugBountyReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'reporter_name', 'reporter_email', 'reporter_handle',
        'title', 'description', 'steps_to_reproduce', 'impact',
        'severity', 'vulnerability_type', 'affected_endpoint', 'affected_version',
        'bounty_amount', 'bounty_currency',
        'status', 'assigned_to', 'resolution_notes', 'is_public',
        'confirmed_at', 'fixed_at', 'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'bounty_amount' => 'decimal:2',
            'is_public' => 'boolean',
            'confirmed_at' => 'datetime',
            'fixed_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    const SEVERITIES = ['critical', 'high', 'medium', 'low', 'informational'];
    const STATUSES = ['submitted', 'under_review', 'confirmed', 'fixed', 'declined', 'paid'];

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true)->whereIn('status', ['fixed', 'paid']);
    }

    /**
     * 根据严重级别获取推荐赏金
     */
    public static function suggestedBounty(string $severity): float
    {
        return match ($severity) {
            'critical' => 1000,
            'high' => 500,
            'medium' => 200,
            'low' => 50,
            default => 0,
        };
    }

    /**
     * 严重级别标签（中文）
     */
    public static function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => '严重',
            'high' => '高危',
            'medium' => '中危',
            'low' => '低危',
            'informational' => '信息',
            default => $severity,
        };
    }

    /**
     * 状态标签（中文）
     */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted' => '已提交',
            'under_review' => '审核中',
            'confirmed' => '已确认',
            'fixed' => '已修复',
            'declined' => '已拒绝',
            'paid' => '已打款',
            default => $status,
        };
    }

    /**
     * 赏金等级标签
     */
    public static function bountyLabel(float $amount): string
    {
        if ($amount >= 1000) return '$' . number_format($amount, 0) . '+';
        if ($amount >= 100) return '$' . number_format($amount, 0);
        return '$' . number_format($amount, 0);
    }
}
