<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPromptTemplate
 */
class PromptTemplate extends Model
{
    protected $fillable = [
        'name', 'category', 'content', 'description', 'variables',
        'version', 'status', 'is_current', 'engine', 'temperature',
        'max_tokens', 'ab_test_config', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'ab_test_config' => 'array',
            'is_current' => 'boolean',
            'temperature' => 'decimal:2',
            'max_tokens' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($q) { $q->where('status', 'active'); }
    public function scopeByCategory($q, $cat) { $q->where('category', $cat); }
    public function scopeCurrent($q) { $q->where('is_current', true); }
}
