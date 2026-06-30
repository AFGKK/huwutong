<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'permissions',
        'metadata',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'permissions' => 'json',
        'metadata' => 'json',
        'is_system' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}
