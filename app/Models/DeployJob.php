<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeployJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'deploy_environment_id', 'deploy_release_id',
        'type', 'status', 'steps', 'output', 'error_message',
        'triggered_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(DeployEnvironment::class, 'deploy_environment_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(DeployRelease::class, 'deploy_release_id');
    }

    const TYPES = [
        'full' => '全量部署',
        'backend_only' => '仅后端',
        'frontend_only' => '仅前端',
        'rollback' => '回滚',
    ];

    const STATUSES = [
        'pending' => '排队中',
        'running' => '运行中',
        'success' => '成功',
        'failed' => '失败',
        'rolling_back' => '回滚中',
        'rolled_back' => '已回滚',
    ];
}
