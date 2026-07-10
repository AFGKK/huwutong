<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperWafIpList
 */
class WafIpList extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ip', 'type', 'source', 'reason', 'hit_count',
        'expires_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'hit_count' => 'integer',
    ];

    const TYPES = ['blacklist', 'whitelist', 'challenge'];
    const SOURCES = ['manual', 'auto', 'cloudflare', 'synced'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip', $ip);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * 检查 IP 是否在列表中
     */
    public static function isInList(string $ip, string $type): bool
    {
        return static::query()
            ->active()
            ->notExpired()
            ->byType($type)
            ->get()
            ->contains(function ($item) use ($ip) {
                return static::ipInCidr($ip, $item->ip);
            });
    }

    /**
     * 检查 IP 是否在 CIDR 范围内
     */
    public static function ipInCidr(string $ip, string $cidr): bool
    {
        if (str_contains($cidr, '/')) {
            [$subnet, $bits] = explode('/', $cidr, 2);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - (int) $bits);
            $subnetLong &= $mask;

            return ($ipLong & $mask) === $subnetLong;
        }

        return $ip === $cidr;
    }
}
