<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceAppInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_id', 'tenant_id', 'user_id', 'status',
        'installed_version', 'config', 'installed_at', 'uninstalled_at',
        'rollout_id', 'previous_version', 'auto_updated',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
            'auto_updated' => 'boolean',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(MarketplaceApp::class, 'app_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rollout(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAppRollout::class, 'rollout_id');
    }
}
