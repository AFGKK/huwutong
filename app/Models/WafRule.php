<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WafRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'category', 'severity', 'mode', 'match_type', 'pattern',
        'target', 'action', 'description', 'recommendation', 'scope',
        'is_active', 'priority', 'hit_count', 'last_hit_at', 'created_by',
    ];

    protected $casts = [
        'scope' => 'array',
        'is_active' => 'boolean',
        'last_hit_at' => 'datetime',
        'hit_count' => 'integer',
        'priority' => 'integer',
    ];

    const CATEGORIES = [
        'sqli' => 'SQL 注入',
        'xss' => '跨站脚本',
        'path_traversal' => '路径穿越',
        'cmd_injection' => '命令注入',
        'file_inclusion' => '文件包含',
        'ssrf' => 'SSRF',
        'custom' => '自定义规则',
    ];

    const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    const MODES = ['block', 'detect', 'simulate'];
    const MATCH_TYPES = ['regex', 'exact', 'prefix', 'suffix', 'contains'];
    const TARGETS = ['all', 'query', 'body', 'headers', 'cookies', 'uri'];
    const ACTIONS = ['block', 'challenge', 'log', 'allow'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority')->orderBy('name');
    }

    /**
     * 检查请求值是否匹配此规则
     */
    public function matches(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        return match ($this->match_type) {
            'regex' => preg_match($this->pattern, $value) === 1,
            'exact' => $value === $this->pattern,
            'prefix' => str_starts_with($value, $this->pattern),
            'suffix' => str_ends_with($value, $this->pattern),
            'contains' => str_contains($value, $this->pattern),
            default => false,
        };
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WafRule $rule) {
            if (empty($rule->name)) {
                $rule->name = 'rule_'.Str::random(8);
            }
        });
    }
}
