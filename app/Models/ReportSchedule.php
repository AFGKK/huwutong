<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSchedule extends Model
{
    protected $table = 'report_schedules';

    protected $fillable = [
        'report_id',
        'user_id',
        'tenant_id',
        'cron_expression',
        'export_format',
        'recipients',
        'subject',
        'message',
        'include_chart',
        'is_active',
        'max_retries',
        'last_run_at',
        'next_run_at',
        'last_success_at',
        'last_failure_at',
        'last_error',
        'run_count',
        'success_count',
        'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'include_chart' => 'boolean',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class, 'report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(ReportDeliveryLog::class, 'schedule_id');
    }

    /**
     * 获取待处理的调度（启用且下次运行时间已到）
     */
    public static function getDueSchedules(): array
    {
        return self::where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(20)
            ->get()
            ->all();
    }

    /**
     * 获取即将运行的调度
     */
    public static function getUpcomingSchedules(int $limit = 10): array
    {
        return self::where('is_active', true)
            ->where('next_run_at', '>', now())
            ->orderBy('next_run_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
