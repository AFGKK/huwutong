<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperDeployEnvironment
 */
class DeployEnvironment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description',
        'base_url', 'server_type', 'config',
        'is_protected', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_protected' => 'boolean',
            'is_active' => 'boolean',
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

    const SERVER_TYPES = [
        'self-hosted' => '自托管',
        'cloud' => '云服务器',
        'kubernetes' => 'Kubernetes',
    ];
}
