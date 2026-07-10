<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSearchPreference
 */
class SearchPreference extends Model
{
    protected $table = 'search_preferences';

    protected $fillable = [
        'user_id', 'recent_types', 'favorite_types',
        'excluded_types', 'results_per_page',
        'show_recent', 'show_suggestions', 'enable_shortcuts',
    ];

    protected function casts(): array
    {
        return [
            'recent_types' => 'array',
            'favorite_types' => 'array',
            'excluded_types' => 'array',
            'show_recent' => 'boolean',
            'show_suggestions' => 'boolean',
            'enable_shortcuts' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取或创建用户的搜索偏好
     */
    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'recent_types' => [],
                'results_per_page' => 20,
                'show_recent' => true,
                'show_suggestions' => true,
                'enable_shortcuts' => true,
            ]
        );
    }
}
