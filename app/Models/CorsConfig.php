<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperCorsConfig
 */
class CorsConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'allowed_origins',
        'allowed_methods',
        'allowed_headers',
        'exposed_headers',
        'allow_credentials',
        'max_age',
        'route_pattern',
        'priority',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allowed_origins' => 'array',
            'allowed_methods' => 'array',
            'allowed_headers' => 'array',
            'exposed_headers' => 'array',
            'allow_credentials' => 'boolean',
            'max_age' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 判断给定的 Origin 是否匹配此配置
     */
    public function matchesOrigin(string $origin): bool
    {
        $origins = $this->allowed_origins ?? [];

        foreach ($origins as $allowed) {
            if ($allowed === '*' || $allowed === $origin) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断是否匹配路由模式
     *
     * 使用 Laravel Str::is() 支持通配符模式（如 api/*、api/license/*）
     */
    public function matchesRoute(string $path): bool
    {
        if (empty($this->route_pattern)) {
            return true;
        }

        return Str::is($this->route_pattern, $path);
    }
}
