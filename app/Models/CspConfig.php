<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CspConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'mode',
        'directives',
        'route_pattern',
        'priority',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'directives' => 'array',
            'priority' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 解析 CSP 指令为字符串
     */
    public function toPolicyString(): string
    {
        $parts = [];
        foreach ($this->directives ?? [] as $directive => $sources) {
            $sources = (array) $sources;
            if (empty($sources)) {
                continue;
            }
            $parts[] = $directive . ' ' . implode(' ', $sources);
        }
        return implode('; ', $parts);
    }

    /**
     * 判断是否匹配路由模式
     */
    public function matchesRoute(string $path): bool
    {
        if (empty($this->route_pattern)) {
            return true;
        }

        $pattern = str_replace(['*', '/'], ['.*', '\/'], $this->route_pattern);
        return preg_match('/^' . $pattern . '$/', $path) === 1;
    }
}
