<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperUrlRedirect
 */
class UrlRedirect extends Model
{
    use HasFactory;

    protected $table = 'url_redirects';

    protected $fillable = [
        'tenant_id',
        'source_url', 'target_url',
        'status_code', 'is_active', 'is_wildcard',
        'notes', 'hit_count', 'last_hit_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'is_wildcard' => 'boolean',
            'hit_count' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    const STATUS_CODES = [301, 302, 307];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * 匹配给定URL的重定向规则
     */
    public static function findMatch(string $url, int $tenantId): ?self
    {
        // 精确匹配
        $redirect = self::where('tenant_id', $tenantId)
            ->where('source_url', $url)
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            return $redirect;
        }

        // 通配符匹配
        return self::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_wildcard', true)
            ->where(function ($q) use ($url) {
                $q->where('source_url', $url)
                  ->orWhere(function ($sub) use ($url) {
                      // source_url = /old-blog/* 匹配 /old-blog/post-1
                      $pattern = str_replace(['*', '?'], ['%', '_'], $sub->getQuery()->getConnection()->raw('?'));
                      $sub->whereRaw('? LIKE REPLACE(source_url, \'*\', \'%\')', [$url]);
                  });
            })
            ->first();
    }
}
