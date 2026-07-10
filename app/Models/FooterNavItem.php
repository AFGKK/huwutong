<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperFooterNavItem
 */
class FooterNavItem extends Model
{
    protected $fillable = [
        'label',
        'type',
        'url',
        'icon',
        'target',
        'sort_order',
        'is_active',
        'group',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
