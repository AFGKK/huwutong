<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tutorial extends Model
{
    protected $table = 'tutorials';

    protected $fillable = [
        'slug', 'title', 'description', 'category',
        'steps', 'sort_order', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
