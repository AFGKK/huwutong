<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentSearch extends Model
{
    protected $table = 'recent_searches';

    protected $fillable = [
        'user_id', 'query', 'resource_type',
        'filters', 'result_count', 'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'searched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
