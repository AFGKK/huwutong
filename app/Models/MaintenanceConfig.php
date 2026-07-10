<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperMaintenanceConfig
 */
class MaintenanceConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_enabled',
        'title',
        'message',
        'whitelist_ips',
        'whitelist_paths',
        'scheduled_end_at',
        'auto_disable_at',
        'retry_after',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'whitelist_ips' => 'array',
            'whitelist_paths' => 'array',
            'scheduled_end_at' => 'datetime',
            'auto_disable_at' => 'datetime',
            'retry_after' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 判断IP是否在白名单
     */
    public function isIpWhitelisted(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        $whitelist = $this->whitelist_ips ?? [];

        foreach ($whitelist as $allowed) {
            if ($allowed === '*' || $allowed === $ip) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断路径是否在白名单
     */
    public function isPathWhitelisted(string $path): bool
    {
        $whitelist = $this->whitelist_paths ?? [];

        foreach ($whitelist as $pattern) {
            // 支持 * 通配符
            $regex = str_replace(['*', '/'], ['.*', '\/'], $pattern);
            if (preg_match('/^' . $regex . '$/', $path)) {
                return true;
            }
        }

        return false;
    }
}
