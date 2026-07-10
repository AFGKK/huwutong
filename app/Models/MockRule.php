<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperMockRule
 */
class MockRule extends Model
{
    protected $fillable = [
        'method',
        'path',
        'status_code',
        'response',
        'description',
        'delay_ms',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'is_active' => 'boolean',
            'status_code' => 'integer',
            'delay_ms' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByMethod($q, $method)
    {
        return $q->where('method', strtoupper($method));
    }

    public function scopeByPath($q, $path)
    {
        return $q->where('path', $path);
    }
}
