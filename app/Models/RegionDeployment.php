<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegionDeployment extends Model
{
    protected $table = 'region_deployments';

    protected $fillable = [
        'region_key', 'name', 'provider', 'api_url',
        'status', 'is_primary', 'weight', 'config',
        'last_health_check_at', 'is_healthy', 'consecutive_failures',
        'active_deployment_id', 'version',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_primary' => 'boolean',
            'is_healthy' => 'boolean',
            'last_health_check_at' => 'datetime',
        ];
    }
}
