<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDeployRelease
 */
class DeployRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'version', 'code_name', 'changelog',
        'git_branch', 'git_commit_hash', 'git_commit_message',
        'author', 'status', 'artifacts', 'metadata',
        'built_at', 'deployed_at', 'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'artifacts' => 'array',
            'metadata' => 'array',
            'built_at' => 'datetime',
            'deployed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deployJobs(): HasMany
    {
        return $this->hasMany(DeployJob::class);
    }

    const STATUSES = [
        'pending' => '待构建',
        'building' => '构建中',
        'built' => '构建完成',
        'deployed' => '已部署',
        'rolled_back' => '已回滚',
        'failed' => '失败',
    ];
}
