<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 蓝绿部署记录模型
 *
 * @m3-63 BlueGreenDeploy
 * @mixin IdeHelperBlueGreenDeployment
 */
class BlueGreenDeployment extends Model
{
    protected $fillable = [
        'tenant_id',
        'release_id',
        'release_version',
        'active_environment',    // blue | green
        'standby_environment',   // green | blue
        'status',                // warmup, verifying, switching, live, rolled_back, failed
        'warmup_started_at',
        'warmup_completed_at',
        'verification_started_at',
        'verification_completed_at',
        'traffic_switched_at',
        'rollback_at',
        'rollback_reason',
        'health_check_results',  // JSON
        'verification_results',  // JSON
        'metrics_before',        // JSON
        'metrics_after',         // JSON
        'performed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'warmup_started_at' => 'datetime',
            'warmup_completed_at' => 'datetime',
            'verification_started_at' => 'datetime',
            'verification_completed_at' => 'datetime',
            'traffic_switched_at' => 'datetime',
            'rollback_at' => 'datetime',
            'health_check_results' => 'array',
            'verification_results' => 'array',
            'metrics_before' => 'array',
            'metrics_after' => 'array',
        ];
    }

    const STATUSES = [
        'warmup' => '预热中',
        'verifying' => '验证中',
        'switching' => '切换中',
        'live' => '已上线',
        'rolled_back' => '已回滚',
        'failed' => '失败',
    ];
}
