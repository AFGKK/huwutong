<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceDownloadLog extends Model
{
    protected $table = 'marketplace_download_logs';

    protected $fillable = [
        'app_id', 'user_id', 'tenant_id',
        'action', 'ip_address', 'user_agent',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(MarketplaceApp::class, 'app_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByApp($query, int $appId)
    {
        return $query->where('app_id', $appId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
